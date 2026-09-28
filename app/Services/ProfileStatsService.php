<?php

namespace App\Services;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\User;
use App\Support\GenreNormalizer;

/**
 * Statistik profil dari riwayat watched: total, jumlah film/series/anime,
 * rata-rata rating, dan genre yang paling sering ditonton.
 */
class ProfileStatsService
{
    public const TOP_GENRES = 5;

    /**
     * @return array{
     *     total: int,
     *     films: int,
     *     series: int,
     *     anime: int,
     *     this_year: int,
     *     average_rating: ?float,
     *     rated: int,
     *     genres: list<array{name: string, count: int, share: float}>
     * }
     */
    public function for(User $user): array
    {
        $entries = $user->watchEntries()
            ->watched()
            ->with('media:id,media_type,source,genres,raw_payload')
            ->get(['id', 'media_cache_id', 'rating', 'watched_at']);

        // Anime dihitung terpisah, jadi film + series + anime = total.
        [$anime, $nonAnime] = $entries->partition(fn ($entry) => $entry->media->isAnime());

        $rated = $entries->whereNotNull('rating');

        $genreCounts = collect($this->countGenres($entries));

        $topGenres = $genreCounts->take(self::TOP_GENRES);

        return [
            'total' => $entries->count(),
            'films' => $nonAnime->filter(fn ($entry) => $entry->media->media_type === MediaType::Film)->count(),
            'series' => $nonAnime->filter(fn ($entry) => $entry->media->media_type === MediaType::Series)->count(),
            'anime' => $anime->count(),
            'this_year' => $entries->filter(fn ($entry) => $entry->watched_at?->isCurrentYear())->count(),
            'average_rating' => $rated->isEmpty() ? null : round($rated->avg('rating'), 1),
            'rated' => $rated->count(),
            // share = porsi judul watched yang punya genre itu; satu judul bisa
            // punya beberapa genre, jadi totalnya boleh lebih dari 100%.
            'genres' => $topGenres
                ->map(fn (int $count, string $name) => [
                    'name' => $name,
                    'count' => $count,
                    'share' => $count / max($entries->count(), 1),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Jumlah judul watched per genre (sudah dinormalisasi), terbanyak dulu.
     * Dipakai juga untuk insight AI dan kemiripan selera antar-teman.
     *
     * @return array<string, int>
     */
    public function genreCounts(User $user): array
    {
        return $this->countGenres(
            $user->watchEntries()->watched()->with('media:id,source,genres')->get(['id', 'media_cache_id']),
        );
    }

    /**
     * @return array<string, int>
     */
    private function countGenres($entries): array
    {
        return $entries
            ->flatMap(fn ($entry) => $this->genresOf($entry->media))
            ->countBy()
            ->sortDesc()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function genresOf($media): array
    {
        $genres = GenreNormalizer::normalize($media->genres);

        // AniList/Jikan tidak mencantumkan "Animasi" seperti TMDB; tanpa ini anime
        // yang sama bisa terhitung beda tergantung sumbernya.
        if (in_array($media->source, [MediaSource::Anilist, MediaSource::Jikan], strict: true) && ! in_array('Animasi', $genres, strict: true)) {
            $genres[] = 'Animasi';
        }

        return $genres;
    }
}
