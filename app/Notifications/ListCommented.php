<?php

namespace App\Notifications;

use App\Models\MediaListComment;
use App\Models\User;

class ListCommented extends ActivityNotification
{
    public function __construct(public readonly MediaListComment $comment)
    {
        parent::__construct($comment->user);
    }

    protected function message(User $notifiable): string
    {
        return "mengomentari list “{$this->comment->list->title}”: ".$this->excerpt($this->comment->body);
    }

    protected function url(User $notifiable): string
    {
        return $this->comment->list->url();
    }
}
