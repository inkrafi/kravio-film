<?php

namespace App\Services\Media\Providers;

use App\Contracts\MediaProvider;
use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Services\Media\Dto\MediaResult;
use App\Services\Media\Dto\ProviderRequest;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sumber film & series dari The Movie Database.
 *
 * Satu pencarian cukup memakai endpoint /search/multi: hasilnya sudah campuran
 * movie + tv, tinggal buang entri bertipe person.
 */
class TmdbProvider implements MediaProvider
{
    private const GENRE_CACHE_TTL = 60 * 60 * 24 * 7;

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
        return [
            'multi' => ProviderRequest::get(
                url: rtrim((string) config('services.tmdb.base_url'), '/').'/search/multi',
                query: array_filter([
                    'query' => $query,
                    'language' => config('services.tmdb.language'),
                    'include_adult' => config('services.tmdb.include_adult') ? 'true' : 'false',
                    'page' => 1,
                    'api_key' => $this->usesBearerToken() ? null : config('services.tmdb.key'),
                ], fn ($value) => $value !== null),
                headers: $this->usesBearerToken()
                    ? ['Authorization' => 'Bearer '.config('services.tmdb.key')]
                    : [],
            ),
        ];
    }

    public function parseSearch(string $name, Response $response): array
    {
        $genreMap = $this->genreMap();
        $results = [];

        foreach (Arr::get($response->json(), 'results', []) as $item) {
            $mediaType = match (Arr::get($item, 'media_type')) {
                'movie' => MediaType::Film,
                'tv' => MediaType::Series,
                default => null,
            };

            if ($mediaType === null || blank(Arr::get($item, 'id'))) {
                continue;
            }

            $title = Arr::get($item, 'title') ?? Arr::get($item, 'name');

            if (blank($title)) {
                continue;
            }

            $date = Arr::get($item, 'release_date') ?: Arr::get($item, 'first_air_date');
            $date = filled($date) ? $date : null;

            $results[] = new MediaResult(
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
            );
        }

        return $results;
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

    private function client(): PendingRequest
    {
        $client = Http::timeout(8)->retry(2, 200, throw: false)->acceptJson();

        return $this->usesBearerToken()
            ? $client->withToken((string) config('services.tmdb.key'))
            : $client;
    }
}
