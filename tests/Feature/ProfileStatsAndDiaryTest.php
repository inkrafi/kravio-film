<?php

namespace Tests\Feature;

use App\Enums\MediaType;
use App\Livewire\UserProfile;
use App\Models\Friendship;
use App\Models\MediaCache;
use App\Models\Review;
use App\Models\User;
use App\Models\WatchEntry;
use App\Services\ProfileStatsService;
use App\Support\GenreNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileStatsAndDiaryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 9, 27)->setTime(12, 0));

        $this->owner = User::factory()->create(['username' => 'budi']);
    }

    private function watched(array $media, ?int $rating, string $watchedAt): WatchEntry
    {
        return WatchEntry::factory()->create([
            'user_id' => $this->owner->id,
            'media_cache_id' => MediaCache::factory()->create($media)->id,
            'rating' => $rating,
            'watched_at' => $watchedAt,
        ]);
    }

    private function profile(?User $viewer = null)
    {
        return Livewire::actingAs($viewer ?? $this->owner)->test(UserProfile::class, ['user' => $this->owner]);
    }

    public function test_genre_names_from_every_source_are_unified(): void
    {
        $this->assertSame(['Aksi', 'Petualangan'], GenreNormalizer::normalize(['Action', 'Aksi & Petualangan']));
        $this->assertSame(['Fiksi Ilmiah', 'Fantasi'], GenreNormalizer::normalize(['Sci-fi & Fantasy', 'Fantasy']));
        $this->assertSame(['Horor', 'Thriller', 'Romantis'], GenreNormalizer::normalize(['Kengerian', 'Cerita Seru', 'Percintaan']));
        // Tag AniList bukan genre, jadi tidak dihitung.
        $this->assertSame(['Drama'], GenreNormalizer::normalize(['Ninja', 'Female Protagonist', 'drama']));
        $this->assertSame([], GenreNormalizer::normalize(null));
    }

    public function test_stats_summarise_the_watch_history(): void
    {
        $this->watched(['media_type' => MediaType::Film, 'genres' => ['Drama', 'Aksi']], 8, '2026-09-01');
        $this->watched(['media_type' => MediaType::Film, 'genres' => ['Drama']], 7, '2026-02-01');
        $this->watched(['media_type' => MediaType::Series, 'genres' => ['Drama', 'Komedi']], null, '2025-12-31');
        // Anime dari AniList: genre Inggris + tag, dan otomatis dihitung Animasi.
        WatchEntry::factory()->create([
            'user_id' => $this->owner->id,
            'media_cache_id' => MediaCache::factory()->anime()->create(['genres' => ['Action', 'Ninja']])->id,
            'rating' => 9,
            'watched_at' => '2026-03-01',
        ]);
        // Watchlist tidak dihitung sama sekali.
        WatchEntry::factory()->watchlist()->create(['user_id' => $this->owner->id]);

        $stats = app(ProfileStatsService::class)->for($this->owner);

        $this->assertSame(4, $stats['total']);
        // Anime AniList dihitung sebagai anime, bukan series: film + series + anime = total.
        $this->assertSame(2, $stats['films']);
        $this->assertSame(1, $stats['series']);
        $this->assertSame(1, $stats['anime']);
        $this->assertSame(3, $stats['this_year']);
        $this->assertSame(8.0, $stats['average_rating']);
        $this->assertSame(3, $stats['rated']);
        $this->assertSame(['Drama', 'Aksi'], array_column(array_slice($stats['genres'], 0, 2), 'name'));
        $this->assertSame([3, 2], array_column(array_slice($stats['genres'], 0, 2), 'count'));
        $this->assertEqualsCanonicalizing(['Drama', 'Aksi', 'Komedi', 'Animasi'], array_column($stats['genres'], 'name'));
        $this->assertSame(0.75, $stats['genres'][0]['share']);
    }

    public function test_japanese_animation_from_tmdb_counts_as_anime(): void
    {
        $this->watched(['media_type' => MediaType::Film, 'genres' => ['Animasi'], 'raw_payload' => ['original_language' => 'ja']], 9, '2026-09-01');
        // Animasi non-Jepang (mis. Pixar) tetap film biasa.
        $this->watched(['media_type' => MediaType::Film, 'genres' => ['Animasi'], 'raw_payload' => ['original_language' => 'en']], 8, '2026-09-02');
        // Film Jepang live-action juga bukan anime.
        $this->watched(['media_type' => MediaType::Film, 'genres' => ['Drama'], 'raw_payload' => ['original_language' => 'ja']], 7, '2026-09-03');

        $stats = app(ProfileStatsService::class)->for($this->owner);

        $this->assertSame(['films' => 2, 'series' => 0, 'anime' => 1], array_intersect_key($stats, array_flip(['films', 'series', 'anime'])));
    }

    public function test_stats_for_an_empty_history(): void
    {
        $stats = app(ProfileStatsService::class)->for($this->owner);

        $this->assertSame(0, $stats['total']);
        $this->assertNull($stats['average_rating']);
        $this->assertSame([], $stats['genres']);
    }

    public function test_the_profile_shows_the_stats(): void
    {
        $this->watched(['genres' => ['Drama']], 8, '2026-09-01');
        $this->watched(['genres' => ['Drama']], 7, '2026-09-02');

        $this->profile()
            ->assertSee('Statistik')
            ->assertDontSee('Total ditonton')
            ->assertSee('7,5')
            ->assertSee('dari 2 judul yang dirating')
            ->assertDontSee('Genre favorit')
            ->assertSeeInOrder(['Rata-rata rating', 'Ditonton tahun'])
            ->assertSeeInOrder(['Sudah ditonton', '2', 'judul', 'Film', '2', 'Series', '0', 'Anime', '0'])
            ->assertSeeHtml('title="Film: 2 judul (100%)"')
            ->assertSeeInOrder(['Genre teratas', 'Drama', '2'])
            ->assertSeeHtml('href="'.route('genre.show', ['slug' => 'drama']).'"');
    }

    public function test_only_the_top_three_genres_are_shown(): void
    {
        $this->watched(['genres' => ['Drama', 'Aksi', 'Komedi', 'Horor']], 8, '2026-09-01');
        $this->watched(['genres' => ['Drama', 'Aksi', 'Komedi']], 8, '2026-09-02');
        $this->watched(['genres' => ['Drama', 'Aksi']], 8, '2026-09-03');

        $this->profile()
            ->assertSeeInOrder(['Genre teratas', 'Drama', 'Aksi', 'Komedi'])
            ->assertDontSee('Horor');
    }

    public function test_strangers_do_not_see_stats_diary_or_comments(): void
    {
        $this->watched(['title' => 'Tontonan Rahasia', 'genres' => ['Drama']], 8, '2026-09-01');

        $this->profile(User::factory()->create())
            ->assertDontSee('Rata-rata rating')
            ->assertDontSee('Tontonan Rahasia')
            ->call('selectTab', 'diary')
            ->assertDontSee('Tontonan Rahasia');
    }

    public function test_friends_see_the_stats(): void
    {
        $friend = User::factory()->create();
        Friendship::factory()->accepted()->create(['user_id' => $friend->id, 'friend_id' => $this->owner->id]);

        $this->watched(['genres' => ['Drama']], 8, '2026-09-01');

        $this->profile($friend)->assertSee('Rata-rata rating');
    }

    public function test_the_diary_lists_watched_titles_chronologically_by_month(): void
    {
        $this->watched(['title' => 'Film Agustus'], 6, '2026-08-15');
        $this->watched(['title' => 'Film Awal September'], null, '2026-09-02');
        $this->watched(['title' => 'Film Akhir September'], 9, '2026-09-20');
        WatchEntry::factory()->watchlist()->create([
            'user_id' => $this->owner->id,
            'media_cache_id' => MediaCache::factory()->create(['title' => 'Masih Watchlist'])->id,
        ]);

        $this->profile()
            ->call('selectTab', 'diary')
            ->assertSet('tab', 'diary')
            ->assertSeeInOrder([
                'September 2026', 'Film Akhir September', 'Film Awal September',
                'Agustus 2026', 'Film Agustus',
            ])
            ->assertViewHas('entries', fn ($entries) => collect($entries->items())->pluck('rating')->all() === [9, null, 6])
            ->assertDontSee('Masih Watchlist');
    }

    public function test_the_diary_marks_titles_that_were_reviewed(): void
    {
        $entry = $this->watched(['title' => 'Ada Reviewnya'], 8, '2026-09-20');
        Review::factory()->create(['user_id' => $this->owner->id, 'media_cache_id' => $entry->media_cache_id]);

        $this->profile()->call('selectTab', 'diary')->assertSee('Ada review');
    }

    public function test_the_diary_tab_is_kept_in_the_url(): void
    {
        Livewire::actingAs($this->owner)
            ->withQueryParams(['daftar' => 'diary'])
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSet('tab', 'diary')
            ->assertSee('Diary masih kosong');
    }

    public function test_an_unknown_tab_falls_back_to_watched(): void
    {
        $this->profile()->call('selectTab', 'rahasia')->assertSet('tab', 'watched');
    }
}
