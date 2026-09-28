<?php

namespace App\Enums;

/**
 * Hubungan antara dua user dari sudut pandang orang yang sedang melihat.
 *
 * Berbeda dari FriendshipStatus yang hanya menyimpan pending/accepted di
 * database: state ini ikut memperhitungkan siapa yang mengirim permintaan,
 * karena tombol yang ditampilkan berbeda untuk pengirim dan penerima.
 */
enum FriendshipState: string
{
    case Self = 'self';
    case None = 'none';
    case PendingOutgoing = 'pending_outgoing';
    case PendingIncoming = 'pending_incoming';
    case Friends = 'friends';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'Ini kamu',
            self::None => 'Tambah Teman',
            self::PendingOutgoing => 'Menunggu Konfirmasi',
            self::PendingIncoming => 'Menunggu Persetujuanmu',
            self::Friends => 'Berteman',
        };
    }

    public function isFriends(): bool
    {
        return $this === self::Friends;
    }
}
