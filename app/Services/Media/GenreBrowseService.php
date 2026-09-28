<?php

namespace App\Services\Media;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\MediaCache;
use App\Services\Media\Providers\AniListProvider;
use App\Services\Media\Providers\TmdbProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Halaman /genre/{slug}: judul populer dalam satu genre, gabungan TMDB
 * (film/series) dan AniList (anime), per halaman.
 */
class GenreBrowseService
{
    private const CACHE_TTL = 60 * 60 * 6;

    /** Anime per halaman; TMDB selalu 20 per halaman. */
    private const ANIME_PER_PAGE = 10;

    public function __construct(
        private readonly TmdbProvider $tmdb,
        private readonly AniListProvider $anilist,
        private readonly MediaSearchService $media,
    ) {}

    /**
     * @param  array{name: string, slug: string, tmdb_movie: ?int, tmdb_tv: ?int, anilist: ?string}  $genre
     * @return array{media: Collection<int, MediaCache>, has_more: bool, failed: list<string>}
     */
    public function page(array $genre, MediaType $type, int $page): array
    {
        $cacheKey = "genre:{$genre['slug']}:{$type->value}:{$page}:".config('services.tmdb.language');

        /** @var array{ids: list<int>, has_more: bool, failed: list<string>}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            $cached = $this->fetch($genre, $type, $page);

            // Halaman yang sebagian sumbernya gagal tidak dicache, supaya dicoba lagi.
            if ($cached['failed'] === []) {
                Cache::put($cacheKey, $cached, self::CACHE_TTL);
            }
        }

        $models = MediaCache::query()->whereIn('id', $cached['ids'])->get()->keyBy('id');

        return [
            'media' => collect($cached['ids'])->map(fn (int $id) => $models->get($id))->filter()->values(),
            'has_more' => $cached['has_more'],
            'failed' => $cached['failed'],
        ];
    }

    /**
     * @return array{ids: list<int>, has_more: bool, failed: list<string>}
     */
    private function fetch(array $genre, MediaType $type, int $page): array
    {
        $tmdbGenre = $type === MediaType::Film ? $genre['tmdb_movie'] : $genre['tmdb_tv'];
        $failed = [];
        $hasMore = false;
        $tmdbResults = [];
        $animeResults = [];

        if ($tmdbGenre !== null && $this->tmdb->isConfigured()) {
            try {
                $tmdb = $this->tmdb->discover($type, $tmdbGenre, $page);
                $tmdbResults = $tmdb['results'];
                $hasMore = $tmdb['has_more'];
            } catch (Throwable $e) {
                $failed[] = MediaSource::Tmdb->value;
                Log::warning('Gagal menjelajah genre di TMDB.', ['genre' => $genre['slug'], 'reason' => $e->getMessage()]);
            }
        }

        if ($genre['anilist'] !== null && $this->anilist->isConfigured()) {
            try {
                $anime = $this->anilist->browse($genre['anilist'], $type, $page, self::ANIME_PER_PAGE);
                $animeResults = $anime['results'];
                $hasMore = $hasMore || $anime['has_more'];
            } catch (Throwable $e) {
                $failed[] = MediaSource::Anilist->value;
                Log::warning('Gagal menjelajah genre di AniList.', ['genre' => $genre['slug'], 'reason' => $e->getMessage()]);
            }
        }

        // Selang-seling 2 judul TMDB : 1 anime supaya anime tidak menumpuk di bawah.
        $merged = [];
        $tmdbChunks = array_chunk($tmdbResults, 2);

        for ($i = 0, $n = max(count($tmdbChunks), count($animeResults)); $i < $n; $i++) {
            array_push($merged, ...($tmdbChunks[$i] ?? []));

            if (isset($animeResults[$i])) {
                $merged[] = $animeResults[$i];
            }
        }

        return [
            'ids' => $this->media->remember($merged)->pluck('id')->all(),
            'has_more' => $hasMore,
            'failed' => $failed,
        ];
    }
}
