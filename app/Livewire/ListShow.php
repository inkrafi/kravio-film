<?php

namespace App\Livewire;

use App\Models\MediaList;
use App\Models\MediaListComment;
use App\Notifications\ListCommented;
use App\Services\MediaListService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman satu list: isi berurutan + catatan, like, salin, dan komentar.
 */
#[Layout('layouts.app')]
class ListShow extends Component
{
    private const COMMENTS_PER_MINUTE = 5;

    #[Locked]
    public MediaList $mediaList;

    public string $commentBody = '';

    /**
     * @param  string  $list  "{id}-{slug}" atau "{id}".
     */
    public function mount(string $list): void
    {
        $found = MediaList::findByRouteKey($list);

        // List yang tidak boleh dilihat diperlakukan seperti tidak ada.
        abort_if($found === null || Auth::user()->cannot('view', $found), 404);

        $this->mediaList = $found;
    }

    public function render()
    {
        $list = $this->mediaList->load(['user', 'items.media', 'copiedFrom.user'])->loadCount('likes');

        return view('livewire.list-show', [
            'list' => $list,
            'liked' => $list->likes()->where('user_id', Auth::id())->exists(),
            'comments' => $list->comments()->with('user:id,name,username,avatar_path')->latest()->latest('id')->get(),
            // Sumber salinan hanya disebut kalau pengunjung boleh melihatnya.
            'copiedFrom' => $list->copiedFrom && Auth::user()->can('view', $list->copiedFrom) ? $list->copiedFrom : null,
        ])->title($list->title);
    }

    public function toggleLike(): void
    {
        $this->authorize('like', $this->mediaList);

        $like = $this->mediaList->likes()->where('user_id', Auth::id())->first();

        $like ? $like->delete() : $this->mediaList->likes()->create(['user_id' => Auth::id()]);
    }

    public function copy(MediaListService $lists): void
    {
        $this->authorize('copy', $this->mediaList);

        try {
            $copy = $lists->copy($this->mediaList, Auth::user());
        } catch (ValidationException $e) {
            $this->addError('copy', $e->getMessage());

            return;
        }

        $this->redirect(route('lists.edit', ['list' => $copy->id]), navigate: true);
    }

    public function postComment(): void
    {
        $this->authorize('comment', $this->mediaList);

        $this->validate([
            'commentBody' => ['required', 'string', 'max:'.MediaListComment::MAX_LENGTH],
        ], attributes: ['commentBody' => 'komentar']);

        $key = 'list-comment:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, self::COMMENTS_PER_MINUTE)) {
            $this->addError('commentBody', 'Terlalu banyak komentar. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.');

            return;
        }

        RateLimiter::hit($key, 60);

        $comment = $this->mediaList->comments()->create(['user_id' => Auth::id(), 'body' => trim($this->commentBody)]);

        if ($this->mediaList->user_id !== Auth::id()) {
            $this->mediaList->user->notify(new ListCommented($comment));
        }

        $this->reset('commentBody');
    }

    public function deleteComment(int $commentId): void
    {
        $comment = $this->mediaList->comments()->find($commentId);

        if ($comment) {
            Gate::authorize('deleteComment', [$this->mediaList, $comment]);
            $comment->delete();
        }
    }

    public function deleteList(): void
    {
        $this->authorize('delete', $this->mediaList);

        $owner = $this->mediaList->user;
        $this->mediaList->delete();

        $this->redirect(route('lists.index', $owner), navigate: true);
    }
}
