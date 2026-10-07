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
 * Judul yang sedang populer untuk halaman Cari sebelum pengguna mengetik:
 * trending mingguan TMDB (film/series) diselang-seling anime trending AniList.
 */
class TrendingService
{
    public const LIMIT = 18;

    private const CACHE_TTL = 60 * 60 * 6;

    /**
     * Daftar yang sebagian sumbernya gagal tetap dicache sebentar, supaya
     * sumber yang sedang down tidak ditunggu lagi di setiap kunjungan.
     */
    private const FAILED_CACHE_TTL = 60 * 10;

    /** Anime yang diselipkan: kira-kira sepertiga dari daftar. */
    private const ANIME_COUNT = 6;

    public function __construct(
        private readonly TmdbProvider $tmdb,
        private readonly AniListProvider $anilist,
        private readonly MediaSearchService $media,
    ) {}

    /**
     * @return array{media: Collection<int, MediaCache>, failed: list<string>}
     */
    public function titles(?MediaType $type = null): array
    {
        $cacheKey = $this->cacheKey($type);

        /** @var array{ids: list<int>, failed: list<string>}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            $cached = $this->fetch($type);

            Cache::put($cacheKey, $cached, $cached['failed'] === [] ? self::CACHE_TTL : self::FAILED_CACHE_TTL);
        }

        $models = MediaCache::query()->whereIn('id', $cached['ids'])->get()->keyBy('id');

        return [
            'media' => collect($cached['ids'])->map(fn (int $id) => $models->get($id))->filter()->values(),
            'failed' => $cached['failed'],
        ];
    }

    /**
     * Judul populer dari cache saja, tanpa memanggil API (untuk halaman yang
     * harus selalu cepat, mis. landing page). Null kalau belum pernah diambil.
     *
     * @return Collection<int, MediaCache>|null
     */
    public function cached(?MediaType $type = null): ?Collection
    {
        $ids = Cache::get($this->cacheKey($type))['ids'] ?? null;

        if (! $ids) {
            return null;
        }

        $models = MediaCache::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $models->get($id))->filter()->values();
    }

    private function cacheKey(?MediaType $type): string
    {
        return 'trending:'.($type->value ?? 'semua').':'.config('services.tmdb.language');
    }

    /**
     * @return array{ids: list<int>, failed: list<string>}
     */
    private function fetch(?MediaType $type): array
    {
        $failed = [];
        $tmdbResults = [];
        $animeResults = [];

        if ($this->tmdb->isConfigured()) {
            try {
                $tmdbResults = $this->tmdb->trending($type);
            } catch (Throwable $e) {
                $failed[] = MediaSource::Tmdb->value;
                Log::warning('Gagal mengambil trending TMDB.', ['reason' => $e->getMessage()]);
            }
        }

        if ($this->anilist->isConfigured()) {
            try {
                $animeResults = $this->anilist->trending($type, self::ANIME_COUNT);
            } catch (Throwable $e) {
                $failed[] = MediaSource::Anilist->value;
                Log::warning('Gagal mengambil trending AniList.', ['reason' => $e->getMessage()]);
            }
        }

        // Selang-seling 2 judul TMDB : 1 anime, sama seperti halaman genre.
        $merged = [];
        $tmdbChunks = array_chunk($tmdbResults, 2);

        for ($i = 0, $n = max(count($tmdbChunks), count($animeResults)); $i < $n; $i++) {
            array_push($merged, ...($tmdbChunks[$i] ?? []));

            if (isset($animeResults[$i])) {
                $merged[] = $animeResults[$i];
            }
        }

        return [
            'ids' => $this->media->remember(array_slice($merged, 0, self::LIMIT))->pluck('id')->all(),
            'failed' => $failed,
        ];
    }
}
