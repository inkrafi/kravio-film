<?php

namespace App\Models;

use App\Enums\WatchStatus;
use Database\Factories\WatchEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'media_cache_id', 'status', 'rating', 'watched_at'])]
class WatchEntry extends Model
{
    /** @use HasFactory<WatchEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => WatchStatus::class,
            'watched_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaCache::class, 'media_cache_id');
    }

    public function scopeWatched(Builder $query): Builder
    {
        return $query->where('status', WatchStatus::Watched);
    }

    public function scopeWatchlist(Builder $query): Builder
    {
        return $query->where('status', WatchStatus::Watchlist);
    }
}
