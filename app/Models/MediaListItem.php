<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['media_list_id', 'media_cache_id', 'position', 'note'])]
class MediaListItem extends Model
{
    public const MAX_NOTE_LENGTH = 500;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(MediaList::class, 'media_list_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaCache::class, 'media_cache_id');
    }
}
