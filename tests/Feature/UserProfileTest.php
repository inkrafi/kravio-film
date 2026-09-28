<?php

namespace Tests\Feature;

use App\Enums\WatchStatus;
use App\Livewire\UserProfile;
use App\Models\Favorite;
use App\Models\Friendship;
use App\Models\MediaCache;
use App\Models\User;
use App\Models\WatchEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'name' => 'Budi',
            'username' => 'budi',
            'bio' => 'Penggemar fiksi ilmiah.',
        ]);

        $this->viewer = User::factory()->create(['name' => 'Arif', 'username' => 'arif']);
    }

    private function logWatched(string $title): MediaCache
    {
        $media = MediaCache::factory()->create(['title' => $title]);

        WatchEntry::factory()->create([
            'user_id' => $this->owner->id,
            'media_cache_id' => $media->id,
            'status' => WatchStatus::Watched,
            'rating' => 9,
        ]);

        return $media;
    }

    private function befriend(): void
    {
        Friendship::factory()->accepted()->create([
            'user_id' => $this->viewer->id,
            'friend_id' => $this->owner->id,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('profile.show', $this->owner))->assertRedirect(route('login'));
    }

    public function test_the_profile_is_addressed_by_username(): void
    {
        $this->assertSame(
            url('/u/budi'),
            route('profile.show', $this->owner),
        );

        $this->actingAs($this->viewer)
            ->get(route('profile.show', $this->owner))
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee('Penggemar fiksi ilmiah.');
    }

    public function test_bio_and_favourites_are_visible_to_strangers(): void
    {
        $media = MediaCache::factory()->create(['title' => 'Blade Runner 2049']);
        Favorite::factory()->create([
            'user_id' => $this->owner->id,
            'media_cache_id' => $media->id,
        ]);

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Penggemar fiksi ilmiah.')
            ->assertSee('Blade Runner 2049');
    }

    public function test_the_library_is_hidden_from_strangers(): void
    {
        $this->logWatched('Interstellar');

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Riwayat tontonan disembunyikan')
            ->assertDontSee('Interstellar');
    }

    public function test_a_pending_request_is_not_enough_to_unlock_the_library(): void
    {
        $this->logWatched('Interstellar');

        Friendship::factory()->create([
            'user_id' => $this->viewer->id,
            'friend_id' => $this->owner->id,
        ]);

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Riwayat tontonan disembunyikan')
            ->assertDontSee('Interstellar');
    }

    public function test_friends_can_see_the_library(): void
    {
        $this->logWatched('Interstellar');
        $this->befriend();

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Interstellar')
            ->assertDontSee('Riwayat tontonan disembunyikan');
    }

    public function test_the_friendship_direction_does_not_matter_for_access(): void
    {
        $this->logWatched('Interstellar');

        // Kali ini pemilik profil yang dulu mengirim permintaan.
        Friendship::factory()->accepted()->create([
            'user_id' => $this->owner->id,
            'friend_id' => $this->viewer->id,
        ]);

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Interstellar');
    }

    public function test_your_own_library_is_always_visible(): void
    {
        $this->logWatched('Interstellar');

        Livewire::actingAs($this->owner)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Interstellar')
            ->assertSee('Edit profil');
    }

    public function test_the_tabs_separate_watched_from_the_watchlist(): void
    {
        $this->befriend();
        $this->logWatched('Interstellar');

        $planned = MediaCache::factory()->create(['title' => 'Dune Part Three']);
        WatchEntry::factory()->watchlist()->create([
            'user_id' => $this->owner->id,
            'media_cache_id' => $planned->id,
        ]);

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Interstellar')
            ->assertDontSee('Dune Part Three')
            ->call('selectTab', WatchStatus::Watchlist->value)
            ->assertSee('Dune Part Three')
            ->assertDontSee('Interstellar');
    }

    public function test_an_unknown_tab_falls_back_to_watched(): void
    {
        $this->befriend();

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->call('selectTab', 'ngawur')
            ->assertSet('tab', WatchStatus::Watched->value);
    }

    public function test_ratings_are_shown_next_to_watched_titles(): void
    {
        $this->befriend();
        $this->logWatched('Interstellar');

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSeeHtml('rounded-full bg-amber-500');
    }

    public function test_a_friend_request_can_be_sent_from_the_profile(): void
    {
        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Tambah Teman')
            ->call('sendFriendRequest')
            ->assertHasNoErrors()
            ->assertSee('Menunggu konfirmasi');

        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_an_incoming_request_can_be_accepted_from_the_profile(): void
    {
        $this->logWatched('Interstellar');

        Friendship::factory()->create([
            'user_id' => $this->owner->id,
            'friend_id' => $this->viewer->id,
        ]);

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Terima')
            ->assertDontSee('Interstellar')
            ->call('acceptFriendRequest')
            ->assertSee('Berteman')
            ->assertSee('Interstellar');
    }

    public function test_ending_a_friendship_locks_the_library_again(): void
    {
        $this->logWatched('Interstellar');
        $this->befriend();

        Livewire::actingAs($this->viewer)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Interstellar')
            ->call('removeFriend')
            ->assertSee('Riwayat tontonan disembunyikan')
            ->assertDontSee('Interstellar');
    }

    public function test_the_policy_backs_the_gating_independently_of_the_component(): void
    {
        $this->assertFalse($this->viewer->can('viewLibrary', $this->owner));
        $this->assertTrue($this->owner->can('viewLibrary', $this->owner));

        $this->befriend();

        $this->assertTrue($this->viewer->fresh()->can('viewLibrary', $this->owner));
    }
}
