<?php

namespace Tests\Feature;

use App\Livewire\ListShow;
use App\Livewire\NotificationBell;
use App\Livewire\ProfileComments;
use App\Models\Friendship;
use App\Models\MediaList;
use App\Models\ProfileComment;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use App\Notifications\ListCommented;
use App\Notifications\ProfileCommentPosted;
use App\Notifications\ProfileCommentReplied;
use App\Services\FriendshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $budi;

    private User $arif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->budi = User::factory()->create(['name' => 'Budi', 'username' => 'budi']);
        $this->arif = User::factory()->create(['name' => 'Arif', 'username' => 'arif']);
    }

    private function friends(User $a, User $b): void
    {
        Friendship::factory()->accepted()->create(['user_id' => $a->id, 'friend_id' => $b->id]);
    }

    public function test_friend_requests_notify_the_addressee_and_acceptance_notifies_the_sender(): void
    {
        $friendships = app(FriendshipService::class);

        $friendship = $friendships->sendRequest($this->arif, $this->budi);

        $this->assertSame(FriendRequestReceived::class, $this->budi->notifications()->sole()->type);
        $this->assertSame([
            'actor_id' => $this->arif->id,
            'actor_name' => 'Arif',
            'message' => 'mengirim permintaan pertemanan',
            'url' => route('friends'),
        ], $this->budi->notifications()->sole()->data);

        $friendships->accept($this->budi, $friendship);

        // Permintaan yang sudah dijawab hilang dari lonceng penerimanya.
        $this->assertSame(0, $this->budi->notifications()->count());
        $this->assertSame(FriendRequestAccepted::class, $this->arif->notifications()->sole()->type);
        $this->assertSame(route('profile.show', $this->budi), $this->arif->notifications()->sole()->data['url']);
    }

    public function test_a_cancelled_or_rejected_request_leaves_no_notification(): void
    {
        $friendships = app(FriendshipService::class);

        $friendships->cancel($this->arif, $friendships->sendRequest($this->arif, $this->budi));
        $this->assertSame(0, $this->budi->notifications()->count());

        $friendships->reject($this->budi, $friendships->sendRequest($this->arif, $this->budi));
        $this->assertSame(0, $this->budi->notifications()->count());
        $this->assertSame(0, $this->arif->notifications()->count());
    }

    public function test_a_profile_comment_notifies_the_owner_but_not_when_commenting_on_your_own_profile(): void
    {
        $this->friends($this->arif, $this->budi);

        Livewire::actingAs($this->arif)
            ->test(ProfileComments::class, ['user' => $this->budi])
            ->set('body', 'Rekomendasi Dune-mu mantap!')
            ->call('post');

        $notification = $this->budi->notifications()->sole();
        $this->assertSame(ProfileCommentPosted::class, $notification->type);
        $this->assertSame('mengomentari profilmu: “Rekomendasi Dune-mu mantap!”', $notification->data['message']);

        Livewire::actingAs($this->budi)
            ->test(ProfileComments::class, ['user' => $this->budi])
            ->set('body', 'Catatan untuk diri sendiri')
            ->call('post');

        $this->assertSame(1, $this->budi->notifications()->count());
    }

    public function test_a_reply_notifies_the_profile_owner_and_the_comment_author_but_not_the_replier(): void
    {
        $citra = User::factory()->create(['name' => 'Citra']);
        $this->friends($this->arif, $this->budi);
        $this->friends($citra, $this->budi);

        $comment = ProfileComment::factory()->create([
            'commenter_id' => $this->arif->id,
            'profile_user_id' => $this->budi->id,
            'body' => 'Komentar awal',
        ]);

        Livewire::actingAs($citra)
            ->test(ProfileComments::class, ['user' => $this->budi])
            ->call('startReply', $comment->id)
            ->set('replyBody', 'Setuju banget')
            ->call('postReply')
            ->assertHasNoErrors();

        $this->assertSame('membalas komentar di profilmu: “Setuju banget”', $this->budi->notifications()->sole()->data['message']);
        $this->assertSame('membalas komentar di profil Budi: “Setuju banget”', $this->arif->notifications()->sole()->data['message']);
        $this->assertSame(ProfileCommentReplied::class, $this->arif->notifications()->sole()->type);
        $this->assertSame(0, $citra->notifications()->count());
    }

    public function test_a_list_comment_notifies_the_list_owner(): void
    {
        $list = MediaList::factory()->create(['user_id' => $this->budi->id, 'title' => 'Film Hujan']);

        Livewire::actingAs($this->arif)
            ->test(ListShow::class, ['list' => (string) $list->id])
            ->set('commentBody', 'List yang mantap!')
            ->call('postComment');

        $notification = $this->budi->notifications()->sole();
        $this->assertSame(ListCommented::class, $notification->type);
        $this->assertSame('mengomentari list “Film Hujan”: “List yang mantap!”', $notification->data['message']);
        $this->assertSame($list->url(), $notification->data['url']);
    }

    public function test_the_bell_shows_unread_notifications_and_opening_one_marks_it_read(): void
    {
        app(FriendshipService::class)->sendRequest($this->arif, $this->budi);
        $notification = $this->budi->notifications()->sole();

        Livewire::actingAs($this->budi)
            ->test(NotificationBell::class)
            ->assertSee('Notifikasi (1 belum dibaca)')
            ->assertSee('Arif')
            ->assertSee('mengirim permintaan pertemanan')
            ->call('open', $notification->id)
            ->assertRedirect(route('friends'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_all_as_read_clears_the_badge(): void
    {
        $this->friends($this->arif, $this->budi);

        Livewire::actingAs($this->arif)
            ->test(ProfileComments::class, ['user' => $this->budi])
            ->set('body', 'Halo')
            ->call('post');

        Livewire::actingAs($this->budi)
            ->test(NotificationBell::class)
            ->call('markAllAsRead')
            ->assertDontSee('belum dibaca');

        $this->assertSame(0, $this->budi->unreadNotifications()->count());
    }

    public function test_users_cannot_open_someone_elses_notification(): void
    {
        app(FriendshipService::class)->sendRequest($this->arif, $this->budi);
        $notification = $this->budi->notifications()->sole();

        Livewire::actingAs($this->arif)
            ->test(NotificationBell::class)
            ->call('open', $notification->id)
            ->assertNoRedirect();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_an_empty_bell_says_so(): void
    {
        Livewire::actingAs($this->budi)
            ->test(NotificationBell::class)
            ->assertSee('Belum ada notifikasi.');
    }
}
