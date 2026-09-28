<?php

namespace Tests\Feature;

use App\Enums\ListVisibility;
use App\Livewire\AddToList;
use App\Livewire\ListEditor;
use App\Livewire\ListShow;
use App\Livewire\UserProfile;
use App\Models\Friendship;
use App\Models\MediaCache;
use App\Models\MediaList;
use App\Models\MediaListItem;
use App\Models\User;
use App\Services\MediaListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MediaListTest extends TestCase
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

        Friendship::factory()->accepted()->create(['user_id' => $this->friend->id, 'friend_id' => $this->owner->id]);
    }

    private function listOf(User $user, ListVisibility $visibility = ListVisibility::Public, array $attributes = []): MediaList
    {
        return MediaList::factory()->visibility($visibility)->create(['user_id' => $user->id] + $attributes);
    }

    /**
     * @param  list<string>  $titles
     */
    private function fill(MediaList $list, array $titles): void
    {
        foreach ($titles as $title) {
            app(MediaListService::class)->add($list, MediaCache::factory()->film()->create(['title' => $title]));
        }
    }

    private function titles(MediaList $list): array
    {
        return $list->items()->with('media')->get()->pluck('media.title')->all();
    }

    public function test_visibility_is_chosen_per_list(): void
    {
        $public = $this->listOf($this->owner, ListVisibility::Public, ['title' => 'Daftar Publik']);
        $friends = $this->listOf($this->owner, ListVisibility::Friends, ['title' => 'Daftar Teman']);
        $private = $this->listOf($this->owner, ListVisibility::Private, ['title' => 'Daftar Pribadi']);

        $visible = fn (User $viewer) => MediaList::visibleTo($viewer)->orderBy('id')->pluck('title')->all();

        $this->assertSame(['Daftar Publik', 'Daftar Teman', 'Daftar Pribadi'], $visible($this->owner));
        $this->assertSame(['Daftar Publik', 'Daftar Teman'], $visible($this->friend));
        $this->assertSame(['Daftar Publik'], $visible($this->stranger));

        // Halaman list yang tidak boleh dilihat diperlakukan seperti tidak ada.
        $this->actingAs($this->stranger)->get($friends->url())->assertNotFound();
        $this->actingAs($this->friend)->get($private->url())->assertNotFound();
        $this->actingAs($this->friend)->get($friends->url())->assertOk()->assertSee('Daftar Teman');
        $this->actingAs($this->stranger)->get($public->url())->assertOk();
    }

    public function test_creating_a_list_goes_on_to_the_editor(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ListEditor::class)
            ->set('title', '  Top 10 Anime 2024  ')
            ->set('description', 'Pilihan pribadi.')
            ->set('visibility', 'friends')
            ->set('isRanked', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('lists.edit', ['list' => MediaList::sole()->id]));

        $list = MediaList::sole();
        $this->assertSame('Top 10 Anime 2024', $list->title);
        $this->assertSame(ListVisibility::Friends, $list->visibility);
        $this->assertTrue($list->is_ranked);
        $this->assertStringEndsWith('/list/'.$list->id.'-top-10-anime-2024', $list->url());
    }

    public function test_list_details_are_validated(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ListEditor::class)
            ->set('title', '')
            ->set('visibility', 'rahasia')
            ->call('save')
            ->assertHasErrors(['title' => 'required', 'visibility']);
    }

    public function test_only_the_owner_can_edit(): void
    {
        $list = $this->listOf($this->owner);

        $this->actingAs($this->friend)->get(route('lists.edit', ['list' => $list->id]))->assertNotFound();
        $this->actingAs($this->owner)->get(route('lists.edit', ['list' => $list->id]))->assertOk();
    }

    public function test_titles_are_searched_and_added_from_the_editor(): void
    {
        $list = $this->listOf($this->owner);
        $media = MediaCache::factory()->film()->create(['title' => 'Interstellar']);

        Livewire::actingAs($this->owner)
            ->test(ListEditor::class, ['list' => (string) $list->id])
            ->call('addMedia', $media->id)
            ->call('addMedia', $media->id)
            ->assertSee('Interstellar')
            ->assertSee('(1 judul)');

        $this->assertSame(1, $list->items()->count());
    }

    public function test_items_can_be_reordered_and_positions_stay_compact(): void
    {
        $list = $this->listOf($this->owner);
        $this->fill($list, ['A', 'B', 'C', 'D']);
        $items = $list->items()->get();

        $editor = Livewire::actingAs($this->owner)->test(ListEditor::class, ['list' => (string) $list->id]);

        $editor->call('moveUp', $items[1]->id);
        $this->assertSame(['B', 'A', 'C', 'D'], $this->titles($list));

        $editor->call('moveDown', $items[1]->id);
        $this->assertSame(['A', 'B', 'C', 'D'], $this->titles($list));

        $editor->call('moveTo', $items[3]->id, 1);
        $this->assertSame(['D', 'A', 'B', 'C'], $this->titles($list));

        // Posisi di luar jangkauan dijepit ke ujung.
        $editor->call('moveTo', $items[3]->id, 99);
        $this->assertSame(['A', 'B', 'C', 'D'], $this->titles($list));

        $editor->call('removeItem', $items[1]->id);
        $this->assertSame(['A', 'C', 'D'], $this->titles($list));
        $this->assertSame([1, 2, 3], $list->items()->pluck('position')->all());
    }

    public function test_notes_are_saved_when_the_field_is_left(): void
    {
        $list = $this->listOf($this->owner);
        $this->fill($list, ['Interstellar']);
        $item = $list->items()->sole();

        Livewire::actingAs($this->owner)
            ->test(ListEditor::class, ['list' => (string) $list->id])
            ->set("notes.{$item->id}", '  Tonton di bioskop IMAX.  ')
            ->assertHasNoErrors();

        $this->assertSame('Tonton di bioskop IMAX.', $item->fresh()->note);

        Livewire::actingAs($this->owner)
            ->test(ListEditor::class, ['list' => (string) $list->id])
            ->set("notes.{$item->id}", str_repeat('a', MediaListItem::MAX_NOTE_LENGTH + 1))
            ->assertHasErrors("notes.{$item->id}");
    }

    public function test_the_list_page_shows_ranks_and_notes_in_order(): void
    {
        $list = $this->listOf($this->owner, attributes: ['title' => 'Top 3', 'is_ranked' => true]);
        $this->fill($list, ['Pertama', 'Kedua', 'Ketiga']);
        $list->items()->first()->update(['note' => 'Tak tertandingi.']);

        Livewire::actingAs($this->friend)
            ->test(ListShow::class, ['list' => $list->id.'-top-3'])
            ->assertSeeInOrder(['Top 3', 'Budi', 'Pertama', 'Tak tertandingi.', 'Kedua', 'Ketiga'])
            ->assertSeeHtml('min-w-7');
    }

    public function test_titles_can_be_added_from_the_detail_page(): void
    {
        $media = MediaCache::factory()->film()->create(['title' => 'Interstellar']);
        $list = $this->listOf($this->owner, attributes: ['title' => 'Favorit Nolan']);
        $this->listOf($this->friend, attributes: ['title' => 'List orang lain']);

        $component = Livewire::actingAs($this->owner)
            ->test(AddToList::class, ['media' => $media])
            ->assertSee('Favorit Nolan')
            ->assertDontSee('List orang lain')
            ->call('toggle', $list->id)
            ->assertSee('(ada di 1)');

        $this->assertSame(['Interstellar'], $this->titles($list));

        $component->call('toggle', $list->id);
        $this->assertSame([], $this->titles($list));

        $component->set('newTitle', 'Wajib ditonton')->call('createAndAdd')->assertHasNoErrors();
        $this->assertSame(['Interstellar'], $this->titles(MediaList::where('title', 'Wajib ditonton')->sole()));
    }

    public function test_someone_elses_list_cannot_be_changed_from_the_detail_page(): void
    {
        $media = MediaCache::factory()->film()->create();
        $theirs = $this->listOf($this->friend);

        Livewire::actingAs($this->owner)->test(AddToList::class, ['media' => $media])->call('toggle', $theirs->id);

        $this->assertSame(0, $theirs->items()->count());
    }

    public function test_the_detail_page_has_the_add_to_list_button(): void
    {
        $media = MediaCache::factory()->film()->create();

        $this->actingAs($this->owner)->get($media->url())->assertOk()->assertSeeLivewire(AddToList::class)->assertSee('Tambah ke list');
    }

    public function test_others_can_like_a_list_but_the_owner_cannot(): void
    {
        $list = $this->listOf($this->owner);

        Livewire::actingAs($this->friend)
            ->test(ListShow::class, ['list' => (string) $list->id])
            ->call('toggleLike')
            ->assertSee('♥ Disukai · 1')
            ->call('toggleLike')
            ->assertSee('♡ Suka · 0');

        Livewire::actingAs($this->owner)
            ->test(ListShow::class, ['list' => (string) $list->id])
            ->assertDontSee('♡ Suka')
            ->call('toggleLike')
            ->assertForbidden();
    }

    public function test_a_list_can_be_copied_as_a_private_list_without_notes(): void
    {
        $list = $this->listOf($this->owner, attributes: ['title' => 'Top Ghibli', 'is_ranked' => true]);
        $this->fill($list, ['Spirited Away', 'Mononoke']);
        $list->items()->first()->update(['note' => 'Catatan Budi']);

        Livewire::actingAs($this->friend)
            ->test(ListShow::class, ['list' => (string) $list->id])
            ->call('copy');

        $copy = $this->friend->mediaLists()->sole();

        $this->assertSame('Top Ghibli (salinan)', $copy->title);
        $this->assertSame(ListVisibility::Private, $copy->visibility);
        $this->assertTrue($copy->is_ranked);
        $this->assertSame(['Spirited Away', 'Mononoke'], $this->titles($copy));
        $this->assertSame([null, null], $copy->items()->pluck('note')->all());

        Livewire::actingAs($this->friend)
            ->test(ListShow::class, ['list' => (string) $copy->id])
            ->assertSee('Disalin dari')
            ->assertSee('Top Ghibli');
    }

    public function test_a_list_cannot_be_copied_by_someone_who_cannot_see_it(): void
    {
        $list = $this->listOf($this->owner, ListVisibility::Friends);

        $this->assertFalse($this->stranger->can('copy', $list));
        $this->assertFalse($this->owner->can('copy', $list));
    }

    public function test_viewers_can_comment_and_owners_can_moderate(): void
    {
        $list = $this->listOf($this->owner);

        Livewire::actingAs($this->stranger)
            ->test(ListShow::class, ['list' => (string) $list->id])
            ->set('commentBody', 'List yang mantap!')
            ->call('postComment')
            ->assertHasNoErrors()
            ->assertSee('List yang mantap!');

        $comment = $list->comments()->sole();

        Livewire::actingAs($this->friend)
            ->test(ListShow::class, ['list' => (string) $list->id])
            ->call('deleteComment', $comment->id)
            ->assertForbidden();

        Livewire::actingAs($this->owner)
            ->test(ListShow::class, ['list' => (string) $list->id])
            ->call('deleteComment', $comment->id);

        $this->assertModelMissing($comment);
    }

    public function test_the_owner_can_delete_a_list(): void
    {
        $list = $this->listOf($this->owner);
        $this->fill($list, ['A']);

        Livewire::actingAs($this->owner)
            ->test(ListShow::class, ['list' => (string) $list->id])
            ->call('deleteList')
            ->assertRedirect(route('lists.index', $this->owner));

        $this->actingAs($this->owner)->get(route('lists.index', $this->owner))
            ->assertRedirect(route('profile.show', ['user' => $this->owner, 'daftar' => 'list']));

        $this->assertModelMissing($list);
        $this->assertSame(0, MediaListItem::count());
    }

    public function test_profile_and_index_only_show_lists_the_viewer_may_see(): void
    {
        $this->listOf($this->owner, ListVisibility::Public, ['title' => 'Daftar Publik']);
        $this->listOf($this->owner, ListVisibility::Friends, ['title' => 'Daftar Teman']);
        $this->listOf($this->owner, ListVisibility::Private, ['title' => 'Daftar Pribadi']);

        Livewire::actingAs($this->stranger)
            ->withQueryParams(['daftar' => 'list'])
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Daftar Publik')
            ->assertDontSee('Daftar Teman')
            ->assertDontSee('Daftar Pribadi');

        Livewire::actingAs($this->owner)
            ->withQueryParams(['daftar' => 'list'])
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSee('Daftar Publik')
            ->assertSee('Daftar Teman')
            ->assertSee('Daftar Pribadi')
            ->assertSee('Buat list');
    }

    public function test_the_list_tab_sits_next_to_diary_for_friends(): void
    {
        $this->listOf($this->owner, attributes: ['title' => 'Daftar Publik']);

        Livewire::actingAs($this->friend)
            ->test(UserProfile::class, ['user' => $this->owner])
            ->assertSeeInOrder(['Sudah Ditonton', 'Watchlist', 'Diary', 'List', '(1)'])
            ->call('selectTab', 'list')
            ->assertSet('tab', 'list')
            ->assertSee('Daftar Publik');
    }

    public function test_strangers_only_get_the_list_tab(): void
    {
        $this->listOf($this->owner, attributes: ['title' => 'Daftar Publik']);
        $this->listOf($this->owner, ListVisibility::Friends, ['title' => 'Daftar Teman']);

        Livewire::actingAs($this->stranger)
            ->test(UserProfile::class, ['user' => $this->owner])
            // Tab lain terkunci, jadi List langsung terbuka.
            ->assertSet('tab', 'list')
            ->assertSee('Riwayat tontonan disembunyikan')
            ->assertSeeInOrder(['List', '(1)'])
            ->assertDontSee('Diary')
            ->assertSee('Daftar Publik')
            ->assertDontSee('Daftar Teman')
            ->call('selectTab', 'diary')
            ->assertSet('tab', 'list');
    }

    public function test_the_editor_supports_drag_and_drop(): void
    {
        $list = $this->listOf($this->owner);
        $this->fill($list, ['A', 'B']);

        Livewire::actingAs($this->owner)
            ->test(ListEditor::class, ['list' => (string) $list->id])
            ->assertSeeHtml('x-data="sortableList(\'moveTo\')"')
            ->assertSeeHtml('data-sort-id="'.$list->items()->first()->id.'"')
            ->assertSeeHtml('data-sort-handle');

        $this->assertStringContainsString("Alpine.data('sortableList'", file_get_contents(resource_path('js/app.js')));
    }

    public function test_lists_have_size_limits(): void
    {
        $list = $this->listOf($this->owner);
        $service = app(MediaListService::class);

        MediaListItem::insert(collect(range(1, MediaList::MAX_ITEMS))->map(fn ($i) => [
            'media_list_id' => $list->id,
            'media_cache_id' => MediaCache::factory()->create()->id,
            'position' => $i,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        $this->expectException(ValidationException::class);
        $service->add($list, MediaCache::factory()->create());
    }
}
