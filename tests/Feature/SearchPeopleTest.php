<?php

namespace Tests\Feature;

use App\Enums\MediaType;
use App\Livewire\MediaSearch;
use App\Models\MediaCache;
use App\Models\User;
use App\Services\Media\MediaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SearchPeopleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function fakeTmdb(array $results): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => $results]),
            'graphql.anilist.co' => Http::response(['data' => ['Page' => ['media' => []]]]),
        ]);
    }

    private function person(array $overrides = []): array
    {
        return $overrides + [
            'id' => 10297,
            'media_type' => 'person',
            'name' => 'Matthew McConaughey',
            'profile_path' => '/mm.jpg',
            'known_for_department' => 'Acting',
            'popularity' => 40.0,
            'known_for' => [
                ['media_type' => 'movie', 'title' => 'Interstellar'],
                ['media_type' => 'movie', 'title' => 'Dallas Buyers Club'],
                ['media_type' => 'tv', 'name' => 'True Detective'],
            ],
        ];
    }

    public function test_people_matching_the_query_come_back_with_the_titles(): void
    {
        $this->fakeTmdb([
            $this->person(),
            ['id' => 1, 'media_type' => 'movie', 'title' => 'Mud', 'release_date' => '2012-05-26', 'popularity' => 10],
        ]);

        $results = app(MediaSearchService::class)->search('matthew mcconaughey');

        $this->assertSame([[
            'id' => 10297,
            'name' => 'Matthew McConaughey',
            'name_latin' => null,
            'photo_url' => 'https://image.tmdb.org/t/p/w185/mm.jpg',
            'department' => 'Acting',
            'known_for' => ['Interstellar', 'Dallas Buyers Club'],
            'popularity' => 40.0,
        ]], $results->people);

        // Orang tidak disimpan sebagai judul.
        $this->assertSame(['Mud'], $results->media->pluck('title')->all());
        $this->assertSame(0, MediaCache::where('external_id', '10297')->count());
    }

    public function test_people_whose_names_do_not_match_are_left_out(): void
    {
        // Mencari judul film: kru terkait yang namanya tak mirip tidak ikut tampil.
        $this->fakeTmdb([
            ['id' => 157336, 'media_type' => 'movie', 'title' => 'Interstellar', 'popularity' => 100],
            $this->person(['id' => 525, 'name' => 'Christopher Nolan', 'known_for_department' => 'Directing']),
        ]);

        $this->assertSame([], app(MediaSearchService::class)->search('interstellar')->people);
    }

    public function test_people_are_only_part_of_the_unfiltered_search(): void
    {
        $this->fakeTmdb([$this->person()]);

        $this->assertSame([], app(MediaSearchService::class)->search('matthew mcconaughey', MediaType::Film)->people);
    }

    public function test_non_latin_names_get_a_romanized_spelling(): void
    {
        $this->fakeTmdb([$this->person(['id' => 115290, 'name' => '이선균', 'known_for' => []])]);

        $person = app(MediaSearchService::class)->search('이선균')->people[0];

        $this->assertSame('Iseongyun', $person['name_latin']);
    }

    public function test_a_latin_query_finds_a_hangul_named_actor_with_the_known_spelling(): void
    {
        // Ejaan dari Gemini yang sudah tersimpan di credits judul lain.
        MediaCache::factory()->series()->create([
            'credits' => ['directors' => [], 'creators' => [], 'cast' => [
                ['id' => 115290, 'name' => '이선균', 'role' => null, 'photo_url' => null, 'name_latin' => 'Lee Sun-kyun'],
            ]],
            'localized_hash' => sha1('x'),
        ]);

        // TMDB mencocokkan lewat alias; nama utamanya tetap Hangul.
        $this->fakeTmdb([
            $this->person(['id' => 1148561, 'name' => 'Lee Sun-Kyung', 'known_for_department' => 'Writing', 'popularity' => 0.4, 'known_for' => []]),
            $this->person(['id' => 115290, 'name' => '이선균', 'popularity' => 2.0, 'known_for' => []]),
        ]);

        $people = app(MediaSearchService::class)->search('lee sun kyun')->people;

        $this->assertSame([115290, 1148561], array_column($people, 'id'));
        $this->assertSame('Lee Sun-kyun', $people[0]['name_latin']);
    }

    public function test_people_are_cached_with_the_search(): void
    {
        $this->fakeTmdb([$this->person()]);

        app(MediaSearchService::class)->search('matthew mcconaughey');
        $cached = app(MediaSearchService::class)->search('matthew mcconaughey');

        $this->assertSame(10297, $cached->people[0]['id']);
        Http::assertSentCount(5);
    }

    public function test_the_search_page_shows_people_linking_to_their_page(): void
    {
        $this->fakeTmdb([$this->person()]);

        Livewire::actingAs(User::factory()->create())
            ->test(MediaSearch::class)
            ->set('query', 'matthew mcconaughey')
            ->assertSee('Orang')
            ->assertSee('Matthew McConaughey')
            ->assertSee('Akting')
            ->assertSee('Interstellar, Dallas Buyers Club')
            ->assertSeeHtml('href="'.url('/orang/10297-matthew-mcconaughey').'"')
            // Hanya orang, tanpa judul: bukan keadaan "tidak ditemukan".
            ->assertDontSee('Tidak ditemukan hasil')
            ->assertDontSee('judul untuk');
    }
}
