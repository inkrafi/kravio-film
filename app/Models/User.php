<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\FriendshipStatus;
use App\Services\AvatarService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'username', 'email', 'password', 'bio', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function watchEntries(): HasMany
    {
        return $this->hasMany(WatchEntry::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function aiInsights(): HasMany
    {
        return $this->hasMany(AiInsight::class);
    }

    public function mediaLists(): HasMany
    {
        return $this->hasMany(MediaList::class);
    }

    /**
     * Komentar yang ditinggalkan orang lain di profil user ini.
     */
    public function profileComments(): HasMany
    {
        return $this->hasMany(ProfileComment::class, 'profile_user_id');
    }

    /**
     * Permintaan pertemanan yang dikirim user ini.
     */
    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    /**
     * Permintaan pertemanan yang masuk ke user ini.
     */
    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    /**
     * Baris pertemanan antara user ini dan user lain, arah mana pun.
     */
    public function friendshipWith(User|int $other): ?Friendship
    {
        $otherId = $other instanceof User ? $other->id : $other;

        if ($otherId === $this->id) {
            return null;
        }

        return Friendship::query()->between($this->id, $otherId)->first();
    }

    public function isFriendsWith(User|int $other): bool
    {
        return $this->friendshipWith($other)?->status === FriendshipStatus::Accepted;
    }

    /**
     * Profil sendiri selalu terbuka; profil orang lain terbuka kalau sudah berteman.
     */
    public function canViewLibraryOf(User $owner): bool
    {
        return $this->id === $owner->id || $this->isFriendsWith($owner);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk(AvatarService::disk())->url($this->avatar_path) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }
}
