<?php

namespace App\Notifications;

use App\Models\ProfileComment;
use App\Models\User;

class ProfileCommentPosted extends ActivityNotification
{
    public function __construct(public readonly ProfileComment $comment)
    {
        parent::__construct($comment->commenter);
    }

    protected function message(User $notifiable): string
    {
        return 'mengomentari profilmu: '.$this->excerpt($this->comment->body);
    }

    protected function url(User $notifiable): string
    {
        return $this->profileUrl($notifiable);
    }
}
