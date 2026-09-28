<?php

namespace Tests\Feature;

use App\Jobs\GenerateUserInsight;
use App\Livewire\InsightPage;
use App\Models\AiInsight;
use App\Models\Friendship;
use App\Models\MediaCache;
use App\Models\Review;
use App\Models\User;
use App\Models\WatchEntry;
use App\Services\Insights\FriendSimilarityService;
use App\Services\Insights\InsightService;
use App\Services\Insights\RecommendationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AiInsightTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'gemini-test-key', 'services.tmdb.key' => 'tmdb-test-key']);

        Http::preventStrayRequests();

        $this->user = User::factory()->create(['username' => 'budi']);
    }

    private function watch(User $user, array $media = [], ?int $rating = 8, string $watchedAt = '2026-09-01'): WatchEntry
    {
        return WatchEntry::factory()->create([
            'user_id' => $user->id,
            'media_cache_id' => MediaCache::factory()->film()->create($media)->id,
            'rating' => $rating,
            'watched_at' => $watchedAt,
        ]);
    }

    private function befriend(User $a, User $b): void
    {
        Friendship::factory()->accepted()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    }

    /**
     * TMDB & AniList kosong kecuali rekomendasi untuk satu seed; Gemini membalas $gemini.
     */
    private function fakeApis(array $gemini, array $recommendations = []): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => [['id' => 18, 'name' => 'Drama']]]),
            'api.themoviedb.org/3/movie/*/recommendations*' => Http::response(['results' => $recommendations]),
            'api.themoviedb.org/3/discover/*' => Http::response(['total_pages' => 1, 'results' => []]),
            'graphql.anilist.co' => Http::response(['data' => ['Page' => ['pageInfo' => ['hasNextPage' => false], 'media' => []]]]),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
                'content' => ['parts' => [['text' => json_encode($gemini, JSON_UNESCAPED_UNICODE)]]],
            ]]]),
        ]);
    }

    private function geminiAnswer(array $recommendations = []): array
    {
        return [
            'headline' => 'Pemburu drama yang royal memberi nilai',
            'summary' => "Kamu sudah menonton 3 judul.\n\nRata-rata ratingmu 8.",
            'highlights' => [
                ['label' => 'Genre andalan', 'text' => 'Drama mendominasi tontonanmu.'],
                ['label' => '', 'text' => 'Label kosong dibuang.'],
            ],
            'recommendations' => $recommendations,
        ];
    }

    private function userWithHistory(): void
    {
        $this->watch($this->user, ['title' => 'Interstellar', 'external_id' => '157336', 'genres' => ['Drama']], 9);
        $this->watch($this->user, ['title' => 'Whiplash', 'genres' => ['Drama']], 8, '2026-08-01');
        $this->watch($this->user, ['title' => 'Tenet', 'genres' => ['Aksi']], 6, '2026-07-01');
    }

    public function test_candidates_come_from_favourite_titles_and_skip_known_ones(): void
    {
        $this->userWithHistory();
        $known = MediaCache::factory()->film()->create(['external_id' => '27205', 'title' => 'Inception']);
        WatchEntry::factory()->watchlist()->create(['user_id' => $this->user->id, 'media_cache_id' => $known->id]);

        $this->fakeApis([], [
            ['id' => 27205, 'title' => 'Inception', 'genre_ids' => [18]],
            ['id' => 1124, 'title' => 'The Prestige', 'genre_ids' => [18]],
        ]);

        $candidates = app(RecommendationService::class)->candidates($this->user);

        $this->assertSame(['The Prestige'], $candidates->pluck('media.title')->all());
        $this->assertSame('Interstellar', $candidates->first()['because']);
        // Hanya judul rating >= 8 yang jadi dasar rekomendasi TMDB.
        Http::assertSentCount(2 + count(Http::recorded(fn ($request) => ! str_contains($request->url(), '/recommendations'))));
    }

    public function test_friend_similarity_uses_genre_profiles_and_accepted_friends_only(): void
    {
        $this->userWithHistory();

        $twin = User::factory()->create(['name' => 'Kembar']);
        $this->befriend($this->user, $twin);
        $this->watch($twin, ['genres' => ['Drama']], 7);
        $this->watch($twin, ['genres' => ['Drama']], 7);
        $pick = $this->watch($twin, ['title' => 'Pilihan Kembar', 'genres' => ['Aksi']], 9);

        $opposite = User::factory()->create();
        $this->befriend($opposite, $this->user);
        foreach (range(1, 3) as $i) {
            $this->watch($opposite, ['genres' => ['Horor']], 8);
        }

        $pendingFriend = User::factory()->create();
        Friendship::factory()->create(['user_id' => $pendingFriend->id, 'friend_id' => $this->user->id]);
        foreach (range(1, 3) as $i) {
            $this->watch($pendingFriend, ['genres' => ['Drama']], 9);
        }

        $friends = app(FriendSimilarityService::class)->for($this->user);

        $this->assertSame([$twin->id, $opposite->id], array_column($friends, 'user_id'));
        $this->assertSame(1.0, $friends[0]['similarity']);
        $this->assertSame(0.0, $friends[1]['similarity']);
        $this->assertSame(['Drama', 'Aksi'], $friends[0]['shared_genres']);
        $this->assertSame([['media_id' => $pick->media_cache_id, 'rating' => 9]], $friends[0]['picks']);
    }

    public function test_generating_stores_a_validated_insight(): void
    {
        $this->userWithHistory();

        // Baris kandidat sudah ada, jadi id-nya diketahui sebelum Gemini "memilih".
        $prestige = MediaCache::factory()->film()->create(['external_id' => '1124', 'title' => 'The Prestige']);
        $memento = MediaCache::factory()->film()->create(['external_id' => '77', 'title' => 'Memento']);

        $this->fakeApis(
            $this->geminiAnswer([
                ['id' => 999999, 'reason' => 'Id karangan harus dibuang.'],
                ['id' => $prestige->id, 'reason' => 'Twist yang memutar otak seperti Interstellar.'],
                ['id' => $prestige->id, 'reason' => 'Duplikat dibuang.'],
            ]),
            [['id' => 1124, 'title' => 'The Prestige', 'genre_ids' => [18]], ['id' => 77, 'title' => 'Memento', 'genre_ids' => [18]]],
        );

        $insight = app(InsightService::class)->generate($this->user);

        $this->assertSame('gemini-3.1-flash-lite', $insight->model);
        $this->assertSame('Pemburu drama yang royal memberi nilai', $insight->content['headline']);
        $this->assertCount(1, $insight->content['highlights']);
        $this->assertSame(
            [
                ['media_id' => $prestige->id, 'reason' => 'Twist yang memutar otak seperti Interstellar.'],
                // Pilihan Gemini terlalu sedikit: dilengkapi kandidat lain.
                ['media_id' => $memento->id, 'reason' => 'Karena kamu menyukai Interstellar.'],
            ],
            $insight->content['recommendations'],
        );
        $this->assertSame(app(InsightService::class)->fingerprint($this->user), $insight->content['fingerprint']);
    }

    public function test_the_prompt_contains_own_history_but_no_friend_data(): void
    {
        $this->userWithHistory();
        Review::factory()->create(['user_id' => $this->user->id, 'media_cache_id' => MediaCache::where('title', 'Interstellar')->value('id'), 'body' => 'Adegan dockingnya bikin merinding.']);

        $friend = User::factory()->create();
        $this->befriend($this->user, $friend);
        foreach (range(1, 3) as $i) {
            $this->watch($friend, ['title' => "Rahasia Teman {$i}", 'genres' => ['Drama']], 9);
        }

        $this->fakeApis($this->geminiAnswer());

        app(InsightService::class)->generate($this->user);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generativelanguage')) {
                return false;
            }

            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'Interstellar')
                && str_contains($prompt, 'Adegan dockingnya bikin merinding.')
                && ! str_contains($prompt, 'Rahasia Teman');
        });
    }

    public function test_users_with_too_little_history_get_no_insight(): void
    {
        $this->watch($this->user);
        Http::fake();

        $this->assertNull(app(InsightService::class)->generate($this->user));
        $this->assertFalse(app(InsightService::class)->isDue($this->user));
        Http::assertNothingSent();
    }

    public function test_the_schedule_skips_fresh_or_unchanged_insights(): void
    {
        $this->userWithHistory();
        $insights = app(InsightService::class);

        $this->assertTrue($insights->isDue($this->user));

        $insight = $this->user->aiInsights()->create([
            'content' => ['fingerprint' => $insights->fingerprint($this->user)],
            'generated_at' => now()->subDays(8),
        ]);

        // Sudah seminggu tapi tidak ada yang berubah: tidak perlu memanggil Gemini.
        $this->assertFalse($insights->isDue($this->user));

        $this->travel(1)->seconds();
        $this->watch($this->user, ['genres' => ['Komedi']]);
        $this->assertTrue($insights->isDue($this->user));

        // Masih baru: tunggu jadwal berikutnya.
        $insight->update(['generated_at' => now()->subDays(2)]);
        $this->assertFalse($insights->isDue($this->user));
    }

    public function test_manual_refresh_is_limited_to_once_a_day(): void
    {
        $this->userWithHistory();
        $insights = app(InsightService::class);

        $this->assertTrue($insights->canRefreshManually($this->user));

        $this->user->aiInsights()->create(['content' => [], 'generated_at' => now()->subHours(3)]);
        $this->assertFalse($insights->canRefreshManually($this->user));

        $this->travel(22)->hours();
        $this->assertTrue($insights->canRefreshManually($this->user));
    }

    public function test_the_command_queues_only_due_users(): void
    {
        Queue::fake();
        $this->userWithHistory();

        $idle = User::factory()->create();
        $this->watch($idle);

        $this->artisan('kravio:insights')->expectsOutputToContain('1 insight masuk antrean')->assertSuccessful();

        Queue::assertPushed(GenerateUserInsight::class, fn ($job) => $job->user->is($this->user));
        Queue::assertPushed(GenerateUserInsight::class, 1);
    }

    public function test_the_command_refuses_to_run_without_a_key(): void
    {
        config(['services.gemini.key' => null]);
        Queue::fake();

        $this->artisan('kravio:insights')->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_insights_are_scheduled_weekly_on_monday_morning_jakarta_time(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'kravio:insights'));

        $this->assertNotNull($event);
        $this->assertSame('0 6 * * 1', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
    }

    public function test_the_job_is_rate_limited_and_clears_its_pending_flag(): void
    {
        $this->userWithHistory();
        $this->fakeApis($this->geminiAnswer());
        Cache::put(GenerateUserInsight::pendingKey($this->user->id), true);

        $job = new GenerateUserInsight($this->user);
        $this->assertInstanceOf(RateLimited::class, $job->middleware()[0]);

        $job->handle(app(InsightService::class));

        $this->assertFalse(Cache::has(GenerateUserInsight::pendingKey($this->user->id)));
        $this->assertSame(1, AiInsight::count());
    }

    public function test_a_failed_job_is_reported_on_the_page(): void
    {
        $this->userWithHistory();
        Cache::put(GenerateUserInsight::pendingKey($this->user->id), true);

        (new GenerateUserInsight($this->user))->failed(new \RuntimeException('Gemini membalas 503'));

        Livewire::actingAs($this->user)
            ->test(InsightPage::class)
            ->assertSee('Insight gagal dibuat')
            ->assertSee('Buat insight sekarang');
    }

    public function test_the_page_explains_what_is_needed_first(): void
    {
        Livewire::actingAs($this->user)->test(InsightPage::class)->assertSee('Tandai minimal 3 judul');

        config(['services.gemini.key' => null]);
        Livewire::actingAs($this->user)->test(InsightPage::class)->assertSee('GEMINI_API_KEY');
    }

    public function test_the_button_queues_a_job_and_the_page_polls(): void
    {
        Queue::fake();
        $this->userWithHistory();

        Livewire::actingAs($this->user)
            ->test(InsightPage::class)
            ->assertSee('Buat insight sekarang')
            ->call('generate')
            ->assertSee('Sedang dianalisis')
            ->assertSeeHtml('wire:poll.5s')
            ->call('generate');

        Queue::assertPushed(GenerateUserInsight::class, 1);
    }

    public function test_the_page_renders_the_stored_insight(): void
    {
        $this->userWithHistory();
        $recommended = MediaCache::factory()->film()->create(['title' => 'The Prestige']);
        $friend = User::factory()->create(['name' => 'Citra']);
        $friendPick = MediaCache::factory()->film()->create(['title' => 'Pilihan Citra']);

        $this->user->aiInsights()->create([
            'model' => 'gemini-3.8-flash',
            'generated_at' => now()->subHours(3),
            'content' => [
                'headline' => 'Pemburu drama yang royal memberi nilai',
                'summary' => "Paragraf satu.\n\nParagraf dua.",
                'highlights' => [['label' => 'Genre andalan', 'text' => 'Drama mendominasi.']],
                'recommendations' => [['media_id' => $recommended->id, 'reason' => 'Twist seperti Interstellar.']],
                'friends' => [['user_id' => $friend->id, 'similarity' => 0.82, 'shared_genres' => ['Drama'], 'shared_titles' => 2, 'picks' => [['media_id' => $friendPick->id, 'rating' => 9]]]],
                'fingerprint' => 'x',
            ],
        ]);

        $this->actingAs($this->user)->get(route('insight'))->assertOk()->assertSeeLivewire(InsightPage::class);

        Livewire::actingAs($this->user)
            ->test(InsightPage::class)
            ->assertSee('Terakhir update')
            ->assertSee('Pemburu drama yang royal memberi nilai')
            ->assertSeeInOrder(['Paragraf satu.', 'Paragraf dua.'])
            ->assertSee('Genre andalan')
            ->assertSee('The Prestige')
            ->assertSee('Twist seperti Interstellar.')
            ->assertSee('Citra')
            ->assertSee('82% mirip')
            ->assertSee('Sama-sama suka Drama.')
            ->assertSee('Pilihan Citra')
            ->assertSee('Bisa diperbarui manual lagi');
    }

    public function test_insights_are_private(): void
    {
        $this->userWithHistory();
        $this->user->aiInsights()->create(['generated_at' => now(), 'content' => [
            'headline' => 'Rahasia Budi', 'summary' => 'x', 'highlights' => [], 'recommendations' => [], 'friends' => [],
        ]]);

        $this->get(route('insight'))->assertRedirect(route('login'));
        Livewire::actingAs(User::factory()->create())->test(InsightPage::class)->assertDontSee('Rahasia Budi');
    }
}
