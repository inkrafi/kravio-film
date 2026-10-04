<?php

namespace App\Notifications;

use App\Models\ProfileComment;
use App\Models\User;

/**
 * Dikirim ke pemilik profil dan ke penulis komentar yang dibalas.
 */
class ProfileCommentReplied extends ActivityNotification
{
    public function __construct(public readonly ProfileComment $reply)
    {
        parent::__construct($reply->commenter);
    }

    protected function message(User $notifiable): string
    {
        $where = $this->reply->profile_user_id === $notifiable->id
            ? 'di profilmu'
            : 'di profil '.$this->reply->profileUser->name;

        return "membalas komentar {$where}: ".$this->excerpt($this->reply->body);
    }

    protected function url(User $notifiable): string
    {
        return $this->profileUrl($this->reply->profileUser);
    }
}
