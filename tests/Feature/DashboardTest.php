<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Friendship;
use App\Models\MediaCache;
use App\Models\Review;
use App\Models\User;
use App\Models\WatchEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Rafi']);
    }

    public function test_the_dashboard_route_renders_the_home_component(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(Dashboard::class)
            ->assertSee('Halo, Rafi');
    }

    public function test_it_shows_what_friends_watched_but_not_strangers_or_pending_friends(): void
    {
        $friend = User::factory()->create(['name' => 'Teman Akrab']);
        $pending = User::factory()->create(['name' => 'Masih Pending']);
        $stranger = User::factory()->create(['name' => 'Orang Asing']);

        Friendship::factory()->accepted()->create(['user_id' => $friend->id, 'friend_id' => $this->user->id]);
        Friendship::factory()->create(['user_id' => $this->user->id, 'friend_id' => $pending->id]);

        $interstellar = MediaCache::factory()->film()->create(['title' => 'Interstellar']);
        WatchEntry::factory()->for($friend)->create(['media_cache_id' => $interstellar->id, 'rating' => 9]);
        Review::factory()->for($friend)->create(['media_cache_id' => $interstellar->id, 'body' => 'Musiknya bikin merinding', 'contains_spoiler' => false]);

        // Watchlist teman bukan "baru ditonton".
        WatchEntry::factory()->for($friend)->watchlist()->create(['media_cache_id' => MediaCache::factory()->film()->create(['title' => 'Dune Dua'])->id]);
        WatchEntry::factory()->for($pending)->create(['media_cache_id' => MediaCache::factory()->film()->create(['title' => 'Judul Pending'])->id]);
        WatchEntry::factory()->for($stranger)->create(['media_cache_id' => MediaCache::factory()->film()->create(['title' => 'Judul Orang Asing'])->id]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSee('Teman Akrab')
            ->assertSee('Interstellar')
            ->assertSee('★ 9')
            ->assertSee('Musiknya bikin merinding')
            ->assertDontSee('Dune Dua')
            ->assertDontSee('Judul Pending')
            ->assertDontSee('Judul Orang Asing');
    }

    public function test_spoiler_reviews_are_folded(): void
    {
        $friend = User::factory()->create();
        Friendship::factory()->accepted()->create(['user_id' => $this->user->id, 'friend_id' => $friend->id]);

        $media = MediaCache::factory()->film()->create();
        WatchEntry::factory()->for($friend)->create(['media_cache_id' => $media->id]);
        Review::factory()->for($friend)->create(['media_cache_id' => $media->id, 'contains_spoiler' => true]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSeeHtml('<details')
            ->assertSee('Review mengandung spoiler');
    }

    public function test_it_shows_the_users_watchlist_and_pending_friend_requests(): void
    {
        WatchEntry::factory()->for($this->user)->watchlist()->create(['media_cache_id' => MediaCache::factory()->film()->create(['title' => 'Oppenheimer'])->id]);
        Friendship::factory()->create(['friend_id' => $this->user->id]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSee('Oppenheimer')
            ->assertSee('1 permintaan pertemanan menunggu jawabanmu');
    }

    public function test_recommendations_skip_titles_the_user_already_logged(): void
    {
        $fresh = MediaCache::factory()->film()->create(['title' => 'Rekomendasi Baru']);
        $seen = MediaCache::factory()->film()->create(['title' => 'Sudah Ditonton Duluan']);
        WatchEntry::factory()->for($this->user)->create(['media_cache_id' => $seen->id]);

        $this->user->aiInsights()->create(['generated_at' => now(), 'content' => [
            'recommendations' => [
                ['media_id' => $seen->id, 'reason' => null],
                ['media_id' => $fresh->id, 'reason' => null],
            ],
        ]]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSee('Rekomendasi Baru')
            ->assertDontSee('Sudah Ditonton Duluan');
    }

    public function test_new_users_get_guidance_instead_of_empty_sections(): void
    {
        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSee('Tambah teman untuk melihat apa yang sedang mereka tonton.')
            ->assertSee('Watchlist masih kosong.')
            ->assertSee('Rekomendasi muncul setelah insight mingguanmu dibuat');
    }
}
