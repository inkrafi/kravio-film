<?php

namespace App\Livewire;

use App\Models\ProfileComment;
use App\Models\User;
use App\Notifications\ProfileCommentPosted;
use App\Notifications\ProfileCommentReplied;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Kolom komentar di profil. Komponen terpisah dari UserProfile supaya polling
 * komentar baru (tanpa reload) tidak ikut me-render ulang seluruh profil.
 *
 * Teman menulis komentar; pemilik profil hanya membalas. Balasan satu tingkat:
 * membalas sebuah balasan tetap masuk ke utas komentar induknya.
 */
class ProfileComments extends Component
{
    public const PAGE_SIZE = 20;

    /** Jeda polling komentar baru, dalam detik. */
    public const POLL_SECONDS = 15;

    private const MAX_PER_MINUTE = 5;

    #[Locked]
    public User $user;

    public string $body = '';

    /** Komentar induk yang sedang dibalas, atau null. */
    public ?int $replyingTo = null;

    public string $replyBody = '';

    #[Locked]
    public int $limit = self::PAGE_SIZE;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function render()
    {
        $canView = Gate::allows('viewAny', [ProfileComment::class, $this->user]);

        // Halaman dihitung per komentar induk; balasannya selalu ikut lengkap.
        $comments = $canView
            ? $this->user->profileComments()
                ->topLevel()
                ->with(['commenter:id,name,username,avatar_path', 'replies.commenter:id,name,username,avatar_path'])
                ->latest()
                ->latest('id')
                ->take($this->limit + 1)
                ->get()
            : collect();

        // Relasi yang dipakai view/policy diisi dari data yang sudah ada, supaya
        // tidak ada query per komentar.
        $comments->each(function (ProfileComment $comment) {
            $comment->setRelation('profileUser', $this->user);
            $comment->replies->each(fn (ProfileComment $reply) => $reply
                ->setRelation('parent', $comment)
                ->setRelation('profileUser', $this->user));
        });

        return view('livewire.profile-comments', [
            'canView' => $canView,
            'canComment' => Gate::allows('create', [ProfileComment::class, $this->user]),
            // Sama untuk semua komentar di profil ini (lihat ProfileCommentPolicy::reply).
            'canReply' => $canView,
            'isOwner' => Auth::id() === $this->user->id,
            'comments' => $comments->take($this->limit),
            'hasMore' => $comments->count() > $this->limit,
            'total' => $canView ? $this->user->profileComments()->count() : 0,
        ]);
    }

    public function post(): void
    {
        Gate::authorize('create', [ProfileComment::class, $this->user]);

        $this->validate([
            'body' => ['required', 'string', 'max:'.ProfileComment::MAX_LENGTH],
        ], attributes: ['body' => 'komentar']);

        if (! $this->withinRateLimit('body')) {
            return;
        }

        $comment = $this->user->profileComments()->create([
            'commenter_id' => Auth::id(),
            'body' => trim($this->body),
        ]);

        if ($this->user->id !== Auth::id()) {
            $this->user->notify(new ProfileCommentPosted($comment));
        }

        $this->reset('body');
    }

    public function startReply(int $commentId): void
    {
        $this->replyingTo = $commentId;
        $this->replyBody = '';
        $this->resetValidation('replyBody');
    }

    public function cancelReply(): void
    {
        $this->reset('replyingTo', 'replyBody');
        $this->resetValidation('replyBody');
    }

    public function postReply(): void
    {
        $parent = $this->user->profileComments()->find($this->replyingTo);

        if (! $parent) {
            $this->cancelReply();

            return;
        }

        // Balasan untuk balasan masuk ke utas komentar induknya.
        $parent = $parent->parent_id ? $parent->parent : $parent;

        Gate::authorize('reply', $parent);

        $this->validate([
            'replyBody' => ['required', 'string', 'max:'.ProfileComment::MAX_LENGTH],
        ], attributes: ['replyBody' => 'balasan']);

        if (! $this->withinRateLimit('replyBody')) {
            return;
        }

        $reply = $this->user->profileComments()->create([
            'commenter_id' => Auth::id(),
            'parent_id' => $parent->id,
            'body' => trim($this->replyBody),
        ]);

        // Pemilik profil dan penulis komentar induk, kecuali yang membalas sendiri.
        User::query()
            ->whereIn('id', [$this->user->id, $parent->commenter_id])
            ->whereKeyNot(Auth::id())
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(new ProfileCommentReplied($reply)));

        $this->reset('replyingTo', 'replyBody');
    }

    public function delete(int $commentId): void
    {
        $comment = $this->user->profileComments()->find($commentId);

        if (! $comment) {
            return;
        }

        Gate::authorize('delete', $comment);

        // Balasannya ikut terhapus (cascade di database).
        $comment->delete();
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    /**
     * Komentar dan balasan berbagi batas yang sama.
     */
    private function withinRateLimit(string $field): bool
    {
        $key = 'profile-comment:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_MINUTE)) {
            $this->addError($field, 'Terlalu banyak komentar. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.');

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
}
