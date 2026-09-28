<?php

namespace App\Services\Insights;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\MediaCache;
use App\Models\User;
use App\Services\Media\GenreBrowseService;
use App\Services\Media\MediaSearchService;
use App\Services\Media\Providers\TmdbProvider;
use App\Services\ProfileStatsService;
use App\Support\GenreNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kandidat rekomendasi personal. Semuanya judul nyata dari TMDB/AniList yang
 * sudah tersimpan di media_cache — Gemini hanya memilih dan menjelaskan dari
 * daftar ini, tidak mengarang judul.
 *
 * Sumber kandidat:
 * 1. Rekomendasi TMDB untuk judul yang paling disukai pengguna (rating >= 8).
 * 2. Judul populer di genre favoritnya (termasuk anime dari AniList).
 */
class RecommendationService
{
    public const MAX_CANDIDATES = 24;

    private const MAX_SEEDS = 3;

    private const SEED_MIN_RATING = 8;

    private const TOP_GENRES = 2;

    public function __construct(
        private readonly TmdbProvider $tmdb,
        private readonly MediaSearchService $media,
        private readonly GenreBrowseService $genres,
        private readonly ProfileStatsService $stats,
    ) {}

    /**
     * @return Collection<int, array{media: MediaCache, because: ?string, score: float}>
     */
    public function candidates(User $user): Collection
    {
        $known = $user->watchEntries()->pluck('media_cache_id')->all();
        $topGenres = array_slice(array_keys($this->stats->genreCounts($user)), 0, self::TOP_GENRES);

        /** @var array<int, array{media: MediaCache, because: ?string, score: float}> $pool */
        $pool = [];

        $add = function (MediaCache $media, float $score, ?string $because) use (&$pool, $known) {
            if (in_array($media->id, $known, true)) {
                return;
            }

            $pool[$media->id] ??= ['media' => $media, 'because' => $because, 'score' => 0.0];
            $pool[$media->id]['score'] += $score;
        };

        foreach ($this->seeds($user) as $seed) {
            try {
                $recommended = $this->media->remember($this->tmdb->recommendations($seed->media_type, $seed->external_id));
            } catch (Throwable $e) {
                Log::warning('Gagal mengambil rekomendasi TMDB.', ['seed' => $seed->sourceKey(), 'reason' => $e->getMessage()]);

                continue;
            }

            // Judul yang muncul dari beberapa seed sekaligus naik peringkatnya.
            foreach ($recommended->take(10)->values() as $rank => $media) {
                $add($media, 3.0 - $rank * 0.2, $seed->title);
            }
        }

        foreach ($topGenres as $genreName) {
            $genre = GenreNormalizer::findBySlug(GenreNormalizer::slug($genreName));

            if ($genre === null) {
                continue;
            }

            foreach (MediaType::cases() as $type) {
                foreach ($this->genres->page($genre, $type, 1)['media']->take(8)->values() as $rank => $media) {
                    $add($media, 1.5 - $rank * 0.1, null);
                }
            }
        }

        // Bonus kecocokan genre dengan selera pengguna.
        foreach ($pool as $id => $candidate) {
            $pool[$id]['score'] += 0.5 * count(array_intersect($candidate['media']->displayGenres(), $topGenres));
        }

        return collect($pool)
            ->sortByDesc('score')
            ->take(self::MAX_CANDIDATES)
            ->values();
    }

    /**
     * Judul TMDB yang paling disukai dan paling baru ditonton.
     *
     * @return Collection<int, MediaCache>
     */
    private function seeds(User $user): Collection
    {
        if (! $this->tmdb->isConfigured()) {
            return collect();
        }

        return $user->watchEntries()
            ->watched()
            ->where('rating', '>=', self::SEED_MIN_RATING)
            ->whereHas('media', fn ($query) => $query->where('source', MediaSource::Tmdb->value))
            ->with('media')
            ->orderByDesc('rating')
            ->orderByDesc('watched_at')
            ->take(self::MAX_SEEDS)
            ->get()
            ->pluck('media');
    }
}
