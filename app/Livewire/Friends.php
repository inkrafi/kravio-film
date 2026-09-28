<?php

namespace App\Livewire;

use App\Models\Friendship;
use App\Models\User;
use App\Services\FriendshipService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Satu tempat untuk mengurus pertemanan: permintaan masuk, permintaan yang
 * dikirim, daftar teman, dan pencarian pengguna baru.
 */
#[Layout('layouts.app')]
#[Title('Teman')]
class Friends extends Component
{
    private const SEARCH_LIMIT = 10;

    private const MIN_QUERY_LENGTH = 2;

    public string $query = '';

    public function render(FriendshipService $friendships)
    {
        return view('livewire.friends', [
            'incoming' => $friendships->incomingRequests(Auth::user()),
            'outgoing' => $friendships->outgoingRequests(Auth::user()),
            'friends' => $friendships->friendsOf(Auth::user()),
            'matches' => $this->searchUsers($friendships),
        ]);
    }

    public function accept(int $friendshipId, FriendshipService $friendships): void
    {
        $friendships->accept(Auth::user(), Friendship::findOrFail($friendshipId));
    }

    public function reject(int $friendshipId, FriendshipService $friendships): void
    {
        $friendships->reject(Auth::user(), Friendship::findOrFail($friendshipId));
    }

    public function cancel(int $friendshipId, FriendshipService $friendships): void
    {
        $friendships->cancel(Auth::user(), Friendship::findOrFail($friendshipId));
    }

    public function remove(int $userId, FriendshipService $friendships): void
    {
        $friendships->remove(Auth::user(), User::findOrFail($userId));
    }

    public function add(int $userId, FriendshipService $friendships): void
    {
        try {
            $friendships->sendRequest(Auth::user(), User::findOrFail($userId));
        } catch (ValidationException $e) {
            $this->addError('friendship', $e->getMessage());
        }
    }

    /**
     * Pengguna yang belum punya relasi apa pun dengan kita — yang sudah jadi
     * teman atau sudah dikirimi permintaan tidak perlu muncul lagi di sini.
     *
     * @return Collection<int, User>
     */
    private function searchUsers(FriendshipService $friendships): Collection
    {
        $query = trim($this->query);

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return new Collection;
        }

        $related = Friendship::query()
            ->where('user_id', Auth::id())
            ->orWhere('friend_id', Auth::id())
            ->get(['user_id', 'friend_id'])
            ->flatMap(fn (Friendship $row) => [$row->user_id, $row->friend_id])
            ->push(Auth::id())
            ->unique()
            ->all();

        return User::query()
            ->whereNotIn('id', $related)
            ->where(fn ($builder) => $builder
                ->where('username', 'ilike', "%{$query}%")
                ->orWhere('name', 'ilike', "%{$query}%"))
            ->orderBy('name')
            ->take(self::SEARCH_LIMIT)
            ->get();
    }
}
