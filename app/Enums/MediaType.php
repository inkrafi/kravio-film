<?php

namespace App\Enums;

/**
 * Anime tidak punya tipe sendiri: anime movie masuk Film, anime berepisode
 * (TV, OVA, ONA, special, ...) masuk Series.
 */
enum MediaType: string
{
    case Film = 'film';
    case Series = 'series';

    public function label(): string
    {
        return match ($this) {
            self::Film => 'Film',
            self::Series => 'Series',
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
        };
    }
}
