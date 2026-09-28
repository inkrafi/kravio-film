<?php

namespace App\Services\Media;

use App\Contracts\MediaProvider;
use App\Contracts\SearchesPeople;
use App\Enums\MediaType;
use App\Models\MediaCache;
use App\Services\Media\Dto\MediaResult;
use App\Services\Media\Dto\MediaSearchResults;
use App\Support\PersonNames;
use App\Support\Romanizer;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pencarian gabungan film dan series. Anime tidak punya tipe sendiri: anime
 * movie masuk Film, anime berepisode masuk Series.
 *
 * Sumber utama ditembak paralel dalam satu pool HTTP. Kalau sebuah sumber utama
 * gagal, sumber cadangannya (mis. Jikan untuk AniList) dijalankan di ronde
 * kedua. Hasilnya dinormalisasi ke satu bentuk,
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

    /** Jumlah orang yang ditampilkan di atas hasil judul. */
    public const PEOPLE_LIMIT = 6;

    /**
     * Orang dengan nama yang tidak mirip kata kunci dibuang, supaya mencari
     * judul film tidak memunculkan deretan kru yang kebetulan terkait.
     */
    private const PEOPLE_MIN_SIMILARITY = 0.6;

    /**
     * @param  Collection<int, MediaProvider>  $providers  Sumber utama.
     * @param  Collection<string, MediaProvider>  $fallbackProviders  Cadangan, di-key dengan key sumber utama yang
     *                                                                digantikannya; hanya dipakai saat sumber itu gagal.
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
            people: $outcome['people'] ?? [],
        );
    }

    /**
     * Tembak sumber utama, jalankan cadangan kalau perlu, simpan hasilnya, lalu
     * kembalikan urutan key-nya. Hanya key yang dicache supaya model selalu
     * dibaca segar dari database.
     *
     * @param  list<MediaType>  $types
     * @return array{keys: list<string>, failed: list<string>, skipped: list<string>, fallback: array<string, string>, people: list<array<string, mixed>>}
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

        // Sumber utama yang gagal => cadangannya (kalau ada dan siap dipakai).
        $backups = collect($failed)
            ->mapWithKeys(fn (string $source) => [$source => $this->fallbackProviders->get($source)])
            ->filter(fn (?MediaProvider $provider) => $provider?->isConfigured() ?? false);

        if ($backups->isNotEmpty()) {
            Log::info('Sumber utama gagal, mencoba sumber cadangan.', [
                'failed' => $backups->keys()->all(),
                'fallback' => $backups->map(fn (MediaProvider $provider) => $provider->key())->values()->all(),
            ]);

            $backup = $this->runProviders($backups->values(), $query, $types, $limit);

            $results = [...$results, ...$backup['results']];

            // Sumber utama yang cadangannya berhasil tidak lagi dilaporkan gagal —
            // cukup dicatat penggantiannya, supaya UI bisa bilang
            // "AniList bermasalah, hasil diambil dari MyAnimeList".
            foreach ($backups as $source => $provider) {
                if (! in_array($provider->key(), $backup['failed'], strict: true)) {
                    $fallbackUsed[$source] = $provider->key();
                }
            }

            $failed = [
                ...array_filter($failed, fn (string $source) => ! array_key_exists($source, $fallbackUsed)),
                ...$backup['failed'],
            ];
        }

        $ranked = $this->rank(array_values($results), $query, $limit);

        $this->store($ranked);

        // Orang hanya di pencarian tanpa filter tipe (tab "Semua").
        $people = count($types) === count(MediaType::cases())
            ? $this->rankPeople($primary['people'], $query)
            : [];

        return [
            'keys' => array_map(fn (MediaResult $result) => $result->key(), $ranked),
            'failed' => array_values(array_unique($failed)),
            'skipped' => $skipped,
            'fallback' => $fallbackUsed,
            'people' => $people,
        ];
    }

    /**
     * Jalankan sekumpulan provider dalam satu pool HTTP.
     *
     * @param  Collection<int, MediaProvider>  $providers
     * @param  list<MediaType>  $types
     * @return array{results: array<string, MediaResult>, failed: list<string>, people: array<int, array<string, mixed>>}
     */
    private function runProviders(Collection $providers, string $query, array $types, int $limit): array
    {
        $outcome = ['results' => [], 'failed' => [], 'people' => []];

        if ($providers->isEmpty()) {
            return $outcome;
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

        foreach ($index as $poolKey => $entry) {
            $provider = $entry['provider'];
            $response = $responses[$poolKey] ?? null;

            $fail = function (string $reason, ?int $status = null) use (&$outcome, $poolKey, $provider) {
                $outcome['failed'][] = $provider->key();

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
                    // Tidak semua API bisa menyaring tipe (mis. /search/multi TMDB,
                    // atau anime Series di Jikan), jadi saring di sini.
                    if (! in_array($result->mediaType, $entry['types'], strict: true)) {
                        continue;
                    }

                    $key = $result->key();

                    // Request yang lebih awal menang; yang belakangan hanya
                    // menambal field kosong (mis. sinopsis bahasa cadangan).
                    $outcome['results'][$key] = isset($outcome['results'][$key])
                        ? $outcome['results'][$key]->fillGapsFrom($result)
                        : $result;
                }

                if ($provider instanceof SearchesPeople) {
                    foreach ($provider->parsePeople($entry['name'], $response) as $person) {
                        $outcome['people'][$person['id']] ??= $person;
                    }
                }
            } catch (Throwable $e) {
                $fail($e->getMessage(), $response->status());
            }
        }

        $outcome['failed'] = array_values(array_unique($outcome['failed']));

        return $outcome;
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
     * Kemiripan nama dominan, popularitas sebagai penyeimbang — sama seperti judul.
     *
     * @param  array<int, array{name: string, name_latin: ?string, popularity: float}>  $people
     * @return list<array<string, mixed>>
     */
    private function rankPeople(array $people, string $query): array
    {
        $ceiling = max(array_column($people, 'popularity') ?: [0]) ?: 1.0;
        $latinQuery = Romanizer::isLatin($query);

        return collect($people)
            ->map(function (array $person) use ($query, $latinQuery) {
                if (! Romanizer::isLatin($person['name'])) {
                    // Ejaan dari Gemini (lihat PersonNames) lebih tepat daripada romanisasi.
                    $person['name_latin'] = PersonNames::latinFromCredits($person['id']) ?? $person['name_latin'];
                }

                $similarity = $this->similarity([$person['name'], $person['name_latin']], $query);

                // Nama non-latin yang muncul untuk kata kunci latin berarti TMDB
                // mencocokkannya lewat alias (mis. "lee sun kyun" → 이선균); percayai.
                if ($latinQuery && ! Romanizer::isLatin($person['name'])) {
                    $similarity = max($similarity, self::PEOPLE_MIN_SIMILARITY);
                }

                return $person + ['similarity' => $similarity];
            })
            ->filter(fn (array $person) => $person['similarity'] >= self::PEOPLE_MIN_SIMILARITY)
            ->sortByDesc(fn (array $person) => 0.75 * $person['similarity'] + 0.25 * $person['popularity'] / $ceiling)
            ->take(self::PEOPLE_LIMIT)
            ->map(fn (array $person) => array_diff_key($person, ['similarity' => true]))
            ->values()
            ->all();
    }

    /**
     * Nilai 0..1, mengambil kecocokan terbaik antara judul utama dan judul asli.
     */
    private function titleSimilarity(MediaResult $result, string $query): float
    {
        return $this->similarity([$result->title, $result->originalTitle], $query);
    }

    /**
     * @param  list<?string>  $candidates
     */
    private function similarity(array $candidates, string $query): float
    {
        $needle = Str::lower($query);
        $best = 0.0;

        foreach (array_filter($candidates) as $candidate) {
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
     * Simpan hasil dari luar pencarian (filmografi orang, jelajah genre) ke
     * media_cache, lalu kembalikan modelnya dengan urutan yang sama — supaya
     * setiap judul punya slug dan bisa dibuka halaman detailnya.
     *
     * @param  list<MediaResult>  $results
     * @return Collection<int, MediaCache>
     */
    public function remember(array $results): Collection
    {
        $unique = collect($results)->keyBy(fn (MediaResult $result) => $result->key())->values()->all();

        $this->store($unique);

        return $this->hydrate(array_map(fn (MediaResult $result) => $result->key(), $unique));
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
                // Versi latin dari sumber (judul en-US TMDB, romaji) boleh
                // menggantikan yang lama, tapi kosong tidak boleh menghapusnya.
                'title_latin' => DB::raw('COALESCE(excluded.title_latin, media_cache.title_latin)'),
                'original_title_latin' => DB::raw('COALESCE(excluded.original_title_latin, media_cache.original_title_latin)'),
            ],
        );

        // Romanisasi cadangan hanya mengisi yang masih kosong, supaya tidak
        // menimpa judul resmi atau hasil Gemini (MediaLocalizationService).
        foreach ($results as $result) {
            $fromSource = $result->latinColumns(withFallback: false);

            foreach ($result->latinColumns() as $column => $value) {
                if ($value !== null && $fromSource[$column] === null) {
                    MediaCache::query()
                        ->where('source', $result->source->value)
                        ->where('media_type', $result->mediaType->value)
                        ->where('external_id', $result->externalId)
                        ->whereNull($column)
                        ->update([$column => $value]);
                }
            }
        }

        // Upsert tidak memicu event model, jadi judul baru diberi slug di sini.
        MediaCache::query()->whereNull('slug')->orderBy('id')->each(
            fn (MediaCache $media) => $media->assignSlug(),
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
