<?php

namespace App\Models;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use Database\Factories\MediaCacheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'external_id', 'source', 'media_type', 'title', 'original_title',
    'poster_url', 'backdrop_url', 'synopsis', 'year', 'released_on',
    'genres', 'raw_payload', 'synced_at',
])]
class MediaCache extends Model
{
    /** @use HasFactory<MediaCacheFactory> */
    use HasFactory;

    protected $table = 'media_cache';

    protected function casts(): array
    {
        return [
            'source' => MediaSource::class,
            'media_type' => MediaType::class,
            'genres' => 'array',
            'raw_payload' => 'array',
            'released_on' => 'date',
            'synced_at' => 'datetime',
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

    public function scopeOfType(Builder $query, MediaType|string $type): Builder
    {
        return $query->where('media_type', $type instanceof MediaType ? $type->value : $type);
    }

    /**
     * Kunci unik lintas sumber, dipakai sebagai identitas media di URL & cache.
     */
    public function getSlugAttribute(): string
    {
        return "{$this->source->value}-{$this->media_type->value}-{$this->external_id}";
    }
}
