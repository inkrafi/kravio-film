<?php

namespace App\Models;

use App\Enums\FriendshipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'friend_id', 'status', 'accepted_at'])]
class Friendship extends Model
{
    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Pengirim permintaan pertemanan.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Penerima permintaan pertemanan.
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', FriendshipStatus::Accepted);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', FriendshipStatus::Pending);
    }

    /**
     * Baris pertemanan yang melibatkan dua user, arah mana pun.
     */
    public function scopeBetween(Builder $query, int $userId, int $otherId): Builder
    {
        return $query->where(function (Builder $q) use ($userId, $otherId) {
            $q->where(fn (Builder $inner) => $inner->where('user_id', $userId)->where('friend_id', $otherId))
                ->orWhere(fn (Builder $inner) => $inner->where('user_id', $otherId)->where('friend_id', $userId));
        });
    }
}
