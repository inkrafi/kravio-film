<?php

namespace App\Services;

use App\Enums\FriendshipState;
use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Semua aturan main pertemanan ada di sini, supaya komponen Livewire cukup
 * memanggil satu method dan tidak menduplikasi pengecekan arah relasi.
 *
 * Tabel friendships menyimpan satu baris berarah (user_id -> friend_id).
 * Pertemanan yang sudah diterima tetap satu baris; arahnya hanya menandakan
 * siapa yang dulu mengirim permintaan.
 */
class FriendshipService
{
    /**
     * @throws ValidationException
     */
    public function sendRequest(User $from, User $to): Friendship
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'friendship' => 'Kamu tidak bisa berteman dengan diri sendiri.',
            ]);
        }

        $existing = Friendship::query()->between($from->id, $to->id)->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'friendship' => $existing->status === FriendshipStatus::Accepted
                    ? 'Kalian sudah berteman.'
                    : 'Permintaan pertemanan sudah ada.',
            ]);
        }

        return Friendship::create([
            'user_id' => $from->id,
            'friend_id' => $to->id,
            'status' => FriendshipStatus::Pending,
        ]);
    }

    /**
     * Hanya penerima permintaan yang boleh menerima.
     *
     * @throws AuthorizationException
     */
    public function accept(User $actor, Friendship $friendship): void
    {
        if ($friendship->friend_id !== $actor->id || $friendship->status !== FriendshipStatus::Pending) {
            throw new AuthorizationException('Permintaan pertemanan ini bukan milikmu.');
        }

        $friendship->update([
            'status' => FriendshipStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Penerima menolak permintaan yang masuk.
     *
     * @throws AuthorizationException
     */
    public function reject(User $actor, Friendship $friendship): void
    {
        if ($friendship->friend_id !== $actor->id || $friendship->status !== FriendshipStatus::Pending) {
            throw new AuthorizationException('Permintaan pertemanan ini bukan milikmu.');
        }

        $friendship->delete();
    }

    /**
     * Pengirim membatalkan permintaan yang belum dijawab.
     *
     * @throws AuthorizationException
     */
    public function cancel(User $actor, Friendship $friendship): void
    {
        if ($friendship->user_id !== $actor->id || $friendship->status !== FriendshipStatus::Pending) {
            throw new AuthorizationException('Permintaan pertemanan ini bukan milikmu.');
        }

        $friendship->delete();
    }

    /**
     * Memutus pertemanan; boleh dilakukan kedua belah pihak.
     */
    public function remove(User $actor, User $other): void
    {
        Friendship::query()
            ->between($actor->id, $other->id)
            ->accepted()
            ->delete();
    }

    public function stateBetween(User $viewer, User $other): FriendshipState
    {
        if ($viewer->id === $other->id) {
            return FriendshipState::Self;
        }

        $friendship = Friendship::query()->between($viewer->id, $other->id)->first();

        if (! $friendship) {
            return FriendshipState::None;
        }

        if ($friendship->status === FriendshipStatus::Accepted) {
            return FriendshipState::Friends;
        }

        return $friendship->user_id === $viewer->id
            ? FriendshipState::PendingOutgoing
            : FriendshipState::PendingIncoming;
    }

    /**
     * Baris pertemanan antara dua user, arah mana pun — dipakai komponen untuk
     * mengambil target aksi terima/tolak/batal.
     */
    public function between(User $a, User $b): ?Friendship
    {
        return Friendship::query()->between($a->id, $b->id)->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function friendsOf(User $user): Collection
    {
        return User::query()
            ->whereIn('id', $this->friendIdsOf($user))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<int>
     */
    public function friendIdsOf(User $user): array
    {
        return Friendship::query()
            ->accepted()
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('friend_id', $user->id))
            ->get(['user_id', 'friend_id'])
            ->map(fn (Friendship $row) => $row->user_id === $user->id ? $row->friend_id : $row->user_id)
            ->values()
            ->all();
    }

    /**
     * Permintaan yang menunggu jawaban user ini.
     *
     * @return Collection<int, Friendship>
     */
    public function incomingRequests(User $user): Collection
    {
        return Friendship::query()
            ->pending()
            ->where('friend_id', $user->id)
            ->with('requester')
            ->latest()
            ->get();
    }

    /**
     * Permintaan yang dikirim user ini dan belum dijawab.
     *
     * @return Collection<int, Friendship>
     */
    public function outgoingRequests(User $user): Collection
    {
        return Friendship::query()
            ->pending()
            ->where('user_id', $user->id)
            ->with('addressee')
            ->latest()
            ->get();
    }
}
