<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['media_list_id', 'user_id'])]
class MediaListLike extends Model
{
    public function list(): BelongsTo
    {
        return $this->belongsTo(MediaList::class, 'media_list_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
