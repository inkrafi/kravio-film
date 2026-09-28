<?php

namespace Tests\Feature;

use App\Livewire\MediaSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MediaSearchComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @return array<string, mixed>
     */
    private function anilistPayload(): array
    {
        return [
            'data' => ['Page' => ['media' => [
                [
                    'id' => 154587,
                    'title' => ['romaji' => 'Sousou no Frieren', 'english' => null, 'native' => null],
                    'description' => 'Sinopsis.',
                    'coverImage' => ['extraLarge' => 'https://img.anili.st/frieren.jpg', 'large' => null],
                    'bannerImage' => null,
                    'startDate' => ['year' => 2023, 'month' => 9, 'day' => 29],
                    'seasonYear' => 2023,
                    'format' => 'TV',
                    'genres' => ['Fantasy'],
                    'tags' => [],
                    'popularity' => 480000,
                ],
            ]]],
        ];
    }

    /**
     * Http::fake() menumpuk stub dan stub pertama yang cocok menang, jadi tiap
     * test memilih sendiri skenario mana yang dipasang.
     */
    private function fakeResults(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => [['id' => 18, 'name' => 'Drama']]]),
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => [
                [
                    'id' => 157336,
                    'media_type' => 'movie',
                    'title' => 'Interstellar',
                    'overview' => 'Perjalanan menembus lubang cacing.',
                    'poster_path' => '/poster.jpg',
                    'release_date' => '2014-11-05',
                    'genre_ids' => [18],
                    'popularity' => 120.5,
                ],
            ]]),
            'graphql.anilist.co' => Http::response($this->anilistPayload()),
        ]);
    }

    private function fakeNoResults(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => []]),
            'graphql.anilist.co' => Http::response(['data' => ['Page' => ['media' => []]]]),
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('search'))->assertRedirect(route('login'));
    }

    public function test_the_search_page_renders_for_signed_in_users(): void
    {
        $this->fakeResults();

        $this->actingAs(User::factory()->create())
            ->get(route('search'))
            ->assertOk()
            ->assertSeeLivewire(MediaSearch::class)
            ->assertSee('Mulai ketik untuk mencari');
    }

    public function test_typing_a_query_shows_mixed_results(): void
    {
        $this->fakeResults();

        Livewire::actingAs(User::factory()->create())
            ->test(MediaSearch::class)
            ->set('query', 'interstellar')
            ->assertSee('Interstellar')
            ->assertSee('Sousou no Frieren')
            ->assertSee('Film')
            ->assertSee('Series');
    }

    public function test_there_is_no_separate_anime_tab(): void
    {
        $this->fakeResults();

        Livewire::actingAs(User::factory()->create())
            ->test(MediaSearch::class)
            ->assertSet('tabs', [MediaSearch::ALL => 'Semua', 'film' => 'Film', 'series' => 'Series'])
            ->call('selectType', 'anime')
            ->assertSet('type', MediaSearch::ALL);
    }

    public function test_the_type_filter_narrows_the_results(): void
    {
        $this->fakeResults();

        Livewire::actingAs(User::factory()->create())
            ->test(MediaSearch::class)
            ->set('query', 'interstellar')
            ->call('selectType', 'series')
            ->assertSet('type', 'series')
            ->assertSee('Sousou no Frieren')
            ->assertDontSee('Interstellar');
    }

    public function test_an_unknown_filter_value_falls_back_to_all(): void
    {
        $this->fakeResults();

        Livewire::actingAs(User::factory()->create())
            ->test(MediaSearch::class)
            ->call('selectType', 'dokumenter')
            ->assertSet('type', MediaSearch::ALL);
    }

    public function test_a_query_without_matches_shows_the_empty_state(): void
    {
        $this->fakeNoResults();

        Livewire::actingAs(User::factory()->create())
            ->test(MediaSearch::class)
            ->set('query', 'zzzzzzzz')
            ->assertSee('Tidak ditemukan hasil');
    }

    public function test_clearing_resets_query_and_filter(): void
    {
        $this->fakeResults();

        Livewire::actingAs(User::factory()->create())
            ->test(MediaSearch::class)
            ->set('query', 'interstellar')
            ->call('selectType', 'film')
            ->call('clear')
            ->assertSet('query', '')
            ->assertSet('type', MediaSearch::ALL)
            ->assertSee('Mulai ketik untuk mencari');
    }

    public function test_the_query_is_kept_in_the_url(): void
    {
        $this->fakeResults();

        Livewire::actingAs(User::factory()->create())
            ->withQueryParams(['q' => 'interstellar', 'tipe' => 'film'])
            ->test(MediaSearch::class)
            ->assertSet('query', 'interstellar')
            ->assertSet('type', 'film')
            ->assertSee('Interstellar');
    }

    public function test_it_tells_the_user_when_results_came_from_the_fallback_source(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => []]),
            'graphql.anilist.co' => Http::response(['error' => 'down'], 503),
            'api.jikan.moe/v4/anime*' => Http::response(['data' => [
                [
                    'mal_id' => 52991,
                    'title' => 'Sousou no Frieren',
                    'images' => ['jpg' => ['large_image_url' => 'https://cdn.myanimelist.net/frieren.jpg']],
                    'aired' => ['from' => '2023-09-29T00:00:00+00:00'],
                    'year' => 2023,
                    'genres' => [['name' => 'Adventure']],
                    'members' => 900000,
                ],
            ]]),
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(MediaSearch::class)
            ->set('query', 'frieren')
            ->assertSee('Sousou no Frieren')
            ->assertSee('AniList sedang bermasalah')
            ->assertSee('diambil dari')
            ->assertSee('MyAnimeList')
            ->assertDontSee('tidak bisa dihubungi');
    }

    public function test_it_warns_when_every_anime_source_is_down(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => []]),
            'graphql.anilist.co' => Http::response(['error' => 'down'], 503),
            'api.jikan.moe/v4/anime*' => Http::response(['status' => 504], 504),
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(MediaSearch::class)
            ->set('query', 'frieren')
            ->assertSee('tidak bisa dihubungi')
            ->assertSee('AniList')
            ->assertSee('MyAnimeList');
    }
}
