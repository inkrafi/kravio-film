<?php

namespace App\Enums;

enum MediaSource: string
{
    case Tmdb = 'tmdb';
    case Jikan = 'jikan';
    case Anilist = 'anilist';

    public function label(): string
    {
        return match ($this) {
            self::Tmdb => 'TMDB',
            self::Jikan => 'MyAnimeList',
            self::Anilist => 'AniList',
        };
    }

    /**
     * Nama yang bisa dibaca pengguna, untuk daftar sumber yang gagal/dilewati.
     *
     * @param  list<string>  $sources
     */
    public static function labels(array $sources): string
    {
        return implode(', ', array_map(
            fn (string $source) => self::tryFrom($source)?->label() ?? $source,
            $sources,
        ));
    }
}
