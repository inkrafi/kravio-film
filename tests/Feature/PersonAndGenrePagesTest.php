<?php

namespace Tests\Feature;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Livewire\GenreBrowse;
use App\Livewire\MediaDetail;
use App\Livewire\PersonDetail;
use App\Models\MediaCache;
use App\Models\User;
use App\Services\Media\Dto\MediaResult;
use App\Services\Media\MediaDetailsService;
use App\Services\Media\MediaSearchService;
use App\Services\Media\PersonService;
use App\Support\GenreNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PersonAndGenrePagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'tmdb-test-key']);

        Http::preventStrayRequests();

        $this->user = User::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function nolan(): array
    {
        return [
            'id' => 525,
            'name' => 'Christopher Nolan',
            'also_known_as' => ['Кристофер Нолан'],
            'profile_path' => '/nolan.jpg',
            'known_for_department' => 'Directing',
            'birthday' => '1970-07-30',
            'deathday' => null,
            'place_of_birth' => 'London, England, UK',
            'biography' => 'Sutradara asal Inggris.',
            'combined_credits' => [
                'cast' => [
                    ['id' => 1, 'media_type' => 'movie', 'title' => 'Cameo Film', 'character' => 'Self', 'release_date' => '2005-01-01', 'genre_ids' => []],
                ],
                'crew' => [
                    ['id' => 157336, 'media_type' => 'movie', 'title' => 'Interstellar', 'job' => 'Director', 'release_date' => '2014-11-05', 'genre_ids' => [878]],
                    ['id' => 157336, 'media_type' => 'movie', 'title' => 'Interstellar', 'job' => 'Writer', 'release_date' => '2014-11-05', 'genre_ids' => [878]],
                    ['id' => 872585, 'media_type' => 'movie', 'title' => 'Oppenheimer', 'job' => 'Director', 'release_date' => '2023-07-19', 'genre_ids' => [18]],
                    ['id' => 99, 'media_type' => 'movie', 'title' => 'Hanya Produser', 'job' => 'Producer', 'release_date' => '2010-01-01', 'genre_ids' => []],
                ],
            ],
        ];
    }

    private function fakeTmdb(array $person, array $extra = []): void
    {
        Http::fake($extra + [
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => [['id' => 878, 'name' => 'Cerita Fiksi'], ['id' => 18, 'name' => 'Drama']]]),
            'api.themoviedb.org/3/person/*' => Http::response($person),
        ]);
    }

    public function test_the_person_page_lists_directed_and_acted_titles(): void
    {
        $this->fakeTmdb($this->nolan());

        Livewire::actingAs($this->user)
            ->test(PersonDetail::class, ['person' => '525-christopher-nolan'])
            ->assertSee('Christopher Nolan')
            ->assertSee('Penyutradaraan')
            ->assertSee('30 Juli 1970')
            ->assertSee('Sutradara asal Inggris.')
            ->assertSeeInOrder(['Sebagai Sutradara / Kreator', 'Oppenheimer', 'Interstellar', 'Sebagai Pemain', 'Cameo Film', 'sebagai dirinya sendiri'])
            ->assertDontSee('Hanya Produser');

        // Setiap judul tersimpan dan bisa dibuka di halaman detail.
        $this->assertSame(url('/film/interstellar'), MediaCache::where('external_id', '157336')->sole()->url());
        $this->assertSame(1, MediaCache::where('external_id', '157336')->count());
    }

    public function test_the_person_is_cached_for_a_day(): void
    {
        $this->fakeTmdb($this->nolan());

        Livewire::actingAs($this->user)->test(PersonDetail::class, ['person' => '525']);
        $firstVisit = count(Http::recorded(fn ($request) => str_contains($request->url(), 'person/525')));

        Livewire::actingAs($this->user)->test(PersonDetail::class, ['person' => '525']);

        // Kunjungan pertama: versi id-ID + en-US; kunjungan kedua dari cache.
        $this->assertSame(2, $firstVisit);
        $this->assertCount(2, Http::recorded(fn ($request) => str_contains($request->url(), 'person/525')));
    }

    public function test_filmography_titles_get_their_official_english_title(): void
    {
        $korean = ['id' => 2, 'name' => '이선균', 'also_known_as' => [], 'combined_credits' => ['cast' => [
            ['id' => 701, 'media_type' => 'movie', 'title' => '탈출: 프로젝트 사일런스', 'original_title' => '탈출: 프로젝트 사일런스', 'original_language' => 'ko', 'character' => 'Cha Jung-won', 'release_date' => '2024-07-12', 'genre_ids' => []],
        ], 'crew' => []]] + $this->nolan();

        $this->fakeTmdb($korean, [
            'api.themoviedb.org/3/person/2?*language=en-US*' => Http::response(['biography' => '', 'combined_credits' => ['cast' => [
                ['id' => 701, 'media_type' => 'movie', 'title' => 'Project Silence'],
            ], 'crew' => []]]),
        ]);

        Livewire::actingAs($this->user)->test(PersonDetail::class, ['person' => '2'])->assertSee('Project Silence');

        $this->assertSame('Project Silence', MediaCache::where('external_id', '701')->sole()->title_latin);
    }

    public function test_an_official_title_replaces_an_earlier_romanization_but_not_the_reverse(): void
    {
        $media = MediaCache::factory()->film()->create(['external_id' => '701', 'title' => '탈출', 'title_latin' => 'Talchul']);
        $search = app(MediaSearchService::class);
        $result = fn (?string $latin) => new MediaResult(
            source: MediaSource::Tmdb, mediaType: MediaType::Film, externalId: '701',
            title: '탈출', raw: ['original_language' => 'ko'], titleLatin: $latin,
        );

        $search->remember([$result('Project Silence')]);
        $this->assertSame('Project Silence', $media->fresh()->title_latin);

        // Hasil tanpa judul en-US tidak menghapus atau menurunkan kualitasnya.
        $search->remember([$result(null)]);
        $this->assertSame('Project Silence', $media->fresh()->title_latin);
    }

    public function test_the_person_page_reuses_the_spelling_gemini_gave_on_a_detail_page(): void
    {
        MediaCache::factory()->series()->create([
            'credits' => ['directors' => [], 'creators' => [], 'cast' => [
                ['id' => 1, 'name' => '이선균', 'role' => null, 'photo_url' => null, 'name_latin' => 'Lee Sun-kyun'],
            ]],
            'localized_hash' => sha1('x'),
            'details_synced_at' => now(),
        ]);

        $this->fakeTmdb(['id' => 1, 'name' => '이선균', 'also_known_as' => ['Lee Seon-gyun ', 'Lee Sun Gyun']] + $this->nolan());

        Livewire::actingAs($this->user)->test(PersonDetail::class, ['person' => '1'])
            ->assertSee('Lee Sun-kyun')
            ->assertDontSee('Lee Seon-gyun');
    }

    public function test_the_person_page_uses_the_spelling_from_the_clicked_link(): void
    {
        $this->fakeTmdb(['id' => 1, 'name' => '이선균', 'also_known_as' => ['Lee Seon-gyun', 'Lee Sun-kyun']] + $this->nolan());

        Livewire::actingAs($this->user)->test(PersonDetail::class, ['person' => '1-lee-sun-kyun'])->assertSee('Lee Sun-kyun')->assertDontSee('Lee Seon-gyun');
        Livewire::actingAs($this->user)->test(PersonDetail::class, ['person' => '1'])->assertSee('Lee Seon-gyun');
    }

    public function test_non_latin_names_get_a_latin_spelling(): void
    {
        $this->fakeTmdb(['id' => 1, 'name' => '이선균', 'also_known_as' => ['イ・ソンギュン', 'Lee Sun-kyun', 'Lee Seon-gyun'], 'biography' => 'Aktor Korea.'] + $this->nolan());

        Livewire::actingAs($this->user)
            ->test(PersonDetail::class, ['person' => '1'])
            ->assertSee('Lee Sun-kyun')
            ->assertSee('이선균');
    }

    public function test_an_english_biography_is_translated_after_rendering(): void
    {
        config(['services.gemini.key' => 'gemini-test-key']);

        $this->fakeTmdb(
            ['biography' => ''] + $this->nolan(),
            [
                'api.themoviedb.org/3/person/525?*language=en-US*' => Http::response(['biography' => 'British-American filmmaker.']),
                'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['text' => 'Pembuat film Inggris-Amerika.'])]]],
                ]]]),
            ],
        );

        Livewire::actingAs($this->user)
            ->test(PersonDetail::class, ['person' => '525'])
            ->assertSee('Menerjemahkan biografi')
            ->call('translateBiography')
            ->assertSee('Pembuat film Inggris-Amerika.')
            ->assertSee('Lihat teks asli');
    }

    public function test_unknown_people_and_bad_ids_return_404(): void
    {
        Http::fake(['api.themoviedb.org/3/person/*' => Http::response(['status_message' => 'not found'], 404)]);

        $this->actingAs($this->user)->get('/orang/999999999-siapa')->assertNotFound();
        $this->actingAs($this->user)->get('/orang/bukan-angka')->assertNotFound();
    }

    public function test_person_urls_use_a_latin_slug(): void
    {
        $this->assertSame(url('/orang/525-christopher-nolan'), PersonService::url(525, 'Christopher Nolan'));
        $this->assertSame(url('/orang/7-naui-ajeossi'), PersonService::url(7, '나의 아저씨'));
    }

    public function test_the_detail_page_links_people_and_genres(): void
    {
        $media = MediaCache::factory()->film()->create([
            'genres' => ['Drama', 'Cerita Fiksi'],
            'credits' => [
                'directors' => [['id' => 525, 'name' => 'Christopher Nolan', 'role' => null, 'photo_url' => null]],
                'creators' => [],
                'cast' => [
                    ['id' => 10297, 'name' => 'Matthew McConaughey', 'role' => 'Cooper', 'photo_url' => null],
                    ['id' => null, 'name' => 'Tanpa ID', 'role' => null, 'photo_url' => null],
                ],
            ],
            'watch_providers' => ['region' => 'ID', 'link' => null, 'providers' => []],
            'details_synced_at' => now(),
        ]);

        Livewire::actingAs($this->user)
            ->test(MediaDetail::class, ['media' => $media])
            ->assertSeeHtml('href="'.url('/orang/525-christopher-nolan').'"')
            ->assertSeeHtml('href="'.url('/orang/10297-matthew-mcconaughey').'"')
            ->assertSeeHtml('href="'.route('genre.show', ['slug' => 'fiksi-ilmiah', 'tipe' => 'film']).'"')
            ->assertSeeHtml('href="'.route('genre.show', ['slug' => 'drama', 'tipe' => 'film']).'"')
            ->assertSee('Tanpa ID');
    }

    public function test_credits_without_person_ids_are_refetched_once(): void
    {
        $media = MediaCache::factory()->film()->create([
            'credits' => ['directors' => [['name' => 'Lama', 'role' => null, 'photo_url' => null]], 'creators' => [], 'cast' => []],
            'details_synced_at' => now(),
        ]);

        $this->assertTrue(app(MediaDetailsService::class)->isStale($media));
    }

    public function test_every_displayed_genre_has_a_page(): void
    {
        $all = GenreNormalizer::normalize([
            'Action', 'Adventure', 'Animation', 'Comedy', 'Crime', 'Documentary', 'Drama', 'Family', 'Fantasy',
            'Science Fiction', 'History', 'Horror', 'Music', 'Mystery', 'Romance', 'TV Movie', 'Thriller', 'War',
            'War & Politics', 'Western', 'Reality', 'Kids', 'News', 'Talk', 'Soap', 'Psychological',
            'Slice of Life', 'Sports', 'Supernatural', 'Mecha', 'Mahou Shoujo',
        ]);

        foreach ($all as $genre) {
            $this->assertNotNull(GenreNormalizer::findBySlug(GenreNormalizer::slug($genre)), "Genre {$genre} tidak punya halaman.");
        }
    }

    public function test_the_genre_page_mixes_tmdb_titles_and_anime(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => [['id' => 28, 'name' => 'Aksi']]]),
            'api.themoviedb.org/3/discover/movie*' => Http::response([
                'total_pages' => 3,
                'results' => [
                    ['id' => 1, 'title' => 'John Wick', 'release_date' => '2014-10-22', 'genre_ids' => [28]],
                    ['id' => 2, 'title' => 'Mad Max: Fury Road', 'release_date' => '2015-05-13', 'genre_ids' => [28]],
                ],
            ]),
            'graphql.anilist.co' => Http::response(['data' => ['Page' => [
                'pageInfo' => ['hasNextPage' => false],
                'media' => [['id' => 3, 'title' => ['romaji' => 'Jujutsu Kaisen 0', 'english' => null, 'native' => '呪術廻戦 0'], 'format' => 'MOVIE', 'genres' => ['Action']]],
            ]]]),
        ]);

        Livewire::actingAs($this->user)
            ->test(GenreBrowse::class, ['slug' => 'aksi'])
            ->assertSee('Aksi')
            ->assertSeeInOrder(['John Wick', 'Mad Max: Fury Road', 'Jujutsu Kaisen 0'])
            ->assertSee('Muat lebih banyak');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'discover/movie') && $request['with_genres'] === 28);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'anilist.co')
            && $request['variables']['genre'] === 'Action'
            && $request['variables']['formatIn'] === ['MOVIE']);
    }

    public function test_the_series_tab_uses_the_tv_genre_and_non_movie_anime(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/discover/tv*' => Http::response(['total_pages' => 1, 'results' => [['id' => 5, 'name' => 'Arcane', 'genre_ids' => []]]]),
            'graphql.anilist.co' => Http::response(['data' => ['Page' => ['pageInfo' => ['hasNextPage' => false], 'media' => []]]]),
        ]);

        Livewire::actingAs($this->user)
            ->test(GenreBrowse::class, ['slug' => 'aksi'])
            ->call('selectType', 'series')
            ->assertSet('type', 'series')
            ->assertSee('Arcane')
            ->assertDontSee('Muat lebih banyak');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'discover/tv') && $request['with_genres'] === 10759);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'anilist.co') && ($request['variables']['formatNotIn'] ?? null) === ['MOVIE']);
    }

    public function test_load_more_fetches_the_next_page(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/discover/movie*page=1*' => Http::response(['total_pages' => 2, 'results' => [['id' => 1, 'title' => 'Halaman Satu']]]),
            'api.themoviedb.org/3/discover/movie*page=2*' => Http::response(['total_pages' => 2, 'results' => [['id' => 2, 'title' => 'Halaman Dua']]]),
        ]);

        Livewire::actingAs($this->user)
            ->test(GenreBrowse::class, ['slug' => 'kejahatan'])
            ->assertDontSee('Halaman Dua')
            ->call('loadMore')
            ->assertSeeInOrder(['Halaman Satu', 'Halaman Dua'])
            ->assertDontSee('Muat lebih banyak');
    }

    public function test_series_only_genres_have_no_film_tab(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/discover/tv*' => Http::response(['total_pages' => 1, 'results' => []]),
        ]);

        Livewire::actingAs($this->user)
            ->test(GenreBrowse::class, ['slug' => 'talk-show'])
            ->assertSet('type', 'series')
            ->assertDontSee('role="tab"', escape: false);
    }

    public function test_an_unknown_genre_returns_404(): void
    {
        $this->actingAs($this->user)->get('/genre/tidak-ada')->assertNotFound();
    }

    public function test_a_failing_source_is_reported_and_not_cached(): void
    {
        Http::fake([
            'api.themoviedb.org/3/genre/*' => Http::response(['genres' => []]),
            'api.themoviedb.org/3/discover/*' => Http::response(['status_message' => 'down'], 503),
            'graphql.anilist.co' => Http::response(['data' => ['Page' => ['pageInfo' => ['hasNextPage' => false], 'media' => []]]]),
        ]);

        Livewire::actingAs($this->user)
            ->test(GenreBrowse::class, ['slug' => 'aksi'])
            ->assertSee('Sebagian sumber sedang tidak bisa dihubungi')
            ->assertSee('TMDB');

        $this->assertNull(cache()->get('genre:aksi:film:1:'.config('services.tmdb.language')));
    }
}
