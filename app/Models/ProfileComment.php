<?php

namespace App\Models;

use Database\Factories\ProfileCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['commenter_id', 'profile_user_id', 'parent_id', 'body'])]
class ProfileComment extends Model
{
    /** @use HasFactory<ProfileCommentFactory> */
    use HasFactory;

    public const MAX_LENGTH = 1000;

    public function commenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commenter_id');
    }

    public function profileUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profile_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Balasan, urut kronologis seperti percakapan.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest()->oldest('id');
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
