<?php

namespace Tests\Feature;

use App\Enums\WatchStatus;
use App\Livewire\MediaDetail;
use App\Models\Favorite;
use App\Models\Friendship;
use App\Models\MediaCache;
use App\Models\Review;
use App\Models\User;
use App\Models\WatchEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Tests\TestCase;

class MediaDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MediaCache $media;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->media = MediaCache::factory()->create([
            'title' => 'Interstellar',
            'synopsis' => 'Perjalanan menembus lubang cacing.',
            'genres' => ['Petualangan', 'Drama'],
        ]);
    }

    private function detail()
    {
        return Livewire::actingAs($this->user)->test(MediaDetail::class, ['media' => $this->media]);
    }

    public function test_films_and_series_live_under_their_own_path(): void
    {
        $series = MediaCache::factory()->series()->create(['title' => 'Breaking Bad']);

        $this->assertSame(url('/film/interstellar'), $this->media->url());
        $this->assertSame(url('/series/breaking-bad'), $series->url());

        $this->actingAs($this->user)->get('/series/breaking-bad')->assertOk()->assertSee('Breaking Bad');
    }

    /**
     * Tiap request Livewire (wire:init, klik tombol) menjalankan ulang binding
     * route halaman asal di atas request palsu, sementara request() global
     * adalah POST /livewire/update. Binding tidak boleh bergantung pada request() global.
     */
    public function test_the_route_binding_works_outside_the_page_request(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/film/interstellar'));

        app('router')->substituteBindings($route);
        app('router')->substituteImplicitBindings($route);

        $this->assertTrue($this->media->is($route->parameter('media')));
    }

    public function test_the_breadcrumb_leads_back_to_search_and_the_type_tab(): void
    {
        $series = MediaCache::factory()->series()->create(['title' => 'Breaking Bad']);

        $this->actingAs($this->user)
            ->get($series->url())
            ->assertOk()
            ->assertSeeInOrder([
                'aria-label="Breadcrumb"',
                'href="'.route('search').'"', 'Cari',
                'href="'.route('search', ['tipe' => 'series']).'"', 'Series',
                // Judul tidak diulang di breadcrumb; ia tampil besar tepat di bawahnya.
                '<h1', 'Breaking Bad',
            ], escape: false);
    }

    public function test_a_film_is_not_reachable_under_the_series_path(): void
    {
        $this->actingAs($this->user)->get('/series/interstellar')->assertNotFound();
    }

    public function test_a_film_and_a_series_may_share_a_slug(): void
    {
        $series = MediaCache::factory()->series()->create(['title' => 'Interstellar']);

        $this->assertSame('interstellar', $series->slug);

        $this->actingAs($this->user)->get('/film/interstellar')->assertOk()->assertSee('Perjalanan menembus lubang cacing.');
        $this->actingAs($this->user)->get('/series/interstellar')->assertOk()->assertSee($series->synopsis);
    }

    public function test_duplicate_titles_get_the_year_then_a_counter(): void
    {
        $remake = MediaCache::factory()->film()->create(['title' => 'Dune', 'year' => 2021]);
        $original = MediaCache::factory()->film()->create(['title' => 'Dune', 'year' => 1984]);
        $sameYear = MediaCache::factory()->film()->create(['title' => 'Dune', 'year' => 1984]);

        $this->assertSame('dune', $remake->slug);
        $this->assertSame('dune-1984', $original->slug);
        $this->assertSame('dune-1984-2', $sameYear->slug);
    }

    public function test_the_slug_stays_when_the_title_changes(): void
    {
        $this->media->update(['title' => 'Interstellar (Remastered)']);

        $this->assertSame('interstellar', $this->media->fresh()->slug);
    }

    public function test_titles_without_latin_letters_fall_back_to_the_source_key(): void
    {
        $media = MediaCache::factory()->anime()->create(['title' => '!!!', 'original_title' => null, 'external_id' => '999']);

        $this->assertSame('anilist-series-999', $media->slug);
    }

    public function test_old_media_links_redirect_permanently(): void
    {
        $this->actingAs($this->user)
            ->get('/media/tmdb-film-'.$this->media->external_id)
            ->assertRedirect('/film/interstellar')
            ->assertStatus(301);
    }

    public function test_links_from_before_anime_was_merged_still_redirect(): void
    {
        MediaCache::factory()->anime()->create(['external_id' => '154587', 'title' => 'Frieren']);

        $this->actingAs($this->user)
            ->get('/media/anilist-anime-154587')
            ->assertRedirect('/series/frieren');
    }

    public function test_guests_cannot_open_a_detail_page(): void
    {
        $this->get($this->media->url())->assertRedirect(route('login'));
    }

    public function test_the_page_is_reachable_by_slug_and_shows_the_media(): void
    {
        $this->actingAs($this->user)
            ->get('/film/interstellar')
            ->assertOk()
            ->assertSee('Interstellar')
            ->assertSee('Perjalanan menembus lubang cacing.')
            ->assertSee('Petualangan');
    }

    public function test_an_unknown_slug_returns_404(): void
    {
        $this->actingAs($this->user)
            ->get('/film/tidak-ada')
            ->assertNotFound();

        $this->actingAs($this->user)
            ->get('/media/tmdb-film-tidak-ada')
            ->assertNotFound();
    }

    public function test_marking_as_watched_records_the_entry(): void
    {
        $this->detail()
            ->call('setStatus', WatchStatus::Watched->value)
            ->assertSet('status', WatchStatus::Watched->value);

        $entry = WatchEntry::sole();

        $this->assertSame(WatchStatus::Watched, $entry->status);
        $this->assertNotNull($entry->watched_at);
    }

    public function test_adding_to_the_watchlist_records_no_watch_date(): void
    {
        $this->detail()
            ->call('setStatus', WatchStatus::Watchlist->value)
            ->assertSet('status', WatchStatus::Watchlist->value);

        $this->assertNull(WatchEntry::sole()->watched_at);
    }

    public function test_switching_between_lists_reuses_the_same_row(): void
    {
        $this->detail()
            ->call('setStatus', WatchStatus::Watchlist->value)
            ->call('setStatus', WatchStatus::Watched->value);

        $this->assertDatabaseCount('watch_entries', 1);
        $this->assertSame(WatchStatus::Watched, WatchEntry::sole()->status);
    }

    public function test_marking_watched_twice_keeps_the_original_date(): void
    {
        $this->detail()->call('setStatus', WatchStatus::Watched->value);

        $first = WatchEntry::sole()->watched_at;

        $this->travel(3)->days();

        $this->detail()->call('setStatus', WatchStatus::Watched->value);

        $this->assertTrue($first->equalTo(WatchEntry::sole()->watched_at));
    }

    public function test_removing_the_entry_clears_it(): void
    {
        WatchEntry::factory()->create([
            'user_id' => $this->user->id,
            'media_cache_id' => $this->media->id,
        ]);

        $this->detail()
            ->call('removeEntry')
            ->assertSet('status', null)
            ->assertSet('rating', null);

        $this->assertDatabaseCount('watch_entries', 0);
    }

    public function test_rating_also_marks_the_title_as_watched(): void
    {
        $this->detail()
            ->call('rate', 8)
            ->assertSet('rating', 8)
            ->assertSet('status', WatchStatus::Watched->value);

        $this->assertSame(8, WatchEntry::sole()->rating);
    }

    public function test_a_rating_outside_one_to_ten_is_ignored(): void
    {
        $this->detail()
            ->call('rate', 11)
            ->assertSet('rating', null)
            ->call('rate', 0)
            ->assertSet('rating', null);

        $this->assertDatabaseCount('watch_entries', 0);
    }

    public function test_a_rating_can_be_cleared_without_losing_the_entry(): void
    {
        $this->detail()
            ->call('rate', 7)
            ->call('clearRating')
            ->assertSet('rating', null)
            ->assertSet('status', WatchStatus::Watched->value);

        $this->assertNull(WatchEntry::sole()->rating);
    }

    public function test_writing_a_review_stores_it_and_logs_the_title(): void
    {
        $this->detail()
            ->call('editReview')
            ->set('form.body', 'Film terbaik yang pernah saya tonton.')
            ->set('form.contains_spoiler', true)
            ->call('saveReview')
            ->assertHasNoErrors()
            ->assertSet('editingReview', false);

        $review = Review::sole();

        $this->assertSame('Film terbaik yang pernah saya tonton.', $review->body);
        $this->assertTrue($review->contains_spoiler);
        $this->assertSame(WatchStatus::Watched, WatchEntry::sole()->status);
    }

    public function test_an_empty_review_is_rejected(): void
    {
        $this->detail()
            ->call('editReview')
            ->set('form.body', '')
            ->call('saveReview')
            ->assertHasErrors('form.body');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_saving_a_review_twice_updates_instead_of_duplicating(): void
    {
        $this->detail()
            ->call('editReview')
            ->set('form.body', 'Versi pertama.')
            ->call('saveReview')
            ->call('editReview')
            ->set('form.body', 'Versi kedua.')
            ->call('saveReview');

        $this->assertDatabaseCount('reviews', 1);
        $this->assertSame('Versi kedua.', Review::sole()->body);
    }

    public function test_removing_the_entry_keeps_the_review(): void
    {
        $this->detail()
            ->call('editReview')
            ->set('form.body', 'Tulisan yang tidak boleh hilang.')
            ->call('saveReview')
            ->call('removeEntry');

        $this->assertDatabaseCount('watch_entries', 0);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_a_review_can_be_deleted(): void
    {
        Review::factory()->create([
            'user_id' => $this->user->id,
            'media_cache_id' => $this->media->id,
        ]);

        $this->detail()->call('deleteReview');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_favourites_can_be_toggled(): void
    {
        $this->detail()
            ->call('toggleFavorite')
            ->assertSet('isFavorite', true)
            ->call('toggleFavorite')
            ->assertSet('isFavorite', false);

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_favourites_stop_at_the_limit(): void
    {
        Favorite::factory()
            ->count(Favorite::MAX_PER_USER)
            ->sequence(fn ($sequence) => ['media_cache_id' => MediaCache::factory()])
            ->create(['user_id' => $this->user->id]);

        $this->detail()
            ->call('toggleFavorite')
            ->assertHasErrors('favorite')
            ->assertSet('isFavorite', false);

        $this->assertDatabaseCount('favorites', Favorite::MAX_PER_USER);
    }

    public function test_one_users_entry_never_leaks_into_another_users_page(): void
    {
        WatchEntry::factory()->create([
            'user_id' => User::factory()->create()->id,
            'media_cache_id' => $this->media->id,
            'rating' => 9,
        ]);

        $this->detail()
            ->assertSet('status', null)
            ->assertSet('rating', null);
    }

    public function test_kursi_penuh_is_the_share_of_viewers_who_rated_seven_or_more(): void
    {
        foreach ([9, 8, 7, 7, 6, 5, 10, 8] as $rating) {
            WatchEntry::factory()->create(['media_cache_id' => $this->media->id, 'rating' => $rating]);
        }
        WatchEntry::factory()->unrated()->create(['media_cache_id' => $this->media->id]);
        // Watchlist tidak dihitung.
        WatchEntry::factory()->watchlist()->create(['media_cache_id' => $this->media->id]);

        // 6 dari 8 rating bernilai 7 ke atas = 75%, rata-rata 7,5.
        $this->detail()
            ->assertSee('75%')
            ->assertSee('Kursi Penuh 75%: 8 dari 10 kursi terisi')
            ->assertSee('6 dari 8 penonton memberi 7 ke atas.')
            ->assertSee('Rata-rata 7,5/10.')
            ->assertSee('9 orang sudah menonton');
    }

    public function test_kursi_penuh_waits_for_enough_ratings(): void
    {
        WatchEntry::factory()->create(['media_cache_id' => $this->media->id, 'rating' => 9]);
        WatchEntry::factory()->create(['media_cache_id' => $this->media->id, 'rating' => 8]);

        $this->detail()
            ->assertSee('Belum cukup penonton: baru 2 dari 5 rating yang dibutuhkan.')
            ->assertSee('Skor Kursi Penuh belum tersedia')
            ->assertDontSee('100%');
    }

    public function test_titles_without_ratings_say_so(): void
    {
        $this->detail()->assertSee('Belum ada rating di Kursi Penuh');
    }

    public function test_it_shows_friends_ratings_and_reviews_but_not_strangers(): void
    {
        $friend = User::factory()->create(['name' => 'Teman Akrab']);
        $planner = User::factory()->create(['name' => 'Teman Rencana']);
        $stranger = User::factory()->create(['name' => 'Orang Asing']);

        Friendship::factory()->accepted()->create(['user_id' => $friend->id, 'friend_id' => $this->user->id]);
        Friendship::factory()->accepted()->create(['user_id' => $this->user->id, 'friend_id' => $planner->id]);

        WatchEntry::factory()->for($friend)->create(['media_cache_id' => $this->media->id, 'rating' => 7]);
        Review::factory()->for($friend)->create(['media_cache_id' => $this->media->id, 'body' => 'Endingnya bikin nangis']);
        WatchEntry::factory()->for($planner)->watchlist()->create(['media_cache_id' => $this->media->id]);

        WatchEntry::factory()->for($stranger)->create(['media_cache_id' => $this->media->id]);
        Review::factory()->for($stranger)->create(['media_cache_id' => $this->media->id, 'body' => 'Review orang asing']);

        $this->detail()
            ->assertSeeInOrder(['Teman Akrab', '★ 7', 'Endingnya bikin nangis', 'Teman Rencana', 'ingin menonton'])
            ->assertDontSee('Orang Asing')
            ->assertDontSee('Review orang asing');
    }

    public function test_friend_spoiler_reviews_are_folded(): void
    {
        $friend = User::factory()->create();
        Friendship::factory()->accepted()->create(['user_id' => $this->user->id, 'friend_id' => $friend->id]);

        WatchEntry::factory()->for($friend)->create(['media_cache_id' => $this->media->id]);
        Review::factory()->for($friend)->create(['media_cache_id' => $this->media->id, 'contains_spoiler' => true]);

        $this->detail()
            ->assertSeeHtml('<details')
            ->assertSee('Review mengandung spoiler, tampilkan');
    }

    public function test_it_says_when_no_friend_has_logged_the_title(): void
    {
        $this->detail()->assertSee('Belum ada teman yang menonton atau menyimpan judul ini.');
    }
}
