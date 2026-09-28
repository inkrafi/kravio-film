<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['media_list_id', 'user_id', 'body'])]
class MediaListComment extends Model
{
    public const MAX_LENGTH = 1000;

    public function list(): BelongsTo
    {
        return $this->belongsTo(MediaList::class, 'media_list_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
