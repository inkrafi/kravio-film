<?php

namespace App\Notifications;

use App\Models\User;

class FriendRequestReceived extends ActivityNotification
{
    protected function message(User $notifiable): string
    {
        return 'mengirim permintaan pertemanan';
    }

    protected function url(User $notifiable): string
    {
        return route('friends');
    }
}
