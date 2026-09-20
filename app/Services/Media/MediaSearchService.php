<?php

namespace App\Services\Media;

use App\Contracts\MediaProvider;
use App\Enums\MediaType;
use App\Models\MediaCache;
use App\Services\Media\Dto\MediaResult;
use App\Services\Media\Dto\MediaSearchResults;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pencarian gabungan film, series, dan anime.
 *
 * Sumber utama ditembak paralel dalam satu pool HTTP. Kalau sebuah media type
 * tidak terlayani karena sumber utamanya mati, sumber cadangan untuk media type
 * itu dijalankan di ronde kedua. Hasilnya dinormalisasi ke satu bentuk,
 * di-upsert ke media_cache, lalu diurutkan berdasarkan kemiripan judul dan
 * popularitas.
 */
class MediaSearchService
{
    /**
     * Hasil pencarian yang sama tidak perlu menembak API lagi dalam waktu dekat —
     * penting karena Jikan membatasi 3 request per detik.
     */
    private const RESULT_CACHE_TTL = 600;

    /**
     * Pencarian yang sebagian sumbernya gagal hanya ditahan sebentar, supaya
     * gangguan singkat di API eksternal tidak mengunci hasil selama 10 menit.
     */
    private const FAILED_RESULT_CACHE_TTL = 30;

    private const MIN_QUERY_LENGTH = 2;

    /**
     * @param  Collection<int, MediaProvider>  $providers  Sumber utama.
     * @param  Collection<int, MediaProvider>  $fallbackProviders  Cadangan, hanya dipakai saat sumber utama gagal.
     */
    public function __construct(
        private readonly Collection $providers,
        private readonly Collection $fallbackProviders,
    ) {}

    /**
     * @param  list<MediaType>|MediaType|null  $types  Null berarti semua tipe.
     */
    public function search(string $query, array|MediaType|null $types = null, int $limit = 20): MediaSearchResults
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');

        if (Str::length($query) < self::MIN_QUERY_LENGTH) {
            return MediaSearchResults::empty();
        }

        $types = $this->normalizeTypes($types);

        $cacheKey = $this->cacheKey($query, $types, $limit);

        /** @var array{keys: list<string>, failed: list<string>, skipped: list<string>, fallback: array<string, string>}|null $outcome */
        $outcome = Cache::get($cacheKey);

        if ($outcome === null) {
            $outcome = $this->fetchAndStore($query, $types, $limit);

            Cache::put(
                $cacheKey,
                $outcome,
                $outcome['failed'] === [] ? self::RESULT_CACHE_TTL : self::FAILED_RESULT_CACHE_TTL,
            );
        }

        return new MediaSearchResults(
            media: $this->hydrate($outcome['keys']),
            failedSources: $outcome['failed'],
            skippedSources: $outcome['skipped'],
            fallbackSources: $outcome['fallback'],
        );
    }

    /**
     * Tembak sumber utama, jalankan cadangan kalau perlu, simpan hasilnya, lalu
     * kembalikan urutan key-nya. Hanya key yang dicache supaya model selalu
     * dibaca segar dari database.
     *
     * @param  list<MediaType>  $types
     * @return array{keys: list<string>, failed: list<string>, skipped: list<string>, fallback: array<string, string>}
     */
    private function fetchAndStore(string $query, array $types, int $limit): array
    {
        $candidates = $this->providersFor($this->providers, $types);

        $skipped = $candidates
            ->reject(fn (MediaProvider $provider) => $provider->isConfigured())
            ->map(fn (MediaProvider $provider) => $provider->key())
            ->values()
            ->all();

        $primary = $this->runProviders(
            $candidates->filter(fn (MediaProvider $provider) => $provider->isConfigured()),
            $query,
            $types,
            $limit,
        );

        $results = $primary['results'];
        $failed = $primary['failed'];
        $fallbackUsed = [];

        // Media type yang sama sekali tidak terlayani: semua sumber utamanya gagal.
        $uncovered = $this->intersectTypes($primary['failedTypes'], $types);
        $uncovered = array_values(array_filter(
            $uncovered,
            fn (MediaType $type) => ! in_array($type, $primary['servedTypes'], strict: true),
        ));

        if ($uncovered !== []) {
            $backups = $this->providersFor($this->fallbackProviders, $uncovered)
                ->filter(fn (MediaProvider $provider) => $provider->isConfigured());

            if ($backups->isNotEmpty()) {
                Log::info('Sumber utama gagal, mencoba sumber cadangan.', [
                    'media_types' => array_map(fn (MediaType $type) => $type->value, $uncovered),
                    'fallback' => $backups->map(fn (MediaProvider $provider) => $provider->key())->all(),
                ]);

                $backup = $this->runProviders($backups, $query, $uncovered, $limit);

                $results = [...$results, ...$backup['results']];
                $failed = [...$failed, ...$backup['failed']];

                [$failed, $fallbackUsed] = $this->creditFallbacks(
                    $failed,
                    $primary['failedTypesBySource'],
                    $backup,
                );
            }
        }

        $ranked = $this->rank(array_values($results), $query, $limit);

        $this->store($ranked);

        return [
            'keys' => array_map(fn (MediaResult $result) => $result->key(), $ranked),
            'failed' => array_values(array_unique($failed)),
            'skipped' => $skipped,
            'fallback' => $fallbackUsed,
        ];
    }

    /**
     * Jalankan sekumpulan provider dalam satu pool HTTP.
     *
     * @param  Collection<int, MediaProvider>  $providers
     * @param  list<MediaType>  $types
     * @return array{
     *     results: array<string, MediaResult>,
     *     failed: list<string>,
     *     servedTypes: list<MediaType>,
     *     failedTypes: list<MediaType>,
     *     failedTypesBySource: array<string, list<MediaType>>,
     *     servedTypesBySource: array<string, list<MediaType>>
     * }
     */
    private function runProviders(Collection $providers, string $query, array $types, int $limit): array
    {
        $empty = [
            'results' => [],
            'failed' => [],
            'servedTypes' => [],
            'failedTypes' => [],
            'failedTypesBySource' => [],
            'servedTypesBySource' => [],
        ];

        if ($providers->isEmpty()) {
            return $empty;
        }

        /** @var array<string, array{provider: MediaProvider, name: string, types: list<MediaType>}> $index */
        $index = [];
        $specs = [];

        foreach ($providers as $provider) {
            $providerTypes = $this->intersectTypes($provider->supportedTypes(), $types);

            foreach ($provider->searchRequests($query, $providerTypes, $limit) as $name => $spec) {
                $poolKey = $provider->key().'.'.$name;
                $index[$poolKey] = ['provider' => $provider, 'name' => $name, 'types' => $providerTypes];
                $specs[$poolKey] = $spec;
            }
        }

        $responses = Http::pool(function (Pool $pool) use ($specs) {
            $requests = [];

            foreach ($specs as $poolKey => $spec) {
                $request = $pool->as($poolKey)
                    ->acceptJson()
                    ->withHeaders($spec->headers)
                    ->timeout(8)
                    ->retry(3, 500, throw: false);

                $requests[] = $spec->method === 'post'
                    ? $request->post($spec->url, $spec->payload)
                    : $request->get($spec->url, $spec->query);
            }

            return $requests;
        });

        $outcome = $empty;

        foreach ($index as $poolKey => $entry) {
            $provider = $entry['provider'];
            $response = $responses[$poolKey] ?? null;

            $fail = function (string $reason, ?int $status = null) use (&$outcome, $poolKey, $provider, $entry) {
                $outcome['failed'][] = $provider->key();
                $outcome['failedTypes'] = [...$outcome['failedTypes'], ...$entry['types']];
                $outcome['failedTypesBySource'][$provider->key()] = array_values(array_unique([
                    ...($outcome['failedTypesBySource'][$provider->key()] ?? []),
                    ...$entry['types'],
                ], SORT_REGULAR));

                Log::warning('Pencarian media gagal.', [
                    'pool_key' => $poolKey,
                    'status' => $status,
                    'reason' => $reason,
                ]);
            };

            if (! $response instanceof Response || $response->failed()) {
                $fail(
                    $response instanceof Throwable ? $response->getMessage() : 'response tidak berhasil',
                    $response instanceof Response ? $response->status() : null,
                );

                continue;
            }

            try {
                foreach ($provider->parseSearch($entry['name'], $response) as $result) {
                    $outcome['results'][$result->key()] = $result;
                }

                $outcome['servedTypes'] = [...$outcome['servedTypes'], ...$entry['types']];
                $outcome['servedTypesBySource'][$provider->key()] = array_values(array_unique([
                    ...($outcome['servedTypesBySource'][$provider->key()] ?? []),
                    ...$entry['types'],
                ], SORT_REGULAR));
            } catch (Throwable $e) {
                $fail($e->getMessage(), $response->status());
            }
        }

        $outcome['failed'] = array_values(array_unique($outcome['failed']));
        $outcome['servedTypes'] = array_values(array_unique($outcome['servedTypes'], SORT_REGULAR));
        $outcome['failedTypes'] = array_values(array_unique($outcome['failedTypes'], SORT_REGULAR));

        return $outcome;
    }

    /**
     * Sumber utama yang seluruh media type-nya berhasil diambil sumber cadangan
     * tidak lagi dilaporkan sebagai gagal — cukup dicatat bahwa ada penggantian,
     * supaya UI bisa bilang "anime diambil dari AniList".
     *
     * @param  list<string>  $failed
     * @param  array<string, list<MediaType>>  $failedTypesBySource
     * @param  array{servedTypesBySource: array<string, list<MediaType>>, ...}  $backup
     * @return array{0: list<string>, 1: array<string, string>}
     */
    private function creditFallbacks(array $failed, array $failedTypesBySource, array $backup): array
    {
        $rescued = [];

        foreach ($failedTypesBySource as $source => $brokenTypes) {
            foreach ($backup['servedTypesBySource'] as $backupSource => $servedTypes) {
                $covered = array_values(array_filter(
                    $brokenTypes,
                    fn (MediaType $type) => in_array($type, $servedTypes, strict: true),
                ));

                if (count($covered) === count($brokenTypes) && $covered !== []) {
                    $rescued[$source] = $backupSource;

                    break;
                }
            }
        }

        $failed = array_values(array_filter(
            $failed,
            fn (string $source) => ! array_key_exists($source, $rescued),
        ));

        return [$failed, $rescued];
    }

    /**
     * Urutkan lintas sumber: kemiripan judul dominan, popularitas relatif
     * (dinormalisasi per sumber, karena skalanya beda jauh) sebagai penyeimbang.
     *
     * @param  list<MediaResult>  $results
     * @return list<MediaResult>
     */
    private function rank(array $results, string $query, int $limit): array
    {
        $maxPopularity = [];

        foreach ($results as $result) {
            $source = $result->source->value;
            $maxPopularity[$source] = max($maxPopularity[$source] ?? 0.0, $result->popularity);
        }

        $scored = array_map(function (MediaResult $result) use ($query, $maxPopularity) {
            $ceiling = $maxPopularity[$result->source->value] ?: 1.0;

            return [
                'result' => $result,
                'score' => 0.75 * $this->titleSimilarity($result, $query)
                    + 0.25 * ($result->popularity / $ceiling),
            ];
        }, $results);

        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_map(
            fn (array $row) => $row['result'],
            array_slice($scored, 0, $limit),
        );
    }

    /**
     * Nilai 0..1, mengambil kecocokan terbaik antara judul utama dan judul asli.
     */
    private function titleSimilarity(MediaResult $result, string $query): float
    {
        $needle = Str::lower($query);
        $best = 0.0;

        foreach (array_filter([$result->title, $result->originalTitle]) as $candidate) {
            $haystack = Str::lower($candidate);

            if ($haystack === $needle) {
                return 1.0;
            }

            similar_text($haystack, $needle, $percent);
            $score = $percent / 100;

            if (str_contains($haystack, $needle)) {
                $score = max($score, 0.85);
            }

            $best = max($best, $score);
        }

        return $best;
    }

    /**
     * @param  list<MediaResult>  $results
     */
    private function store(array $results): void
    {
        if ($results === []) {
            return;
        }

        $now = now();

        $rows = array_map(
            fn (MediaResult $result) => $result->toCacheRow() + [
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $results,
        );

        MediaCache::query()->upsert(
            $rows,
            ['source', 'media_type', 'external_id'],
            [
                'title', 'original_title', 'poster_url', 'backdrop_url', 'synopsis',
                'year', 'released_on', 'genres', 'raw_payload', 'synced_at', 'updated_at',
            ],
        );
    }

    /**
     * Ambil model media_cache sesuai urutan hasil ranking.
     *
     * @param  list<string>  $keys
     * @return Collection<int, MediaCache>
     */
    private function hydrate(array $keys): Collection
    {
        if ($keys === []) {
            return collect();
        }

        $parsed = array_map(fn (string $key) => explode(':', $key, 3), $keys);

        $models = MediaCache::query()
            ->where(function ($outer) use ($parsed) {
                foreach ($parsed as $parts) {
                    [$source, $mediaType, $externalId] = $parts;

                    $outer->orWhere(fn ($inner) => $inner
                        ->where('source', $source)
                        ->where('media_type', $mediaType)
                        ->where('external_id', $externalId));
                }
            })
            ->get()
            ->keyBy(fn (MediaCache $media) => $media->source->value.':'.$media->media_type->value.':'.$media->external_id);

        return collect($keys)
            ->map(fn (string $key) => $models->get($key))
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, MediaProvider>  $providers
     * @param  list<MediaType>  $types
     * @return Collection<int, MediaProvider>
     */
    private function providersFor(Collection $providers, array $types): Collection
    {
        return $providers->filter(
            fn (MediaProvider $provider) => $this->intersectTypes($provider->supportedTypes(), $types) !== []
        )->values();
    }

    /**
     * array_intersect() membandingkan elemen sebagai string, jadi tidak bisa
     * dipakai untuk enum object.
     *
     * @param  list<MediaType>  $a
     * @param  list<MediaType>  $b
     * @return list<MediaType>
     */
    private function intersectTypes(array $a, array $b): array
    {
        return array_values(array_filter($a, fn (MediaType $type) => in_array($type, $b, strict: true)));
    }

    /**
     * @return list<MediaType>
     */
    private function normalizeTypes(array|MediaType|null $types): array
    {
        if ($types === null) {
            return MediaType::cases();
        }

        $types = is_array($types) ? $types : [$types];

        $types = array_values(array_filter(array_map(
            fn ($type) => $type instanceof MediaType ? $type : MediaType::tryFrom((string) $type),
            $types,
        )));

        return $types === [] ? MediaType::cases() : $types;
    }

    /**
     * @param  list<MediaType>  $types
     */
    private function cacheKey(string $query, array $types, int $limit): string
    {
        $typeKey = implode(',', array_map(fn (MediaType $type) => $type->value, $types));

        return 'media:search:'.sha1(Str::lower($query).'|'.$typeKey.'|'.$limit);
    }
}
