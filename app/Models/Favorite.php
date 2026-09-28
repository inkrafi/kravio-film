<?php

namespace App\Models;

use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'media_cache_id', 'sort_order'])]
class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory;

    /**
     * Favorit di profil dibatasi empat kartu, mengikuti kebiasaan Letterboxd —
     * daftar pendek memaksa pilihan yang berarti.
     */
    public const MAX_PER_USER = 4;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaCache::class, 'media_cache_id');
    }
}
