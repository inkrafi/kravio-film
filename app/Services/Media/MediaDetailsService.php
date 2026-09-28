<?php

namespace App\Services\Media;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\MediaCache;
use App\Services\Media\Providers\TmdbProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Info tambahan untuk halaman detail: rating TMDB, IMDb & Rotten Tomatoes,
 * serta pemain dan sutradara/kreator.
 *
 * - TMDB: rating dan credits, sekaligus memberi IMDb ID.
 * - OMDb (https://www.omdbapi.com): rating IMDb & Rotten Tomatoes dari IMDb ID.
 * - Anime (AniList/Jikan) tidak punya tautan ke keduanya, jadi dicari di OMDb
 *   lewat judul + tahun, lalu IMDb ID-nya dipakai untuk menemukan judul TMDB.
 *   Kalau TMDB tetap tidak ketemu, pemain & sutradara diambil dari OMDb (tanpa foto).
 *
 * Hasilnya dicache di media_cache supaya kuota harian OMDb (1.000 request di
 * paket gratis) tidak cepat habis.
 */
class MediaDetailsService
{
    /**
     * Rating dan daftar pemain bergerak lambat; seminggu sekali sudah cukup segar.
     */
    public const TTL_DAYS = 7;

    public function __construct(private readonly TmdbProvider $tmdb) {}

    public function isConfigured(): bool
    {
        return $this->tmdb->isConfigured() || $this->omdbConfigured();
    }

    public function isStale(MediaCache $media): bool
    {
        return $media->details_synced_at === null
            || $media->details_synced_at->lt(now()->subDays(self::TTL_DAYS))
            || $this->creditsLackPersonIds($media);
    }

    /**
     * Credits yang tersimpan sebelum ada tautan ke halaman orang belum punya
     * kunci `id`; ambil ulang sekali supaya nama-namanya bisa diklik.
     */
    private function creditsLackPersonIds(MediaCache $media): bool
    {
        return collect($media->credits ?? [])
            ->flatten(1)
            ->contains(fn ($person) => is_array($person) && ! array_key_exists('id', $person));
    }

    /**
     * Ambil ulang detail kalau belum ada atau sudah basi. Hasil yang sudah
     * didapat tetap disimpan walau sebagian sumber gagal, tapi judulnya tidak
     * ditandai sinkron supaya dicoba lagi di kunjungan berikutnya.
     */
    public function refresh(MediaCache $media, bool $force = false): MediaCache
    {
        if (! $this->isConfigured() || (! $force && ! $this->isStale($media))) {
            return $media;
        }

        $failed = false;

        $attempt = function (string $what, callable $call) use (&$failed, $media) {
            try {
                return $call();
            } catch (Throwable $e) {
                $failed = true;

                Log::warning("Gagal mengambil {$what}.", ['media' => $media->sourceKey(), 'reason' => $e->getMessage()]);

                return null;
            }
        };

        $imdbId = $media->imdb_id;
        $omdb = null;

        // Anime belum punya IMDb ID: cari lewat judul + tahun di OMDb.
        if ($imdbId === null && $media->source !== MediaSource::Tmdb && $this->omdbConfigured()) {
            $omdb = $attempt('data OMDb', fn () => $this->omdb(array_filter([
                't' => $media->title,
                'y' => $media->year,
                'type' => $media->media_type === MediaType::Film ? 'movie' : 'series',
            ])));

            $imdbId = $this->found($omdb) ? (Arr::get($omdb, 'imdbID') ?: null) : null;
        }

        $tmdb = null;

        if ($this->tmdb->isConfigured()) {
            $link = $media->source === MediaSource::Tmdb
                ? ['type' => $media->media_type, 'id' => $media->external_id]
                : ($imdbId ? $attempt('judul TMDB', fn () => $this->tmdb->findByImdbId($imdbId, $media->media_type)) : null);

            if ($link) {
                $tmdb = $attempt('detail TMDB', fn () => $this->tmdb->details($link['type'], $link['id']));
                $imdbId ??= $tmdb['imdb_id'] ?? null;
            }
        }

        if ($omdb === null && $imdbId !== null && $this->omdbConfigured()) {
            $omdb = $attempt('data OMDb', fn () => $this->omdb(['i' => $imdbId]));
        }

        $updates = ['imdb_id' => $imdbId];

        if ($tmdb !== null) {
            $updates += [
                'tmdb_rating' => $tmdb['rating'],
                'tmdb_votes' => $tmdb['votes'],
                'credits' => $tmdb['credits'],
            ];
        }

        if ($this->found($omdb)) {
            $updates += [
                'imdb_rating' => $this->number(Arr::get($omdb, 'imdbRating')),
                'imdb_votes' => $this->integer(Arr::get($omdb, 'imdbVotes')),
                'rotten_tomatoes_score' => $this->rottenTomatoes($omdb),
            ];

            if ($tmdb === null) {
                $updates['credits'] = $this->omdbCredits($omdb);
            }
        }

        if (! $failed) {
            $updates['details_synced_at'] = now();
        }

        if (isset($updates['credits'])) {
            $updates['credits'] = $this->keepLatinNames($updates['credits'], $media->credits ?? []);
        }

        $media->forceFill($updates)->save();

        return $media;
    }

    /**
     * Ejaan latin nama (dari MediaLocalizationService) dibawa ke credits yang
     * baru diambil, supaya tidak perlu diminta ulang ke Gemini.
     *
     * @param  array<string, list<array<string, mixed>>>  $fresh
     * @param  array<string, list<array<string, mixed>>>  $previous
     * @return array<string, list<array<string, mixed>>>
     */
    private function keepLatinNames(array $fresh, array $previous): array
    {
        $known = collect($previous)
            ->flatten(1)
            ->filter(fn ($person) => is_array($person) && array_key_exists('name_latin', $person))
            ->mapWithKeys(fn (array $person) => [$person['name'] => $person['name_latin']]);

        foreach ($fresh as $group => $people) {
            foreach ($people as $i => $person) {
                if ($known->has($person['name'])) {
                    $fresh[$group][$i]['name_latin'] = $known->get($person['name']);
                }
            }
        }

        return $fresh;
    }

    private function omdbConfigured(): bool
    {
        return filled(config('services.omdb.key'));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws RuntimeException kalau OMDb gagal dihubungi.
     */
    private function omdb(array $query): array
    {
        $response = Http::timeout(8)->retry(2, 200, throw: false)->acceptJson()->get(
            (string) config('services.omdb.base_url'),
            $query + ['apikey' => config('services.omdb.key')],
        );

        if ($response->failed()) {
            throw new RuntimeException("OMDb membalas {$response->status()}.");
        }

        return $response->json() ?: [];
    }

    /**
     * OMDb membalas 200 dengan {"Response": "False"} untuk judul yang tidak dikenal.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function found(?array $payload): bool
    {
        return Arr::get($payload ?? [], 'Response') === 'True';
    }

    /**
     * Cadangan kalau TMDB tidak punya judulnya: OMDb hanya memberi nama.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, list<array{name: string, role: ?string, photo_url: ?string}>>
     */
    private function omdbCredits(array $payload): array
    {
        $names = fn (string $field) => collect(explode(',', (string) Arr::get($payload, $field)))
            ->map(fn (string $name) => trim($name))
            ->reject(fn (string $name) => $name === '' || $name === 'N/A')
            ->map(fn (string $name) => ['id' => null, 'name' => $name, 'role' => null, 'photo_url' => null])
            ->values()
            ->all();

        return [
            'directors' => $names('Director'),
            'creators' => [],
            'cast' => $names('Actors'),
        ];
    }

    /**
     * Rotten Tomatoes hanya muncul di array Ratings, mis. {"Source": "Rotten Tomatoes", "Value": "87%"}.
     *
     * @param  array<string, mixed>  $payload
     */
    private function rottenTomatoes(array $payload): ?int
    {
        foreach (Arr::get($payload, 'Ratings', []) as $rating) {
            if (Arr::get($rating, 'Source') === 'Rotten Tomatoes') {
                return $this->integer(Arr::get($rating, 'Value'));
            }
        }

        return null;
    }

    /**
     * OMDb memakai "N/A" untuk nilai kosong.
     */
    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function integer(mixed $value): ?int
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }
}
