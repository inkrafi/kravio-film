<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notifikasi di lonceng navigasi. Teks dan tautannya disimpan saat dikirim,
 * jadi menampilkannya tidak perlu memuat ulang komentar atau list terkait.
 */
abstract class ActivityNotification extends Notification
{
    protected const EXCERPT_LENGTH = 80;

    public function __construct(public readonly User $actor) {}

    /**
     * Kalimat setelah nama pelaku, mis. "mengomentari profilmu".
     */
    abstract protected function message(User $notifiable): string;

    abstract protected function url(User $notifiable): string;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{actor_id: int, actor_name: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'message' => $this->message($notifiable),
            'url' => $this->url($notifiable),
        ];
    }

    protected function excerpt(string $body): string
    {
        return '“'.Str::limit(Str::squish($body), self::EXCERPT_LENGTH).'”';
    }

    protected function profileUrl(User $user): string
    {
        return $user->username ? route('profile.show', $user) : route('friends');
    }
}
