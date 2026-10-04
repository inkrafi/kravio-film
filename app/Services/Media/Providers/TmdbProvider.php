<?php

namespace App\Services\Media\Providers;

use App\Contracts\MediaProvider;
use App\Contracts\SearchesPeople;
use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Services\Media\Dto\MediaResult;
use App\Services\Media\Dto\ProviderRequest;
use App\Support\Romanizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sumber film & series dari The Movie Database.
 *
 * Satu pencarian cukup memakai endpoint /search/multi: hasilnya sudah campuran
 * movie + tv, tinggal buang entri bertipe person.
 */
class TmdbProvider implements MediaProvider, SearchesPeople
{
    private const GENRE_CACHE_TTL = 60 * 60 * 24 * 7;

    /**
     * Bahasa yang selalu punya isi di TMDB, dipakai untuk menambal sinopsis.
     */
    private const FALLBACK_LANGUAGE = 'en-US';

    /**
     * Jumlah pemain yang ditampilkan di halaman detail.
     */
    private const CAST_LIMIT = 12;

    private const PROFILE_SIZE = 'w185';

    private const LOGO_SIZE = 'w92';

    /**
     * Kategori platform tonton dari TMDB, urut dari yang paling murah bagi penonton.
     */
    public const WATCH_PROVIDER_TYPES = ['flatrate', 'free', 'ads', 'rent', 'buy'];

    public function key(): string
    {
        return MediaSource::Tmdb->value;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.tmdb.key'));
    }

    public function supportedTypes(): array
    {
        return [MediaType::Film, MediaType::Series];
    }

    public function searchRequests(string $query, array $types, int $limit): array
    {
        $language = (string) config('services.tmdb.language');

        $requests = ['multi' => $this->searchRequest($query, $language)];

        // Sebagian besar judul belum punya terjemahan Indonesia, sehingga
        // `overview` balik kosong. Versi bahasa asli ditembak berbarengan di pool
        // yang sama (tanpa tambahan latensi) untuk menambal sinopsis yang bolong —
        // lihat MediaResult::fillGapsFrom().
        if ($language !== self::FALLBACK_LANGUAGE) {
            $requests['multi_fallback'] = $this->searchRequest($query, self::FALLBACK_LANGUAGE);
        }

        return $requests;
    }

    private function searchRequest(string $query, string $language): ProviderRequest
    {
        return ProviderRequest::get(
            url: rtrim((string) config('services.tmdb.base_url'), '/').'/search/multi',
            query: array_filter([
                'query' => $query,
                'language' => $language,
                'include_adult' => config('services.tmdb.include_adult') ? 'true' : 'false',
                'page' => 1,
                'api_key' => $this->usesBearerToken() ? null : config('services.tmdb.key'),
            ], fn ($value) => $value !== null),
            headers: $this->usesBearerToken()
                ? ['Authorization' => 'Bearer '.config('services.tmdb.key')]
                : [],
        );
    }

    public function parseSearch(string $name, Response $response): array
    {
        $genreMap = $this->genreMap();

        return collect(Arr::get($response->json(), 'results', []))
            ->map(fn (array $item) => $this->resultFromItem(
                $item,
                $genreMap,
                // Judul versi en-US (request cadangan) jadi versi latin kalau
                // judul bahasa utamanya ternyata masih beraksara Korea/Jepang/dll.
                titleLatin: $name === 'multi_fallback' ? (Arr::get($item, 'title') ?? Arr::get($item, 'name')) : null,
            ))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Orang (entri media_type "person") dari response /search/multi. Hanya dari
     * request bahasa utama; versi en-US berisi orang yang sama.
     */
    public function parsePeople(string $name, Response $response): array
    {
        if ($name !== 'multi') {
            return [];
        }

        return collect(Arr::get($response->json(), 'results', []))
            ->filter(fn (array $item) => Arr::get($item, 'media_type') === 'person' && filled(Arr::get($item, 'id')) && filled(Arr::get($item, 'name')))
            ->map(function (array $item) {
                $name = (string) Arr::get($item, 'name');

                return [
                    'id' => (int) Arr::get($item, 'id'),
                    'name' => $name,
                    'name_latin' => Romanizer::isLatin($name) ? null : Romanizer::romanize($name),
                    'photo_url' => $this->imageUrl(Arr::get($item, 'profile_path'), self::PROFILE_SIZE),
                    'department' => Arr::get($item, 'known_for_department'),
                    'known_for' => collect(Arr::get($item, 'known_for', []))
                        ->map(fn (array $work) => Arr::get($work, 'title') ?? Arr::get($work, 'name'))
                        ->filter()
                        ->take(2)
                        ->values()
                        ->all(),
                    'popularity' => (float) Arr::get($item, 'popularity', 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Profil satu orang beserta semua judul yang pernah ia bintangi atau garap.
     * Null kalau orangnya tidak ada di TMDB.
     *
     * @return array{
     *     id: int, name: string, also_known_as: list<string>, photo_url: ?string,
     *     department: ?string, birthday: ?string, deathday: ?string, place_of_birth: ?string,
     *     biography: ?string, biography_language: ?string,
     *     credits: list<array{result: MediaResult, as: 'cast'|'crew', role: ?string}>
     * }|null
     *
     * @throws RuntimeException kalau TMDB gagal dihubungi.
     */
    public function person(int $personId): ?array
    {
        $language = (string) config('services.tmdb.language');

        $response = $this->client()->get(
            $this->url("person/{$personId}"),
            $this->authQuery(['language' => $language, 'append_to_response' => 'combined_credits']),
        );

        if ($response->notFound()) {
            return null;
        }

        if ($response->failed()) {
            throw new RuntimeException("TMDB membalas {$response->status()}.");
        }

        $item = $response->json();
        $biography = Arr::get($item, 'biography');
        $biographyLanguage = $language;
        $englishTitles = [];

        // Versi en-US dipakai untuk dua hal: judul latin resmi bagi judul yang
        // di id-ID masih beraksara Korea/Jepang/dll., dan biografi — yang jarang
        // tersedia dalam bahasa Indonesia (nanti diterjemahkan halaman orang).
        if ($language !== self::FALLBACK_LANGUAGE) {
            $fallback = $this->client()->get(
                $this->url("person/{$personId}"),
                $this->authQuery(['language' => self::FALLBACK_LANGUAGE, 'append_to_response' => 'combined_credits']),
            );

            if ($fallback->successful()) {
                foreach (['cast', 'crew'] as $as) {
                    foreach ($fallback->json("combined_credits.{$as}") ?? [] as $credit) {
                        $englishTitles[Arr::get($credit, 'media_type').':'.Arr::get($credit, 'id')] = Arr::get($credit, 'title') ?? Arr::get($credit, 'name');
                    }
                }

                if (blank($biography)) {
                    $biography = $fallback->json('biography');
                    $biographyLanguage = self::FALLBACK_LANGUAGE;
                }
            }
        }

        $genreMap = $this->genreMap();
        $credits = [];

        foreach (['cast', 'crew'] as $as) {
            foreach (Arr::get($item, "combined_credits.{$as}", []) as $credit) {
                $result = $this->resultFromItem(
                    $credit,
                    $genreMap,
                    titleLatin: $englishTitles[Arr::get($credit, 'media_type').':'.Arr::get($credit, 'id')] ?? null,
                );

                if ($result !== null) {
                    $credits[] = [
                        'result' => $result,
                        'as' => $as,
                        'role' => $as === 'cast' ? (Arr::get($credit, 'character') ?: null) : (Arr::get($credit, 'job') ?: null),
                    ];
                }
            }
        }

        return [
            'id' => (int) Arr::get($item, 'id'),
            'name' => (string) Arr::get($item, 'name'),
            'also_known_as' => array_values(array_filter((array) Arr::get($item, 'also_known_as', []), 'is_string')),
            'photo_url' => $this->imageUrl(Arr::get($item, 'profile_path'), 'h632'),
            'department' => Arr::get($item, 'known_for_department'),
            'birthday' => Arr::get($item, 'birthday'),
            'deathday' => Arr::get($item, 'deathday'),
            'place_of_birth' => Arr::get($item, 'place_of_birth'),
            'biography' => filled($biography) ? trim($biography) : null,
            'biography_language' => filled($biography) ? $biographyLanguage : null,
            'credits' => $credits,
        ];
    }

    /**
     * Judul populer dalam satu genre TMDB.
     *
     * @return array{results: list<MediaResult>, has_more: bool}
     *
     * @throws RuntimeException kalau TMDB gagal dihubungi.
     */
    public function discover(MediaType $type, int $genreId, int $page = 1): array
    {
        $kind = $type === MediaType::Film ? 'movie' : 'tv';

        $response = $this->client()->get(
            $this->url("discover/{$kind}"),
            $this->authQuery([
                'language' => config('services.tmdb.language'),
                'with_genres' => $genreId,
                'sort_by' => 'popularity.desc',
                // Buang judul yang nyaris tanpa penonton supaya daftar tidak dipenuhi entri kosong.
                'vote_count.gte' => 20,
                'include_adult' => config('services.tmdb.include_adult') ? 'true' : 'false',
                'page' => $page,
            ]),
        );

        if ($response->failed()) {
            throw new RuntimeException("TMDB membalas {$response->status()}.");
        }

        $genreMap = $this->genreMap();

        $results = collect(Arr::get($response->json(), 'results', []))
            ->map(fn (array $item) => $this->resultFromItem($item, $genreMap, $type))
            ->filter()
            ->values()
            ->all();

        return [
            'results' => $results,
            'has_more' => $page < (int) Arr::get($response->json(), 'total_pages', 1),
        ];
    }

    /**
     * Film dan series yang sedang ramai minggu ini. Tanpa tipe, hasilnya
     * campuran film & series (orang yang ikut trending dibuang).
     *
     * @return list<MediaResult>
     *
     * @throws RuntimeException kalau TMDB gagal dihubungi.
     */
    public function trending(?MediaType $type = null): array
    {
        $kind = match ($type) {
            MediaType::Film => 'movie',
            MediaType::Series => 'tv',
            null => 'all',
        };

        // Hanya pemanis halaman Cari: gagal cepat, jangan menunggu ulang.
        $response = $this->client()->timeout(5)->retry(1, 0, throw: false)->get(
            $this->url("trending/{$kind}/week"),
            $this->authQuery(['language' => config('services.tmdb.language')]),
        );

        if ($response->failed()) {
            throw new RuntimeException("TMDB membalas {$response->status()}.");
        }

        $genreMap = $this->genreMap();

        return collect(Arr::get($response->json(), 'results', []))
            ->reject(fn (array $item) => ! config('services.tmdb.include_adult') && Arr::get($item, 'adult'))
            ->map(fn (array $item) => $this->resultFromItem($item, $genreMap, $type))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Judul yang direkomendasikan TMDB berdasarkan satu judul (dasar
     * rekomendasi personal di insight AI).
     *
     * @return list<MediaResult>
     *
     * @throws RuntimeException kalau TMDB gagal dihubungi.
     */
    public function recommendations(MediaType $type, string $tmdbId): array
    {
        $kind = $type === MediaType::Film ? 'movie' : 'tv';

        $response = $this->client()->get(
            $this->url("{$kind}/{$tmdbId}/recommendations"),
            $this->authQuery(['language' => config('services.tmdb.language'), 'page' => 1]),
        );

        if ($response->notFound()) {
            return [];
        }

        if ($response->failed()) {
            throw new RuntimeException("TMDB membalas {$response->status()}.");
        }

        $genreMap = $this->genreMap();

        return collect(Arr::get($response->json(), 'results', []))
            ->map(fn (array $item) => $this->resultFromItem($item, $genreMap, $type))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Satu item film/tv TMDB (dari search, discover, atau combined_credits)
     * jadi MediaResult. discover tidak menyertakan media_type, jadi tipenya
     * bisa dipaksakan.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $genreMap
     */
    private function resultFromItem(array $item, array $genreMap, ?MediaType $type = null, ?string $titleLatin = null): ?MediaResult
    {
        $mediaType = $type ?? match (Arr::get($item, 'media_type')) {
            'movie' => MediaType::Film,
            'tv' => MediaType::Series,
            default => null,
        };

        $title = Arr::get($item, 'title') ?? Arr::get($item, 'name');

        if ($mediaType === null || blank(Arr::get($item, 'id')) || blank($title)) {
            return null;
        }

        $date = Arr::get($item, 'release_date') ?: Arr::get($item, 'first_air_date');
        $date = filled($date) ? $date : null;

        return new MediaResult(
            source: MediaSource::Tmdb,
            mediaType: $mediaType,
            externalId: (string) Arr::get($item, 'id'),
            title: $title,
            originalTitle: Arr::get($item, 'original_title') ?? Arr::get($item, 'original_name'),
            posterUrl: $this->imageUrl(Arr::get($item, 'poster_path')),
            backdropUrl: $this->imageUrl(Arr::get($item, 'backdrop_path'), 'w780'),
            synopsis: filled(Arr::get($item, 'overview')) ? Arr::get($item, 'overview') : null,
            year: $date ? (int) Str::before($date, '-') : null,
            releasedOn: $date,
            genres: $this->mapGenres(Arr::get($item, 'genre_ids', []), $genreMap),
            raw: $item,
            popularity: (float) Arr::get($item, 'popularity', 0),
            titleLatin: $titleLatin,
        );
    }

    /**
     * TMDB menerima API key v3 (query string) atau access token v4 (bearer).
     * Token v4 berbentuk JWT, jadi bisa dibedakan dari titiknya.
     */
    private function usesBearerToken(): bool
    {
        return substr_count((string) config('services.tmdb.key'), '.') === 2;
    }

    private function imageUrl(?string $path, ?string $size = null): ?string
    {
        if (blank($path)) {
            return null;
        }

        $base = rtrim((string) config('services.tmdb.image_base_url'), '/');
        $size ??= (string) config('services.tmdb.poster_size');

        return "{$base}/{$size}{$path}";
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, string>  $map
     * @return list<string>
     */
    private function mapGenres(array $ids, array $map): array
    {
        return array_values(array_filter(array_map(fn ($id) => $map[$id] ?? null, $ids)));
    }

    /**
     * search/multi hanya mengirim genre_ids, jadi daftar genre diambil sekali
     * lalu dicache seminggu.
     *
     * @return array<int, string>
     */
    private function genreMap(): array
    {
        return Cache::remember(
            'tmdb:genres:'.config('services.tmdb.language'),
            self::GENRE_CACHE_TTL,
            function (): array {
                $map = [];

                foreach (['movie', 'tv'] as $kind) {
                    try {
                        $response = $this->client()
                            ->get(rtrim((string) config('services.tmdb.base_url'), '/')."/genre/{$kind}/list", array_filter([
                                'language' => config('services.tmdb.language'),
                                'api_key' => $this->usesBearerToken() ? null : config('services.tmdb.key'),
                            ], fn ($value) => $value !== null));

                        if ($response->failed()) {
                            continue;
                        }

                        foreach (Arr::get($response->json(), 'genres', []) as $genre) {
                            $map[(int) Arr::get($genre, 'id')] = (string) Arr::get($genre, 'name');
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Gagal mengambil daftar genre TMDB.', [
                            'kind' => $kind,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                return $map;
            }
        );
    }

    /**
     * Detail satu judul untuk halaman detail: rating TMDB, IMDb ID, serta
     * pemain & sutradara — semuanya dalam satu request lewat append_to_response.
     * Null kalau judulnya tidak ada di TMDB.
     *
     * @return array{imdb_id: ?string, rating: ?float, votes: ?int, credits: array<string, list<array{name: string, role: ?string, photo_url: ?string}>>}|null
     *
     * @throws RuntimeException kalau TMDB gagal dihubungi.
     */
    public function details(MediaType $type, string $tmdbId): ?array
    {
        $film = $type === MediaType::Film;

        $response = $this->client()->get(
            $this->url(($film ? 'movie' : 'tv')."/{$tmdbId}"),
            $this->authQuery([
                'language' => config('services.tmdb.language'),
                // Series memakai aggregate_credits: pemain & kru dari semua musim,
                // bukan hanya musim terakhir seperti `credits`.
                'append_to_response' => $film ? 'credits,external_ids,watch/providers' : 'aggregate_credits,external_ids,watch/providers',
            ]),
        );

        if ($response->notFound()) {
            return null;
        }

        if ($response->failed()) {
            throw new RuntimeException("TMDB membalas {$response->status()}.");
        }

        $item = $response->json();
        $imdbId = Arr::get($item, 'external_ids.imdb_id') ?? Arr::get($item, 'imdb_id');
        $votes = (int) Arr::get($item, 'vote_count', 0);

        return [
            'imdb_id' => is_string($imdbId) && str_starts_with($imdbId, 'tt') ? $imdbId : null,
            // Tanpa suara, vote_average 0 bukan nilai sungguhan.
            'rating' => $votes > 0 ? round((float) Arr::get($item, 'vote_average'), 1) : null,
            'votes' => $votes > 0 ? $votes : null,
            'credits' => $film ? $this->movieCredits($item) : $this->tvCredits($item),
            'watch_providers' => $this->watchProviders($item),
        ];
    }

    /**
     * Platform tonton di negara yang dikonfigurasi (data JustWatch lewat TMDB).
     * Satu platform bisa muncul di beberapa kategori, mis. sewa sekaligus beli.
     *
     * @param  array<string, mixed>  $item
     * @return array{region: string, link: ?string, providers: list<array{id: int, name: string, logo_url: ?string, types: list<string>}>}
     */
    private function watchProviders(array $item): array
    {
        $region = strtoupper((string) config('services.tmdb.watch_region'));
        $country = Arr::get($item, "watch/providers.results.{$region}", []);
        $providers = [];

        foreach (self::WATCH_PROVIDER_TYPES as $type) {
            foreach (Arr::get($country, $type, []) as $provider) {
                $id = (int) Arr::get($provider, 'provider_id');

                $providers[$id] ??= [
                    'id' => $id,
                    'name' => (string) Arr::get($provider, 'provider_name'),
                    'logo_url' => $this->imageUrl(Arr::get($provider, 'logo_path'), self::LOGO_SIZE),
                    'priority' => (int) Arr::get($provider, 'display_priority', PHP_INT_MAX),
                    'types' => [],
                ];

                $providers[$id]['types'][] = $type;
            }
        }

        return [
            'region' => $region,
            'link' => Arr::get($country, 'link'),
            'providers' => collect($providers)
                ->sortBy('priority')
                ->map(fn (array $provider) => Arr::except($provider, 'priority'))
                ->values()
                ->all(),
        ];
    }

    /**
     * Cari judul TMDB dari IMDb ID — jembatan untuk anime dari AniList/Jikan.
     * Tipe yang cocok diutamakan, tapi tipe lain tetap dipakai kalau hanya itu
     * yang ada (mis. anime movie yang di TMDB dicatat sebagai TV).
     *
     * @return array{type: MediaType, id: string}|null
     *
     * @throws RuntimeException kalau TMDB gagal dihubungi.
     */
    public function findByImdbId(string $imdbId, MediaType $preferred): ?array
    {
        $response = $this->client()->get(
            $this->url("find/{$imdbId}"),
            $this->authQuery(['external_source' => 'imdb_id']),
        );

        if ($response->failed()) {
            throw new RuntimeException("TMDB membalas {$response->status()}.");
        }

        $matches = [
            MediaType::Film->value => Arr::get($response->json(), 'movie_results.0.id'),
            MediaType::Series->value => Arr::get($response->json(), 'tv_results.0.id'),
        ];

        $other = $preferred === MediaType::Film ? MediaType::Series : MediaType::Film;

        foreach ([$preferred, $other] as $type) {
            if (filled($matches[$type->value])) {
                return ['type' => $type, 'id' => (string) $matches[$type->value]];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, list<array{name: string, role: ?string, photo_url: ?string}>>
     */
    private function movieCredits(array $item): array
    {
        $directors = collect(Arr::get($item, 'credits.crew', []))
            ->where('job', 'Director')
            ->map(fn (array $person) => $this->creditPerson($person))
            ->unique('name');

        $cast = collect(Arr::get($item, 'credits.cast', []))
            ->sortBy('order')
            ->take(self::CAST_LIMIT)
            ->map(fn (array $person) => $this->creditPerson($person, Arr::get($person, 'character')));

        return [
            'directors' => $directors->values()->all(),
            'creators' => [],
            'cast' => $cast->values()->all(),
        ];
    }

    /**
     * Series jarang punya satu sutradara; yang ditonjolkan adalah kreatornya,
     * plus beberapa sutradara yang paling banyak memegang episode.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, list<array{name: string, role: ?string, photo_url: ?string}>>
     */
    private function tvCredits(array $item): array
    {
        $creators = collect(Arr::get($item, 'created_by', []))
            ->map(fn (array $person) => $this->creditPerson($person));

        $directors = collect(Arr::get($item, 'aggregate_credits.crew', []))
            ->map(fn (array $person) => $person + [
                'director_episodes' => collect(Arr::get($person, 'jobs', []))->where('job', 'Director')->sum('episode_count'),
            ])
            ->where('director_episodes', '>', 0)
            ->sortByDesc('director_episodes')
            ->take(3)
            ->map(fn (array $person) => $this->creditPerson($person, $person['director_episodes'].' episode'));

        $cast = collect(Arr::get($item, 'aggregate_credits.cast', []))
            ->sortBy('order')
            ->take(self::CAST_LIMIT)
            ->map(fn (array $person) => $this->creditPerson($person, Arr::get($person, 'roles.0.character')));

        return [
            'directors' => $directors->values()->all(),
            'creators' => $creators->values()->all(),
            'cast' => $cast->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array{id: ?int, name: string, role: ?string, photo_url: ?string}
     */
    private function creditPerson(array $person, ?string $role = null): array
    {
        return [
            // ID orang di TMDB, untuk tautan ke halaman /orang/{id}.
            'id' => Arr::get($person, 'id') ? (int) Arr::get($person, 'id') : null,
            'name' => (string) Arr::get($person, 'name'),
            'role' => filled($role) ? $role : null,
            'photo_url' => $this->imageUrl(Arr::get($person, 'profile_path'), self::PROFILE_SIZE),
        ];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.tmdb.base_url'), '/').'/'.$path;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function authQuery(array $query): array
    {
        return array_filter([
            ...$query,
            'api_key' => $this->usesBearerToken() ? null : config('services.tmdb.key'),
        ], fn ($value) => $value !== null);
    }

    private function client(): PendingRequest
    {
        $client = Http::timeout(8)->retry(2, 200, throw: false)->acceptJson();

        return $this->usesBearerToken()
            ? $client->withToken((string) config('services.tmdb.key'))
            : $client;
    }
}
