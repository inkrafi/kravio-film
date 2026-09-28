<?php

namespace Tests\Feature;

use App\Livewire\ProfileComments;
use App\Models\Friendship;
use App\Models\ProfileComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileCommentsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $friend;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Budi', 'username' => 'budi']);
        $this->friend = User::factory()->create(['name' => 'Arif', 'username' => 'arif']);
        $this->stranger = User::factory()->create(['name' => 'Citra', 'username' => 'citra']);

        Friendship::factory()->accepted()->create([
            'user_id' => $this->friend->id,
            'friend_id' => $this->owner->id,
        ]);
    }

    private function as(User $viewer)
    {
        return Livewire::actingAs($viewer)->test(ProfileComments::class, ['user' => $this->owner]);
    }

    private function commentFrom(User $author, string $body, array $attributes = []): ProfileComment
    {
        return ProfileComment::factory()->create([
            'commenter_id' => $author->id,
            'profile_user_id' => $this->owner->id,
            'body' => $body,
        ] + $attributes);
    }

    public function test_the_profile_page_renders_the_comments_component(): void
    {
        $this->actingAs($this->friend)
            ->get(route('profile.show', $this->owner))
            ->assertOk()
            ->assertSeeLivewire(ProfileComments::class);
    }

    public function test_a_friend_can_post_a_comment_without_a_reload(): void
    {
        $this->as($this->friend)
            ->set('body', '  Rekomendasi Dune-mu mantap!  ')
            ->call('post')
            ->assertHasNoErrors()
            ->assertSet('body', '')
            ->assertSee('Rekomendasi Dune-mu mantap!')
            ->assertSee('Arif');

        $this->assertDatabaseHas('profile_comments', [
            'commenter_id' => $this->friend->id,
            'profile_user_id' => $this->owner->id,
            'body' => 'Rekomendasi Dune-mu mantap!',
        ]);
    }

    public function test_the_owner_cannot_write_a_new_comment_on_their_own_profile(): void
    {
        $this->as($this->owner)
            ->assertDontSeeHtml('wire:submit="post"')
            ->assertSee('kamu bisa membalasnya')
            ->assertSee('Belum ada komentar dari teman.')
            ->set('body', 'Minggu ini maraton Ghibli.')
            ->call('post')
            ->assertForbidden();

        $this->assertSame(0, $this->owner->profileComments()->count());
    }

    public function test_the_owner_can_reply_to_a_friends_comment(): void
    {
        $comment = $this->commentFrom($this->friend, 'Rekomendasi Dune-mu mantap!');

        $this->as($this->owner)
            ->assertSee('Balas')
            ->call('startReply', $comment->id)
            ->assertSee('Balas Arif…')
            ->set('replyBody', '  Makasih, tonton juga Part Two!  ')
            ->call('postReply')
            ->assertHasNoErrors()
            ->assertSet('replyingTo', null)
            ->assertSeeInOrder(['Rekomendasi Dune-mu mantap!', 'Pemilik profil', 'Makasih, tonton juga Part Two!']);

        $this->assertDatabaseHas('profile_comments', [
            'commenter_id' => $this->owner->id,
            'profile_user_id' => $this->owner->id,
            'parent_id' => $comment->id,
            'body' => 'Makasih, tonton juga Part Two!',
        ]);
    }

    public function test_friends_can_reply_too_and_replies_stay_one_level_deep(): void
    {
        $comment = $this->commentFrom($this->friend, 'Komentar awal');
        $ownerReply = $this->commentFrom($this->owner, 'Balasan pemilik', ['parent_id' => $comment->id]);

        // Membalas sebuah balasan tetap masuk ke utas komentar induk.
        $this->as($this->friend)
            ->call('startReply', $ownerReply->id)
            ->set('replyBody', 'Sama-sama!')
            ->call('postReply')
            ->assertHasNoErrors();

        $this->assertSame(
            [$ownerReply->id, ProfileComment::where('body', 'Sama-sama!')->value('id')],
            $comment->replies()->pluck('id')->all(),
        );
        $this->assertSame($comment->id, ProfileComment::where('body', 'Sama-sama!')->value('parent_id'));
    }

    public function test_strangers_cannot_reply(): void
    {
        $comment = $this->commentFrom($this->friend, 'Komentar awal');

        $this->as($this->stranger)
            ->assertDontSee('Komentar awal')
            ->set('replyingTo', $comment->id)
            ->set('replyBody', 'Nimbrung')
            ->call('postReply')
            ->assertForbidden();

        $this->assertSame(1, ProfileComment::count());
    }

    public function test_a_reply_cannot_target_a_comment_on_another_profile(): void
    {
        $elsewhere = ProfileComment::factory()->create();

        $this->as($this->owner)
            ->set('replyingTo', $elsewhere->id)
            ->set('replyBody', 'Nyasar')
            ->call('postReply')
            ->assertSet('replyingTo', null);

        $this->assertSame(0, ProfileComment::whereNotNull('parent_id')->count());
    }

    public function test_replies_are_validated_and_share_the_rate_limit(): void
    {
        $comment = $this->commentFrom($this->friend, 'Komentar awal');

        $this->as($this->owner)
            ->call('startReply', $comment->id)
            ->set('replyBody', '   ')
            ->call('postReply')
            ->assertHasErrors(['replyBody' => 'required']);

        $component = $this->as($this->owner);

        for ($i = 1; $i <= 5; $i++) {
            $component->call('startReply', $comment->id)->set('replyBody', "Balasan {$i}")->call('postReply')->assertHasNoErrors();
        }

        $component->call('startReply', $comment->id)->set('replyBody', 'Balasan 6')->call('postReply')->assertHasErrors('replyBody');
    }

    public function test_deleting_a_comment_removes_its_replies(): void
    {
        $comment = $this->commentFrom($this->friend, 'Komentar awal');
        $reply = $this->commentFrom($this->owner, 'Balasan', ['parent_id' => $comment->id]);

        $this->as($this->owner)->call('delete', $comment->id);

        $this->assertModelMissing($comment);
        $this->assertModelMissing($reply);
    }

    public function test_pagination_counts_threads_not_replies(): void
    {
        $comment = $this->commentFrom($this->friend, 'Satu-satunya utas');

        foreach (range(1, ProfileComments::PAGE_SIZE + 5) as $i) {
            $this->commentFrom($this->owner, "Balasan {$i}", ['parent_id' => $comment->id]);
        }

        $this->as($this->owner)
            ->assertSee('Balasan '.(ProfileComments::PAGE_SIZE + 5))
            ->assertDontSee('Muat komentar lebih lama');
    }

    public function test_strangers_can_neither_read_nor_post(): void
    {
        $this->commentFrom($this->friend, 'Rahasia antar teman');

        $this->as($this->stranger)
            ->assertDontSee('Rahasia antar teman')
            ->assertSee('hanya bisa dilihat dan ditulis oleh teman')
            ->set('body', 'Halo!')
            ->call('post')
            ->assertForbidden();

        $this->assertSame(1, ProfileComment::count());
    }

    public function test_a_pending_request_does_not_unlock_comments(): void
    {
        Friendship::factory()->create([
            'user_id' => $this->stranger->id,
            'friend_id' => $this->owner->id,
        ]);

        $this->as($this->stranger)->set('body', 'Halo!')->call('post')->assertForbidden();
    }

    public function test_the_body_is_validated(): void
    {
        $this->as($this->friend)
            ->set('body', '   ')
            ->call('post')
            ->assertHasErrors(['body' => 'required']);

        $this->as($this->friend)
            ->set('body', str_repeat('a', ProfileComment::MAX_LENGTH + 1))
            ->call('post')
            ->assertHasErrors(['body' => 'max']);
    }

    public function test_posting_is_rate_limited(): void
    {
        $component = $this->as($this->friend);

        for ($i = 1; $i <= 5; $i++) {
            $component->set('body', "Komentar {$i}")->call('post')->assertHasNoErrors();
        }

        $component->set('body', 'Komentar 6')->call('post')->assertHasErrors('body');

        $this->assertSame(5, ProfileComment::count());
    }

    public function test_comments_are_listed_newest_first(): void
    {
        $this->commentFrom($this->friend, 'Komentar lama', ['created_at' => now()->subDay()]);
        $this->commentFrom($this->friend, 'Komentar baru');

        $this->as($this->owner)->assertSeeInOrder(['Komentar baru', 'Komentar lama']);
    }

    public function test_older_comments_load_on_demand(): void
    {
        $this->commentFrom($this->friend, 'Komentar tertua', ['created_at' => now()->subYear()]);

        foreach (range(1, ProfileComments::PAGE_SIZE) as $i) {
            $this->commentFrom($this->friend, "Komentar {$i}");
        }

        $this->as($this->owner)
            ->assertDontSee('Komentar tertua')
            ->assertSee('Muat komentar lebih lama')
            ->call('loadMore')
            ->assertSee('Komentar tertua')
            ->assertDontSee('Muat komentar lebih lama');
    }

    public function test_new_comments_from_others_appear_on_the_next_poll(): void
    {
        $component = $this->as($this->owner)->assertDontSee('Muncul saat polling');

        $this->commentFrom($this->friend, 'Muncul saat polling');

        // wire:poll memanggil $refresh; hasilnya sama dengan render ulang.
        $component->call('$refresh')->assertSee('Muncul saat polling');
    }

    public function test_the_list_polls_only_for_people_who_can_see_it(): void
    {
        $this->as($this->friend)->assertSeeHtml('wire:poll.'.ProfileComments::POLL_SECONDS.'s.visible');
        $this->as($this->stranger)->assertDontSeeHtml('wire:poll');
    }

    public function test_authors_can_delete_their_own_comment(): void
    {
        $comment = $this->commentFrom($this->friend, 'Salah ketik');

        $this->as($this->friend)->call('delete', $comment->id)->assertDontSee('Salah ketik');

        $this->assertModelMissing($comment);
    }

    public function test_the_owner_can_delete_any_comment_on_their_profile(): void
    {
        $comment = $this->commentFrom($this->friend, 'Spoiler ending!');

        $this->as($this->owner)->call('delete', $comment->id);

        $this->assertModelMissing($comment);
    }

    public function test_other_friends_cannot_delete_someone_elses_comment(): void
    {
        $otherFriend = User::factory()->create();
        Friendship::factory()->accepted()->create(['user_id' => $otherFriend->id, 'friend_id' => $this->owner->id]);

        $comment = $this->commentFrom($this->friend, 'Punya Arif');

        $this->as($otherFriend)
            ->assertDontSeeHtml('wire:click="delete('.$comment->id.')"')
            ->call('delete', $comment->id)
            ->assertForbidden();

        $this->assertModelExists($comment);
    }

    public function test_comments_on_another_profile_cannot_be_deleted_through_this_one(): void
    {
        $elsewhere = ProfileComment::factory()->create(['commenter_id' => $this->owner->id]);

        $this->as($this->owner)->call('delete', $elsewhere->id);

        $this->assertModelExists($elsewhere);
    }
}
