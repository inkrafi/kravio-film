<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['commenter_id', 'profile_user_id', 'body'])]
class ProfileComment extends Model
{
    public function commenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commenter_id');
    }

    public function profileUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profile_user_id');
    }
}
