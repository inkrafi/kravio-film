<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\MediaCache;
use App\Services\Media\MediaSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sumber utama: TMDB dan AniList, keduanya mengisi Film + Series (anime movie
 * masuk Film, anime berepisode masuk Series).
 * Sumber cadangan: Jikan, hanya dipakai kalau AniList gagal.
 */
class MediaSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function service(): MediaSearchService
    {
        return app(MediaSearchService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function tmdbPayload(): array
    {
        return [
            'results' => [
                [
                    'id' => 157336,
                    'media_type' => 'movie',
                    'title' => 'Interstellar',
                    'original_title' => 'Interstellar',
                    'overview' => 'Perjalanan menembus lubang cacing.',
                    'poster_path' => '/poster.jpg',
                    'backdrop_path' => '/backdrop.jpg',
                    'release_date' => '2014-11-05',
                    'genre_ids' => [12, 18],
                    'popularity' => 120.5,
                ],
                [
                    'id' => 1396,
                    'media_type' => 'tv',
                    'name' => 'Breaking Bad',
                    'original_name' => 'Breaking Bad',
                    'overview' => 'Guru kimia jadi produsen narkoba.',
                    'poster_path' => '/bb.jpg',
                    'first_air_date' => '2008-01-20',
                    'genre_ids' => [18],
                    'popularity' => 300.0,
                ],
                [
                    'id' => 999,
                    'media_type' => 'person',
                    'name' => 'Christopher Nolan',
                    'popularity' => 80.0,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function anilistPayload(): array
    {
        return [
            'data' => [
                'Page' => [
                    'media' => [
                        [
                            'id' => 154587,
                            'title' => [
                                'romaji' => 'Sousou no Frieren',
                                'english' => "Frieren: Beyond Journey's End",
                                'native' => '葬送のフリーレン',
                            ],
                            'description' => 'Elf yang hidup jauh lebih lama.<br><i>Berdasarkan manga.</i>',
                            'coverImage' => [
                                'extraLarge' => 'https://img.anili.st/frieren-xl.jpg',
                                'large' => 'https://img.anili.st/frieren-l.jpg',
                            ],
                            'bannerImage' => 'https://img.anili.st/frieren-banner.jpg',
                            'startDate' => ['year' => 2023, 'month' => 9, 'day' => 29],
                            'seasonYear' => 2023,
                            'format' => 'TV',
                            'genres' => ['Adventure', 'Drama', 'Fantasy'],
                            'tags' => [
                                ['name' => 'Female Protagonist', 'rank' => 90, 'isGeneralSpoiler' => false],
                                ['name' => 'Tragedy', 'rank' => 75, 'isGeneralSpoiler' => false],
                                ['name' => 'Bocoran Besar', 'rank' => 95, 'isGeneralSpoiler' => true],
                                ['name' => 'Tag Sepele', 'rank' => 20, 'isGeneralSpoiler' => false],
                            ],
                            'popularity' => 480000,
                        ],
                        [
                            'id' => 142770,
                            'title' => [
                                'romaji' => 'Suzume no Tojimari',
                                'english' => 'Suzume',
                                'native' => 'すずめの戸締まり',
                            ],
                            'description' => 'Gadis yang menutup pintu bencana.',
                            'coverImage' => ['extraLarge' => 'https://img.anili.st/suzume-xl.jpg', 'large' => null],
                            'bannerImage' => null,
                            'startDate' => ['year' => 2022, 'month' => 11, 'day' => 11],
                            'seasonYear' => null,
                            'format' => 'MOVIE',
                            'genres' => ['Adventure', 'Fantasy'],
                            'tags' => [],
                            'popularity' => 150000,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jikanPayload(): array
    {
        return [
            'data' => [
                [
                    'mal_id' => 52991,
                    'type' => 'TV',
                    'title' => 'Sousou no Frieren',
                    'title_english' => "Frieren: Beyond Journey's End",
                    'title_japanese' => '葬送のフリーレン',
                    'synopsis' => 'Elf yang hidup jauh lebih lama dari rekannya.',
                    'images' => ['jpg' => ['large_image_url' => 'https://cdn.myanimelist.net/frieren.jpg']],
                    'aired' => ['from' => '2023-09-29T00:00:00+00:00'],
                    'year' => 2023,
                    'genres' => [['name' => 'Adventure'], ['name' => 'Drama']],
                    'themes' => [['name' => 'Adventure']],
                    'demographics' => [['name' => 'Shounen']],
                    'members' => 900000,
                ],
            ],
        ];
    }

    /**
     * Kedua sumber utama sehat. Http::fake() menumpuk stub dan stub pertama yang
     * cocok menang, jadi tiap test memilih sendiri skenario mana yang dipasang.
     */
    private function fakeHealthyPrimaries(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => [
                ['id' => 12, 'name' => 'Petualangan'],
                ['id' => 18, 'name' => 'Drama'],
            ]]),
            'api.themoviedb.org/3/search/multi*' => Http::response($this->tmdbPayload()),
            'graphql.anilist.co' => Http::response($this->anilistPayload()),
        ]);
    }

    public function test_it_merges_film_and_series_from_every_source_into_one_result_set(): void
    {
        $this->fakeHealthyPrimaries();

        $results = $this->service()->search('interstellar');

        $this->assertFalse($results->isEmpty());
        $this->assertEqualsCanonicalizing(
            [MediaType::Film->value, MediaType::Series->value],
            $results->media->pluck('media_type')->map->value->unique()->values()->all(),
        );
        $this->assertEqualsCanonicalizing(
            [MediaSource::Tmdb->value, MediaSource::Anilist->value],
            $results->media->pluck('source')->map->value->unique()->values()->all(),
        );
        $this->assertSame([], $results->failedSources);
    }

    public function test_anime_movies_become_films_and_episodic_anime_become_series(): void
    {
        $this->fakeHealthyPrimaries();

        $this->service()->search('frieren');

        $this->assertSame(MediaType::Series, MediaCache::where('external_id', '154587')->sole()->media_type);
        $this->assertSame(MediaType::Film, MediaCache::where('external_id', '142770')->sole()->media_type);
    }

    public function test_it_caches_normalised_tmdb_results_into_media_cache(): void
    {
        $this->fakeHealthyPrimaries();

        $this->service()->search('interstellar');

        $this->assertDatabaseCount('media_cache', 4);

        $film = MediaCache::where('external_id', '157336')->sole();

        $this->assertSame(MediaSource::Tmdb, $film->source);
        $this->assertSame(MediaType::Film, $film->media_type);
        $this->assertSame('Interstellar', $film->title);
        $this->assertSame(2014, $film->year);
        $this->assertSame('https://image.tmdb.org/t/p/w342/poster.jpg', $film->poster_url);
        $this->assertSame(['Petualangan', 'Drama'], $film->genres);
        $this->assertNotNull($film->raw_payload);
    }

    public function test_it_normalises_anilist_results(): void
    {
        $this->fakeHealthyPrimaries();

        $this->service()->search('frieren', MediaType::Series);

        $anime = MediaCache::where('source', MediaSource::Anilist->value)->sole();

        $this->assertSame(MediaType::Series, $anime->media_type);
        $this->assertSame('154587', $anime->external_id);
        $this->assertSame("Frieren: Beyond Journey's End", $anime->title);
        $this->assertSame('葬送のフリーレン', $anime->original_title);
        $this->assertSame('https://img.anili.st/frieren-xl.jpg', $anime->poster_url);
        $this->assertSame('https://img.anili.st/frieren-banner.jpg', $anime->backdrop_url);
        $this->assertSame(2023, $anime->year);
        $this->assertSame('2023-09-29', $anime->released_on->toDateString());

        // Markup HTML dibersihkan dari sinopsis.
        $this->assertStringNotContainsString('<', $anime->synopsis);
        $this->assertStringContainsString('Berdasarkan manga.', $anime->synopsis);

        // Genre resmi plus tag berperingkat tinggi; tag spoiler & tag remeh dibuang.
        $this->assertSame(
            ['Adventure', 'Drama', 'Fantasy', 'Female Protagonist', 'Tragedy'],
            $anime->genres,
        );
    }

    public function test_an_empty_indonesian_synopsis_is_patched_from_the_original_language(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/genre/')) {
                return Http::response(['genres' => []]);
            }

            if (str_contains($request->url(), 'anilist.co')) {
                return Http::response(['data' => ['Page' => ['media' => []]]]);
            }

            $film = [
                'id' => 157336,
                'media_type' => 'movie',
                'title' => 'Interstellar',
                'poster_path' => '/poster.jpg',
                'release_date' => '2014-11-05',
                'genre_ids' => [],
                'popularity' => 120.5,
            ];

            // TMDB membalas overview kosong untuk judul yang belum diterjemahkan.
            return str_contains($request->url(), 'language=en-US')
                ? Http::response(['results' => [$film + ['overview' => 'A team of explorers travel through a wormhole.']]])
                : Http::response(['results' => [$film + ['overview' => '']]]);
        });

        $this->service()->search('interstellar', MediaType::Film);

        $film = MediaCache::where('external_id', '157336')->sole();

        $this->assertSame('A team of explorers travel through a wormhole.', $film->synopsis);
    }

    public function test_an_existing_indonesian_synopsis_wins_over_the_fallback_language(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/genre/')) {
                return Http::response(['genres' => []]);
            }

            if (str_contains($request->url(), 'anilist.co')) {
                return Http::response(['data' => ['Page' => ['media' => []]]]);
            }

            $film = [
                'id' => 157336,
                'media_type' => 'movie',
                'title' => 'Interstellar',
                'poster_path' => '/poster.jpg',
                'release_date' => '2014-11-05',
                'genre_ids' => [],
                'popularity' => 120.5,
            ];

            return str_contains($request->url(), 'language=en-US')
                ? Http::response(['results' => [$film + ['overview' => 'A team of explorers.']]])
                : Http::response(['results' => [$film + ['overview' => 'Sekelompok penjelajah menembus lubang cacing.']]]);
        });

        $this->service()->search('interstellar', MediaType::Film);

        $film = MediaCache::where('external_id', '157336')->sole();

        $this->assertSame('Sekelompok penjelajah menembus lubang cacing.', $film->synopsis);
    }

    public function test_the_fallback_language_request_is_skipped_when_already_english(): void
    {
        config(['services.tmdb.language' => 'en-US']);

        $this->fakeHealthyPrimaries();

        $this->service()->search('interstellar', MediaType::Film);

        $this->assertCount(
            1,
            Http::recorded(fn ($request) => str_contains($request->url(), '/search/multi')),
        );
    }

    public function test_repeating_a_search_updates_instead_of_duplicating_rows(): void
    {
        $this->fakeHealthyPrimaries();

        $this->service()->search('interstellar');
        $this->travel(1)->hours();
        cache()->flush();
        $this->service()->search('interstellar');

        $this->assertDatabaseCount('media_cache', 4);
    }

    public function test_search_results_get_a_url_slug(): void
    {
        $this->fakeHealthyPrimaries();

        $this->service()->search('interstellar');

        $this->assertSame(url('/film/interstellar'), MediaCache::where('external_id', '157336')->sole()->url());
        $this->assertSame(url('/series/breaking-bad'), MediaCache::where('external_id', '1396')->sole()->url());
        $this->assertSame(0, MediaCache::whereNull('slug')->count());
    }

    public function test_it_skips_person_results_from_tmdb(): void
    {
        $this->fakeHealthyPrimaries();

        $this->service()->search('nolan');

        $this->assertDatabaseMissing('media_cache', ['external_id' => '999']);
    }

    public function test_filtering_by_film_keeps_films_and_anime_movies_only(): void
    {
        $this->fakeHealthyPrimaries();

        $results = $this->service()->search('interstellar', MediaType::Film);

        $this->assertEqualsCanonicalizing(
            ['Interstellar', 'Suzume'],
            $results->media->pluck('title')->all(),
        );

        // AniList diminta menyaring format di sisi server.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'anilist.co')
            && $request['variables']['formatIn'] === ['MOVIE']
            && ! array_key_exists('formatNotIn', $request['variables']));
    }

    public function test_filtering_by_series_keeps_series_and_episodic_anime_only(): void
    {
        $this->fakeHealthyPrimaries();

        $results = $this->service()->search('interstellar', MediaType::Series);

        $this->assertEqualsCanonicalizing(
            ['Breaking Bad', "Frieren: Beyond Journey's End"],
            $results->media->pluck('title')->all(),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'anilist.co')
            && ! array_key_exists('formatIn', $request['variables'])
            && $request['variables']['formatNotIn'] === ['MOVIE']);
    }

    public function test_an_unfiltered_search_sends_no_null_variables_to_anilist(): void
    {
        $this->fakeHealthyPrimaries();

        $this->service()->search('frieren');

        // AniList membalas 500 untuk variabel list bernilai null.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'anilist.co')
            && ! in_array(null, $request['variables'], true)
            && ! array_key_exists('formatIn', $request['variables'])
            && ! array_key_exists('formatNotIn', $request['variables']));
    }

    public function test_jikan_movies_become_films(): void
    {
        $movie = ['mal_id' => 50594, 'type' => 'Movie', 'title' => 'Suzume no Tojimari', 'members' => 400000];

        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => []]),
            'graphql.anilist.co' => Http::response(['error' => 'unavailable'], 503),
            'api.jikan.moe/v4/anime*' => Http::response(['data' => [$movie, ...$this->jikanPayload()['data']]]),
        ]);

        $results = $this->service()->search('suzume', MediaType::Film);

        $this->assertSame(['Suzume no Tojimari'], $results->media->pluck('title')->all());
        $this->assertSame(MediaType::Film, $results->media->first()->media_type);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'jikan.moe') && $request['type'] === 'movie');
    }

    public function test_jikan_takes_over_when_anilist_is_down(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response($this->tmdbPayload()),
            'graphql.anilist.co' => Http::response(['error' => 'unavailable'], 503),
            'api.jikan.moe/v4/anime*' => Http::response($this->jikanPayload()),
        ]);

        $results = $this->service()->search('frieren');

        // AniList tidak lagi dilaporkan gagal karena Jikan sudah menutup lubangnya.
        $this->assertSame([], $results->failedSources);
        $this->assertSame(['anilist' => 'jikan'], $results->fallbackSources);
        $this->assertTrue($results->usedFallback());

        $anime = $results->media->firstWhere('source', MediaSource::Jikan);

        $this->assertNotNull($anime);
        $this->assertSame(MediaType::Series, $anime->media_type);
        $this->assertSame('52991', $anime->external_id);
        $this->assertContains('Shounen', $anime->genres);
    }

    public function test_jikan_is_left_alone_while_anilist_works(): void
    {
        $this->fakeHealthyPrimaries();

        $this->service()->search('frieren');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'jikan.moe'));
    }

    public function test_the_fallback_is_skipped_when_anilist_simply_has_no_matches(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => []]),
            'graphql.anilist.co' => Http::response(['data' => ['Page' => ['media' => []]]]),
        ]);

        $results = $this->service()->search('zzzzzzzz');

        $this->assertTrue($results->isEmpty());
        $this->assertSame([], $results->failedSources);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'jikan.moe'));
    }

    public function test_a_graphql_error_body_counts_as_a_failure(): void
    {
        Http::fake([
            // AniList membalas 200 tapi isinya error — tetap harus dianggap gagal.
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => []]),
            'graphql.anilist.co' => Http::response(['errors' => [['message' => 'Too Many Requests']]]),
            'api.jikan.moe/v4/anime*' => Http::response($this->jikanPayload()),
        ]);

        $results = $this->service()->search('frieren', MediaType::Series);

        $this->assertSame([], $results->failedSources);
        $this->assertSame(['anilist' => 'jikan'], $results->fallbackSources);
        $this->assertSame(1, $results->media->count());
    }

    public function test_a_failing_provider_does_not_break_the_whole_search(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response($this->tmdbPayload()),
            'graphql.anilist.co' => Http::response(['error' => 'unavailable'], 503),
            'api.jikan.moe/v4/anime*' => Http::response(['error' => 'upstream down'], 504),
        ]);

        $results = $this->service()->search('interstellar');

        // Anime hilang karena sumber utama dan cadangannya sama-sama mati,
        // tapi film & series dari TMDB tetap tampil.
        $this->assertSame(['anilist', 'jikan'], $results->failedSources);
        $this->assertSame([], $results->fallbackSources);
        $this->assertGreaterThan(0, $results->media->count());
    }

    public function test_a_provider_without_credentials_is_reported_as_skipped(): void
    {
        config(['services.tmdb.key' => null]);

        Http::fake([
            'graphql.anilist.co' => Http::response($this->anilistPayload()),
        ]);

        $results = $this->service()->search('frieren');

        $this->assertSame(['tmdb'], $results->skippedSources);
        $this->assertSame(2, $results->media->count());
    }

    public function test_queries_shorter_than_two_characters_never_hit_the_network(): void
    {
        Http::fake();

        $results = $this->service()->search('a');

        $this->assertTrue($results->isEmpty());
        Http::assertNothingSent();
    }

    public function test_an_exact_title_match_is_ranked_first(): void
    {
        $this->fakeHealthyPrimaries();

        $results = $this->service()->search('interstellar');

        $this->assertSame('Interstellar', $results->media->first()->title);
    }

    public function test_a_successful_search_is_served_from_cache_on_repeat(): void
    {
        $this->fakeHealthyPrimaries();

        $this->service()->search('interstellar');
        $this->service()->search('interstellar');

        // Satu request per endpoint saja: genre movie, genre tv,
        // search/multi (id-ID), search/multi (en-US), dan anilist.
        Http::assertSentCount(5);
    }

    public function test_a_failed_search_is_not_cached_for_long(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response($this->tmdbPayload()),
            'graphql.anilist.co' => Http::response(['error' => 'unavailable'], 503),
            'api.jikan.moe/v4/anime*' => Http::response(['error' => 'upstream down'], 504),
        ]);

        $this->service()->search('interstellar');

        $this->travel(31)->seconds();

        $results = $this->service()->search('interstellar');

        // Sumber yang gagal dicoba lagi, bukan dikunci selama 10 menit.
        $this->assertSame(['anilist', 'jikan'], $results->failedSources);
        $this->assertGreaterThan(2, count(Http::recorded(fn ($request) => str_contains($request->url(), 'anilist.co'))));
    }
}
