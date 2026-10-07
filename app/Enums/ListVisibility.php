<?php

namespace App\Enums;

enum ListVisibility: string
{
    case Public = 'public';
    case Friends = 'friends';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Publik',
            self::Friends => 'Teman saja',
            self::Private => 'Pribadi',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Public => 'Semua pengguna Kursi Penuh bisa melihat.',
            self::Friends => 'Hanya kamu dan teman yang sudah diterima.',
            self::Private => 'Hanya kamu.',
        };
    }
}
