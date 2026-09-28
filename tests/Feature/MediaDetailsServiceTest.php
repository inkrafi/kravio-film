<?php

namespace Tests\Feature;

use App\Enums\MediaType;
use App\Livewire\MediaDetail;
use App\Models\MediaCache;
use App\Models\User;
use App\Services\Media\MediaDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MediaDetailsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tmdb.key' => 'tmdb-test-key',
            'services.omdb.key' => 'omdb-test-key',
        ]);

        Http::preventStrayRequests();
    }

    private function service(): MediaDetailsService
    {
        return app(MediaDetailsService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function tmdbMovie(): array
    {
        return [
            'id' => 157336,
            'vote_average' => 8.456,
            'vote_count' => 36000,
            'external_ids' => ['imdb_id' => 'tt0816692'],
            'credits' => [
                'cast' => [
                    ['id' => 1813, 'name' => 'Anne Hathaway', 'character' => 'Brand', 'order' => 1, 'profile_path' => '/anne.jpg'],
                    ['id' => 10297, 'name' => 'Matthew McConaughey', 'character' => 'Cooper', 'order' => 0, 'profile_path' => '/mm.jpg'],
                ],
                'crew' => [
                    ['name' => 'Christopher Nolan', 'job' => 'Director', 'profile_path' => '/nolan.jpg'],
                    ['name' => 'Christopher Nolan', 'job' => 'Writer', 'profile_path' => '/nolan.jpg'],
                    ['name' => 'Hans Zimmer', 'job' => 'Original Music Composer', 'profile_path' => null],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tmdbTv(): array
    {
        return [
            'id' => 209867,
            'vote_average' => 8.8,
            'vote_count' => 500,
            'external_ids' => ['imdb_id' => 'tt22248376'],
            'created_by' => [],
            'aggregate_credits' => [
                'cast' => [
                    ['name' => 'Atsumi Tanezaki', 'order' => 0, 'profile_path' => null, 'roles' => [['character' => 'Frieren']]],
                ],
                'crew' => [
                    ['name' => 'Keiichirou Saitou', 'profile_path' => null, 'jobs' => [['job' => 'Director', 'episode_count' => 28]]],
                    ['name' => 'Tomohiro Suzuki', 'profile_path' => null, 'jobs' => [['job' => 'Series Composition', 'episode_count' => 28]]],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function omdbPayload(array $overrides = []): array
    {
        return $overrides + [
            'Response' => 'True',
            'imdbID' => 'tt0816692',
            'imdbRating' => '8.7',
            'imdbVotes' => '2,345,678',
            'Director' => 'Christopher Nolan',
            'Actors' => 'Matthew McConaughey, Anne Hathaway, Jessica Chastain',
            'Ratings' => [
                ['Source' => 'Internet Movie Database', 'Value' => '8.7/10'],
                ['Source' => 'Rotten Tomatoes', 'Value' => '73%'],
            ],
        ];
    }

    public function test_tmdb_films_get_ratings_cast_and_director(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/157336*' => Http::response($this->tmdbMovie()),
            'www.omdbapi.com/*' => Http::response($this->omdbPayload()),
        ]);

        $media = MediaCache::factory()->film()->create(['external_id' => '157336']);

        $this->service()->refresh($media);
        $media->refresh();

        $this->assertSame(8.5, $media->tmdb_rating);
        $this->assertSame(36000, $media->tmdb_votes);
        $this->assertSame('tt0816692', $media->imdb_id);
        $this->assertSame(8.7, $media->imdb_rating);
        $this->assertSame(2345678, $media->imdb_votes);
        $this->assertSame(73, $media->rotten_tomatoes_score);
        $this->assertNotNull($media->details_synced_at);

        $this->assertSame(['Christopher Nolan'], array_column($media->people('directors'), 'name'));
        $this->assertSame(
            [
                ['id' => 10297, 'name' => 'Matthew McConaughey', 'role' => 'Cooper', 'photo_url' => 'https://image.tmdb.org/t/p/w185/mm.jpg'],
                ['id' => 1813, 'name' => 'Anne Hathaway', 'role' => 'Brand', 'photo_url' => 'https://image.tmdb.org/t/p/w185/anne.jpg'],
            ],
            $media->people('cast'),
        );

        // Satu request TMDB untuk semuanya, lalu OMDb lewat IMDb ID.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'movie/157336')
            && $request['append_to_response'] === 'credits,external_ids');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'omdbapi.com') && $request['i'] === 'tt0816692');
    }

    public function test_series_list_creators_and_their_busiest_directors(): void
    {
        Http::fake([
            'api.themoviedb.org/3/tv/1396*' => Http::response([
                'vote_average' => 8.9,
                'vote_count' => 15000,
                'external_ids' => ['imdb_id' => 'tt0903747'],
                'created_by' => [['name' => 'Vince Gilligan', 'profile_path' => '/vg.jpg']],
                'aggregate_credits' => [
                    'cast' => [['name' => 'Bryan Cranston', 'order' => 0, 'profile_path' => null, 'roles' => [['character' => 'Walter White']]]],
                    'crew' => [
                        ['name' => 'Michelle MacLaren', 'profile_path' => null, 'jobs' => [['job' => 'Director', 'episode_count' => 11]]],
                        ['name' => 'Vince Gilligan', 'profile_path' => null, 'jobs' => [['job' => 'Director', 'episode_count' => 5], ['job' => 'Writer', 'episode_count' => 13]]],
                        ['name' => 'Dave Porter', 'profile_path' => null, 'jobs' => [['job' => 'Original Music Composer', 'episode_count' => 62]]],
                    ],
                ],
            ]),
            // Rotten Tomatoes sering tidak punya skor untuk series — dibiarkan kosong.
            'www.omdbapi.com/*' => Http::response($this->omdbPayload(['imdbID' => 'tt0903747', 'Ratings' => []])),
        ]);

        $media = MediaCache::factory()->series()->create(['external_id' => '1396']);

        $this->service()->refresh($media);
        $media->refresh();

        $this->assertSame(['Vince Gilligan'], array_column($media->people('creators'), 'name'));
        $this->assertSame(['Michelle MacLaren', 'Vince Gilligan'], array_column($media->people('directors'), 'name'));
        $this->assertSame('11 episode', $media->people('directors')[0]['role']);
        $this->assertSame('Walter White', $media->people('cast')[0]['role']);
        $this->assertNull($media->rotten_tomatoes_score);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'tv/1396')
            && $request['append_to_response'] === 'aggregate_credits,external_ids');
    }

    public function test_anime_is_linked_to_tmdb_through_its_imdb_id(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response($this->omdbPayload(['imdbID' => 'tt22248376', 'imdbRating' => '8.9'])),
            'api.themoviedb.org/3/find/tt22248376*' => Http::response(['movie_results' => [], 'tv_results' => [['id' => 209867]]]),
            'api.themoviedb.org/3/tv/209867*' => Http::response($this->tmdbTv()),
        ]);

        $media = MediaCache::factory()->anime()->create(['title' => "Frieren: Beyond Journey's End", 'year' => 2023]);

        $this->service()->refresh($media);
        $media->refresh();

        $this->assertSame('tt22248376', $media->imdb_id);
        $this->assertSame(8.9, $media->imdb_rating);
        $this->assertSame(8.8, $media->tmdb_rating);
        $this->assertSame(['Keiichirou Saitou'], array_column($media->people('directors'), 'name'));
        $this->assertSame('Frieren', $media->people('cast')[0]['role']);

        Http::assertSent(fn ($request) => ($request['t'] ?? null) === "Frieren: Beyond Journey's End"
            && $request['y'] === 2023
            && $request['type'] === 'series');
        // OMDb cukup ditanya sekali: hasil pencarian judul sudah berisi rating.
        $this->assertCount(1, Http::recorded(fn ($request) => str_contains($request->url(), 'omdbapi.com')));
    }

    public function test_anime_movies_listed_as_tv_on_tmdb_still_get_linked(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response($this->omdbPayload(['imdbID' => 'tt22248376'])),
            'api.themoviedb.org/3/find/*' => Http::response(['movie_results' => [], 'tv_results' => [['id' => 209867]]]),
            'api.themoviedb.org/3/tv/209867*' => Http::response($this->tmdbTv()),
        ]);

        $media = MediaCache::factory()->anime(MediaType::Film)->create();

        $this->service()->refresh($media);

        $this->assertSame(8.8, $media->fresh()->tmdb_rating);
    }

    public function test_omdb_names_are_used_when_tmdb_does_not_know_the_anime(): void
    {
        Http::fake([
            'www.omdbapi.com/*' => Http::response($this->omdbPayload([
                'imdbID' => 'tt1234567',
                'Director' => 'N/A',
                'Actors' => 'Kana Hanazawa, Mamoru Miyano',
            ])),
            'api.themoviedb.org/3/find/*' => Http::response(['movie_results' => [], 'tv_results' => []]),
        ]);

        $media = MediaCache::factory()->anime()->create();

        $this->service()->refresh($media);
        $media->refresh();

        $this->assertNull($media->tmdb_rating);
        $this->assertSame([], $media->people('directors'));
        $this->assertSame(
            [
                ['id' => null, 'name' => 'Kana Hanazawa', 'role' => null, 'photo_url' => null],
                ['id' => null, 'name' => 'Mamoru Miyano', 'role' => null, 'photo_url' => null],
            ],
            $media->people('cast'),
        );
    }

    public function test_tmdb_details_still_load_without_an_omdb_key(): void
    {
        config(['services.omdb.key' => null]);

        Http::fake(['api.themoviedb.org/3/movie/*' => Http::response($this->tmdbMovie())]);

        $media = MediaCache::factory()->film()->create(['external_id' => '157336']);

        $this->service()->refresh($media);
        $media->refresh();

        $this->assertSame(8.5, $media->tmdb_rating);
        $this->assertNull($media->imdb_rating);
        $this->assertCount(2, $media->people('cast'));
        $this->assertNotNull($media->details_synced_at);
    }

    public function test_titles_without_votes_have_no_tmdb_rating(): void
    {
        config(['services.omdb.key' => null]);

        Http::fake(['api.themoviedb.org/3/movie/*' => Http::response(['vote_average' => 0, 'vote_count' => 0] + $this->tmdbMovie())]);

        $media = MediaCache::factory()->film()->create();

        $this->service()->refresh($media);

        $this->assertNull($media->fresh()->tmdb_rating);
    }

    public function test_missing_omdb_values_are_stored_as_null(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/*' => Http::response($this->tmdbMovie()),
            'www.omdbapi.com/*' => Http::response($this->omdbPayload(['imdbRating' => 'N/A', 'imdbVotes' => 'N/A', 'Ratings' => []])),
        ]);

        $media = MediaCache::factory()->film()->create();

        $this->service()->refresh($media);

        $this->assertNull($media->fresh()->imdb_rating);
        $this->assertNull($media->fresh()->imdb_votes);
    }

    public function test_an_anime_nobody_knows_is_not_looked_up_again_this_week(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Response' => 'False', 'Error' => 'Movie not found!'])]);

        $media = MediaCache::factory()->anime()->create();

        $this->service()->refresh($media);
        $this->service()->refresh($media->fresh());

        Http::assertSentCount(1);
        $this->assertNotNull($media->fresh()->details_synced_at);
    }

    public function test_stale_details_are_refreshed(): void
    {
        config(['services.omdb.key' => null]);

        Http::fake(['api.themoviedb.org/3/movie/*' => Http::response($this->tmdbMovie())]);

        $media = MediaCache::factory()->film()->create([
            'tmdb_rating' => 7.0,
            'details_synced_at' => now()->subDays(MediaDetailsService::TTL_DAYS + 1),
        ]);

        $this->service()->refresh($media);

        $this->assertSame(8.5, $media->fresh()->tmdb_rating);
    }

    public function test_an_outage_keeps_what_was_fetched_and_retries_next_visit(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/*' => Http::response($this->tmdbMovie()),
            'www.omdbapi.com/*' => Http::response(['Error' => 'down'], 503),
        ]);

        $media = MediaCache::factory()->film()->create();

        $this->service()->refresh($media);
        $media->refresh();

        $this->assertSame(8.5, $media->tmdb_rating);
        $this->assertNull($media->details_synced_at);
    }

    public function test_nothing_is_requested_without_any_key(): void
    {
        config(['services.tmdb.key' => null, 'services.omdb.key' => null]);
        Http::fake();

        $this->service()->refresh(MediaCache::factory()->film()->create());

        Http::assertNothingSent();
    }

    public function test_the_detail_page_loads_ratings_cast_and_director_after_rendering(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/*' => Http::response($this->tmdbMovie()),
            'www.omdbapi.com/*' => Http::response($this->omdbPayload()),
        ]);

        $media = MediaCache::factory()->film()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(MediaDetail::class, ['media' => $media])
            ->assertSee('Memuat rating, pemain, dan sutradara')
            ->assertDontSee('Christopher Nolan')
            ->call('loadDetails')
            ->assertSee('TMDB')
            ->assertSee('8.5')
            ->assertSee('IMDb')
            ->assertSee('8.7')
            ->assertSee('73%')
            ->assertSee('Sutradara')
            ->assertSee('Christopher Nolan')
            ->assertSee('Pemain')
            ->assertSee('Matthew McConaughey')
            ->assertSee('Cooper')
            ->assertSee('https://www.imdb.com/title/tt0816692/');
    }

    public function test_the_detail_page_does_not_refetch_fresh_details(): void
    {
        Http::fake();

        $media = MediaCache::factory()->series()->create([
            'tmdb_rating' => 7.9,
            'credits' => [
                'directors' => [],
                'creators' => [['id' => 66633, 'name' => 'Vince Gilligan', 'role' => null, 'photo_url' => null]],
                'cast' => [],
            ],
            'details_synced_at' => now(),
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(MediaDetail::class, ['media' => $media])
            ->assertDontSee('Memuat rating')
            ->assertSee('7.9')
            ->assertSee('Kreator')
            ->assertSee('Vince Gilligan')
            ->assertDontSee('Pemain');

        Http::assertNothingSent();
    }

    public function test_cards_do_not_show_ratings(): void
    {
        $media = MediaCache::factory()->film()->make([
            'tmdb_rating' => 8.5,
            'imdb_rating' => 8.7,
            'rotten_tomatoes_score' => 42,
        ]);

        $this->blade('<x-media-card :media="$media" />', ['media' => $media])
            ->assertDontSee('8.5')
            ->assertDontSee('8.7')
            ->assertDontSee('42%');
    }
}
