<?php

namespace App\Models;

use App\Enums\ListVisibility;
use App\Services\FriendshipService;
use Database\Factories\MediaListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'title', 'description', 'visibility', 'is_ranked', 'copied_from_id'])]
class MediaList extends Model
{
    /** @use HasFactory<MediaListFactory> */
    use HasFactory;

    public const MAX_ITEMS = 500;

    public const MAX_PER_USER = 100;

    protected function casts(): array
    {
        return [
            'visibility' => ListVisibility::class,
            'is_ranked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Isi list sesuai urutan yang disusun pemiliknya.
     */
    public function items(): HasMany
    {
        return $this->hasMany(MediaListItem::class)->orderBy('position')->orderBy('id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(MediaListLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MediaListComment::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_id');
    }

    /**
     * `/list/12-top-10-anime-2024` — ID yang menentukan, slug hanya pemanis
     * dan boleh berubah saat judul diganti.
     */
    public function url(): string
    {
        $slug = Str::slug($this->title);

        return route('lists.show', ['list' => $slug !== '' ? "{$this->id}-{$slug}" : (string) $this->id]);
    }

    public static function findByRouteKey(string $key): ?self
    {
        return preg_match('/^(\d+)(?:-[a-z0-9-]*)?$/', $key, $matches)
            ? static::find((int) $matches[1])
            : null;
    }

    /**
     * Data untuk kartu list: pemilik, jumlah judul & like, dan poster pertama.
     */
    public function scopeWithCardData(Builder $query): Builder
    {
        return $query
            ->with(['user:id,name,username,avatar_path', 'items' => fn ($items) => $items->with('media:id,title,poster_url')->limit(5)])
            ->withCount(['items', 'likes']);
    }

    /**
     * List yang boleh dilihat $viewer: publik, milik sendiri, atau "teman saja"
     * milik teman yang sudah diterima.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        $friendIds = app(FriendshipService::class)->friendIdsOf($viewer);

        return $query->where(fn (Builder $query) => $query
            ->where('user_id', $viewer->id)
            ->orWhere('visibility', ListVisibility::Public->value)
            ->orWhere(fn (Builder $query) => $query
                ->where('visibility', ListVisibility::Friends->value)
                ->whereIn('user_id', $friendIds)));
    }
}
