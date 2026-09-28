<?php

namespace Tests\Feature;

use App\Enums\FriendshipState;
use App\Enums\FriendshipStatus;
use App\Livewire\Friends;
use App\Models\Friendship;
use App\Models\User;
use App\Services\FriendshipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FriendshipTest extends TestCase
{
    use RefreshDatabase;

    private User $arif;

    private User $budi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->arif = User::factory()->create(['name' => 'Arif', 'username' => 'arif']);
        $this->budi = User::factory()->create(['name' => 'Budi', 'username' => 'budi']);
    }

    private function service(): FriendshipService
    {
        return app(FriendshipService::class);
    }

    public function test_a_request_can_be_sent_and_accepted(): void
    {
        $friendship = $this->service()->sendRequest($this->arif, $this->budi);

        $this->assertSame(FriendshipStatus::Pending, $friendship->status);
        $this->assertFalse($this->arif->isFriendsWith($this->budi));

        $this->service()->accept($this->budi, $friendship);

        $this->assertSame(FriendshipStatus::Accepted, $friendship->fresh()->status);
        $this->assertNotNull($friendship->fresh()->accepted_at);
        $this->assertTrue($this->arif->fresh()->isFriendsWith($this->budi));
        $this->assertTrue($this->budi->fresh()->isFriendsWith($this->arif));
    }

    public function test_nobody_can_befriend_themselves(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->sendRequest($this->arif, $this->arif);
    }

    public function test_a_second_request_between_the_same_pair_is_refused(): void
    {
        $this->service()->sendRequest($this->arif, $this->budi);

        $this->expectException(ValidationException::class);

        $this->service()->sendRequest($this->arif, $this->budi);
    }

    public function test_a_request_back_in_the_other_direction_is_refused(): void
    {
        $this->service()->sendRequest($this->arif, $this->budi);

        $this->expectException(ValidationException::class);

        $this->service()->sendRequest($this->budi, $this->arif);
    }

    public function test_only_the_addressee_may_accept(): void
    {
        $friendship = $this->service()->sendRequest($this->arif, $this->budi);

        $this->expectException(AuthorizationException::class);

        $this->service()->accept($this->arif, $friendship);
    }

    public function test_an_outsider_cannot_accept_someone_elses_request(): void
    {
        $friendship = $this->service()->sendRequest($this->arif, $this->budi);
        $outsider = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->service()->accept($outsider, $friendship);
    }

    public function test_only_the_sender_may_cancel(): void
    {
        $friendship = $this->service()->sendRequest($this->arif, $this->budi);

        $this->expectException(AuthorizationException::class);

        $this->service()->cancel($this->budi, $friendship);
    }

    public function test_rejecting_removes_the_request(): void
    {
        $friendship = $this->service()->sendRequest($this->arif, $this->budi);

        $this->service()->reject($this->budi, $friendship);

        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_either_side_can_end_the_friendship(): void
    {
        $friendship = $this->service()->sendRequest($this->arif, $this->budi);
        $this->service()->accept($this->budi, $friendship);

        // Budi yang menerima, tapi dia juga boleh memutuskan.
        $this->service()->remove($this->budi, $this->arif);

        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_the_state_is_reported_from_each_side(): void
    {
        $this->assertSame(FriendshipState::Self, $this->service()->stateBetween($this->arif, $this->arif));
        $this->assertSame(FriendshipState::None, $this->service()->stateBetween($this->arif, $this->budi));

        $friendship = $this->service()->sendRequest($this->arif, $this->budi);

        $this->assertSame(FriendshipState::PendingOutgoing, $this->service()->stateBetween($this->arif, $this->budi));
        $this->assertSame(FriendshipState::PendingIncoming, $this->service()->stateBetween($this->budi, $this->arif));

        $this->service()->accept($this->budi, $friendship);

        $this->assertSame(FriendshipState::Friends, $this->service()->stateBetween($this->arif, $this->budi));
        $this->assertSame(FriendshipState::Friends, $this->service()->stateBetween($this->budi, $this->arif));
    }

    public function test_friends_are_listed_regardless_of_who_asked_first(): void
    {
        $citra = User::factory()->create(['name' => 'Citra']);

        Friendship::factory()->accepted()->create([
            'user_id' => $this->arif->id,
            'friend_id' => $this->budi->id,
        ]);
        Friendship::factory()->accepted()->create([
            'user_id' => $citra->id,
            'friend_id' => $this->arif->id,
        ]);

        $names = $this->service()->friendsOf($this->arif)->pluck('name')->all();

        $this->assertSame(['Budi', 'Citra'], $names);
    }

    public function test_pending_rows_are_not_counted_as_friends(): void
    {
        Friendship::factory()->create([
            'user_id' => $this->arif->id,
            'friend_id' => $this->budi->id,
        ]);

        $this->assertSame([], $this->service()->friendIdsOf($this->arif));
    }

    public function test_the_friends_page_lists_incoming_requests_and_accepts_them(): void
    {
        $friendship = Friendship::factory()->create([
            'user_id' => $this->budi->id,
            'friend_id' => $this->arif->id,
        ]);

        Livewire::actingAs($this->arif)
            ->test(Friends::class)
            ->assertSee('Budi')
            ->call('accept', $friendship->id)
            ->assertSee('Budi');

        $this->assertSame(FriendshipStatus::Accepted, $friendship->fresh()->status);
    }

    public function test_the_friends_page_refuses_to_accept_a_request_meant_for_someone_else(): void
    {
        $outsider = User::factory()->create();

        $friendship = Friendship::factory()->create([
            'user_id' => $this->budi->id,
            'friend_id' => $outsider->id,
        ]);

        Livewire::actingAs($this->arif)
            ->test(Friends::class)
            ->call('accept', $friendship->id)
            ->assertForbidden();

        $this->assertSame(FriendshipStatus::Pending, $friendship->fresh()->status);
    }

    public function test_user_search_hides_yourself_and_existing_relations(): void
    {
        Friendship::factory()->accepted()->create([
            'user_id' => $this->arif->id,
            'friend_id' => $this->budi->id,
        ]);

        $citra = User::factory()->create(['name' => 'Citra', 'username' => 'citra']);

        Livewire::actingAs($this->arif)
            ->test(Friends::class)
            ->set('query', 'a')          // di bawah panjang minimum
            ->assertDontSee('@citra')
            ->set('query', 'ci')
            ->assertSee('Citra')
            ->set('query', 'bu')
            ->assertDontSee('@budi')
            ->set('query', 'ari')
            ->assertDontSee('@arif');

        $this->assertNotNull($citra->fresh());
    }

    public function test_sending_a_request_from_the_friends_page_works(): void
    {
        Livewire::actingAs($this->arif)
            ->test(Friends::class)
            ->set('query', 'budi')
            ->call('add', $this->budi->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('friendships', [
            'user_id' => $this->arif->id,
            'friend_id' => $this->budi->id,
            'status' => FriendshipStatus::Pending->value,
        ]);
    }

    public function test_a_duplicate_request_shows_an_error_instead_of_crashing(): void
    {
        Friendship::factory()->create([
            'user_id' => $this->arif->id,
            'friend_id' => $this->budi->id,
        ]);

        Livewire::actingAs($this->arif)
            ->test(Friends::class)
            ->call('add', $this->budi->id)
            ->assertHasErrors('friendship');

        $this->assertDatabaseCount('friendships', 1);
    }
}
