<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Lonceng di navigasi: jumlah notifikasi belum dibaca dan daftar terbarunya.
 * Diperiksa ulang tiap menit lewat wire:poll.
 */
class NotificationBell extends Component
{
    public const LIMIT = 10;

    public function render()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->limit(self::LIMIT)->get();

        return view('livewire.notification-bell', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'actors' => User::query()
                ->whereIn('id', $notifications->pluck('data.actor_id')->filter()->unique())
                ->get(['id', 'name', 'username', 'avatar_path'])
                ->keyBy('id'),
        ]);
    }

    /**
     * Tandai dibaca lalu buka halaman yang dimaksud notifikasi.
     */
    public function open(string $id): void
    {
        $notification = Auth::user()->notifications()->find($id);

        if (! $notification) {
            return;
        }

        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('dashboard'), navigate: true);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }
}
