<?php

namespace App\Notifications;

use App\Models\User;

class FriendRequestAccepted extends ActivityNotification
{
    protected function message(User $notifiable): string
    {
        return 'menerima permintaan pertemananmu';
    }

    protected function url(User $notifiable): string
    {
        return $this->profileUrl($this->actor);
    }
}
