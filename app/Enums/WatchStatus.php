<?php

namespace App\Enums;

enum WatchStatus: string
{
    case Watched = 'watched';
    case Watchlist = 'watchlist';

    public function label(): string
    {
        return match ($this) {
            self::Watched => 'Sudah Ditonton',
            self::Watchlist => 'Watchlist',
        };
    }
}
