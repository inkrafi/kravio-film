<?php

namespace App\Enums;

enum MediaType: string
{
    case Film = 'film';
    case Series = 'series';
    case Anime = 'anime';

    public function label(): string
    {
        return match ($this) {
            self::Film => 'Film',
            self::Series => 'Series',
            self::Anime => 'Anime',
        };
    }

    /**
     * Tailwind classes untuk badge pembeda di kartu hasil search.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Film => 'bg-sky-500/15 text-sky-300 ring-sky-500/30',
            self::Series => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
            self::Anime => 'bg-fuchsia-500/15 text-fuchsia-300 ring-fuchsia-500/30',
        };
    }
}
