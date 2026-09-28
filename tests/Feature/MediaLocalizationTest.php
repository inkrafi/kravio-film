<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Livewire\MediaDetail;
use App\Models\MediaCache;
use App\Models\User;
use App\Services\Media\Dto\MediaResult;
use App\Services\Media\MediaDetailsService;
use App\Services\Media\MediaLocalizationService;
use App\Support\Romanizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MediaLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'gemini-test-key']);

        Http::preventStrayRequests();
    }

    private function service(): MediaLocalizationService
    {
        return app(MediaLocalizationService::class);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function fakeGemini(array $json): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($json, JSON_UNESCAPED_UNICODE)]]],
                    'finishReason' => 'STOP',
                ]],
            ]),
        ]);
    }

    private function myMister(): MediaCache
    {
        return MediaCache::factory()->series()->create([
            'title' => '나의 아저씨',
            'original_title' => '나의 아저씨',
            'title_latin' => null,
            'synopsis' => 'Three brothers and a woman help one another heal.',
            'credits' => [
                'directors' => [],
                'creators' => [['name' => '박해영', 'role' => null, 'photo_url' => null]],
                'cast' => [
                    ['name' => '이선균', 'role' => 'Park Dong-hoon', 'photo_url' => null],
                    ['name' => 'IU', 'role' => 'Lee Ji-an', 'photo_url' => null],
                ],
            ],
            'details_synced_at' => now(),
        ]);
    }

    public function test_script_detection_and_offline_romanization(): void
    {
        $this->assertTrue(Romanizer::isLatin('Amélie: Le Fabuleux Destin, 2001!'));
        $this->assertFalse(Romanizer::isLatin('나의 아저씨'));
        $this->assertFalse(Romanizer::isLatin('ONE PIECE エピソードオブ空島'));

        $this->assertSame('Naui Ajeossi', Romanizer::romanize('나의 아저씨', 'ko'));
        $this->assertSame('Da Gong Bu Ru Da Dian Hua', Romanizer::romanize('打工不如打电话', 'zh'));
        // Kanji Jepang dibaca dengan lafal Mandarin oleh ICU, jadi dibiarkan kosong.
        $this->assertNull(Romanizer::romanize('葬送のフリーレン', 'ja'));
        $this->assertNull(Romanizer::romanize('打工不如打电话'));
        $this->assertNull(Romanizer::romanize('Interstellar'));
    }

    public function test_search_results_carry_a_latin_title(): void
    {
        $korean = new MediaResult(
            source: MediaSource::Tmdb, mediaType: MediaType::Series, externalId: '1',
            title: '나의 아저씨', originalTitle: '나의 아저씨', raw: ['original_language' => 'ko'],
        );
        $withEnglish = $korean->fillGapsFrom(new MediaResult(
            source: MediaSource::Tmdb, mediaType: MediaType::Series, externalId: '1',
            title: 'My Mister', titleLatin: 'My Mister',
        ));

        // Tanpa judul en-US: romanisasi cadangan.
        $this->assertSame('Naui Ajeossi', $korean->latinColumns()['title_latin']);
        // Dengan judul en-US dari request cadangan TMDB: itu yang dipakai.
        $this->assertSame('My Mister', $withEnglish->latinColumns()['title_latin']);

        $frieren = new MediaResult(
            source: MediaSource::Anilist, mediaType: MediaType::Series, externalId: '2',
            title: "Frieren: Beyond Journey's End", originalTitle: '葬送のフリーレン', originalTitleLatin: 'Sousou no Frieren',
        );

        $this->assertSame(['title_latin' => null, 'original_title_latin' => 'Sousou no Frieren'], $frieren->latinColumns());
    }

    public function test_gemini_translates_the_synopsis_and_latinizes_titles_and_names(): void
    {
        $this->fakeGemini([
            'synopsis' => 'Tiga bersaudara dan seorang perempuan saling membantu pulih.',
            'title_latin' => 'My Mister',
            'original_title_latin' => 'Naui Ajeossi',
            'names' => [
                ['original' => '이선균', 'latin' => 'Lee Sun-kyun'],
                ['original' => '박해영', 'latin' => 'Park Hae-young'],
            ],
        ]);

        $media = $this->service()->localize($this->myMister())->fresh();

        $this->assertSame('Tiga bersaudara dan seorang perempuan saling membantu pulih.', $media->displaySynopsis());
        $this->assertSame('Three brothers and a woman help one another heal.', $media->synopsis);
        $this->assertTrue($media->hasTranslatedSynopsis());
        $this->assertSame('My Mister', $media->title_latin);
        $this->assertSame('Naui Ajeossi', $media->original_title_latin);
        $this->assertSame('Lee Sun-kyun', $media->people('cast')[0]['name_latin']);
        $this->assertArrayNotHasKey('name_latin', $media->people('cast')[1]);
        $this->assertSame('Park Hae-young', $media->people('creators')[0]['name_latin']);

        // Hanya nama non-latin yang dikirim, dan jawabannya diminta dalam JSON.
        Http::assertSent(function ($request) {
            $input = json_decode($request['contents'][0]['parts'][0]['text'], true);

            return $request->hasHeader('x-goog-api-key', 'gemini-test-key')
                && $request['generationConfig']['responseMimeType'] === 'application/json'
                && $input['names'] === ['박해영', '이선균']
                && $input['title'] === '나의 아저씨';
        });
    }

    public function test_it_only_asks_again_when_the_source_text_changes(): void
    {
        $this->fakeGemini(['synopsis' => 'Terjemahan.', 'title_latin' => 'My Mister', 'original_title_latin' => null, 'names' => []]);

        $media = $this->service()->localize($this->myMister());

        $this->assertFalse($this->service()->needsLocalization($media->fresh()));

        $media->update(['synopsis' => 'A new, longer overview.']);

        $this->assertTrue($this->service()->needsLocalization($media->fresh()));
    }

    public function test_names_gemini_could_not_latinize_are_not_retried_forever(): void
    {
        $this->fakeGemini(['synopsis' => 'Terjemahan.', 'title_latin' => 'My Mister', 'original_title_latin' => null, 'names' => []]);

        $media = $this->service()->localize($this->myMister())->fresh();

        // Hangul masih bisa dirumanisasi secara offline sebagai cadangan.
        $this->assertSame('Iseongyun', $media->people('cast')[0]['name_latin']);
        $this->assertFalse($this->service()->needsLocalization($media));
    }

    public function test_non_latin_answers_are_ignored(): void
    {
        $this->fakeGemini(['synopsis' => 'Terjemahan.', 'title_latin' => '나의 아저씨', 'original_title_latin' => null, 'names' => []]);

        $media = $this->service()->localize($this->myMister())->fresh();

        $this->assertNull($media->title_latin);
    }

    public function test_a_gemini_failure_is_retried_on_the_next_visit(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Quota exceeded']], 429)]);

        $media = $this->service()->localize($this->myMister())->fresh();

        $this->assertNull($media->synopsis_id);
        $this->assertNull($media->localized_hash);
        $this->assertTrue($this->service()->needsLocalization($media));
    }

    public function test_latin_titles_without_a_synopsis_need_no_request(): void
    {
        Http::fake();

        $media = MediaCache::factory()->film()->create(['title' => 'Interstellar', 'synopsis' => null]);

        $this->service()->localize($media);

        Http::assertNothingSent();
        $this->assertFalse($this->service()->needsLocalization($media->fresh()));
    }

    public function test_nothing_happens_without_a_gemini_key(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $media = $this->myMister();

        $this->assertFalse($this->service()->needsLocalization($media));
        $this->service()->localize($media);

        Http::assertNothingSent();
    }

    public function test_the_detail_page_shows_the_translation_and_latin_names(): void
    {
        $this->fakeGemini([
            'synopsis' => 'Tiga bersaudara dan seorang perempuan saling membantu pulih.',
            'title_latin' => 'My Mister',
            'original_title_latin' => null,
            'names' => [['original' => '이선균', 'latin' => 'Lee Sun-kyun'], ['original' => '박해영', 'latin' => 'Park Hae-young']],
        ]);

        $media = $this->myMister();

        Livewire::actingAs(User::factory()->create())
            ->test(MediaDetail::class, ['media' => $media])
            ->assertSee('Menerjemahkan sinopsis')
            ->call('loadDetails')
            ->assertSee('My Mister')
            ->assertSee('Tiga bersaudara dan seorang perempuan saling membantu pulih.')
            ->assertSee('Diterjemahkan otomatis')
            ->assertSee('Lihat teks asli')
            ->assertSee('Lee Sun-kyun')
            ->assertSee('Park Hae-young (박해영)');
    }

    public function test_latin_names_survive_a_credits_refresh(): void
    {
        config(['services.tmdb.key' => 'tmdb-test-key']);

        $media = $this->myMister();
        $media->forceFill(['details_synced_at' => null, 'credits' => [
            'directors' => [], 'creators' => [],
            'cast' => [['name' => '이선균', 'role' => 'Park Dong-hoon', 'photo_url' => null, 'name_latin' => 'Lee Sun-kyun']],
        ]])->save();

        Http::fake(['api.themoviedb.org/3/tv/*' => Http::response([
            'vote_average' => 9.1, 'vote_count' => 800, 'created_by' => [],
            'aggregate_credits' => ['cast' => [['name' => '이선균', 'order' => 0, 'profile_path' => null, 'roles' => [['character' => 'Park Dong-hoon']]]], 'crew' => []],
        ])]);

        app(MediaDetailsService::class)->refresh($media);

        $this->assertSame('Lee Sun-kyun', $media->fresh()->people('cast')[0]['name_latin']);
    }

    public function test_cards_show_the_latin_title_and_indonesian_genres(): void
    {
        $media = MediaCache::factory()->series()->make([
            'title' => '나의 아저씨',
            'title_latin' => 'My Mister',
            'genres' => ['Drama', 'Ninja', 'Action'],
        ]);

        $this->blade('<x-media-card :media="$media" />', ['media' => $media])
            ->assertSee('My Mister')
            ->assertSee('Drama, Aksi')
            ->assertDontSee('Ninja');
    }
}
