<?php

namespace App\Services\Media\Providers;

use App\Contracts\MediaProvider;
use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Services\Media\Dto\MediaResult;
use App\Services\Media\Dto\ProviderRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Sumber anime cadangan dari Jikan (wrapper MyAnimeList). Tanpa API key.
 *
 * Dipakai hanya kalau AniList sedang gagal: endpoint pencarian Jikan cukup
 * sering membalas 504 saat MyAnimeList tidak bisa dihubungi dari sisi mereka,
 * dan batasnya ketat (3 request/detik).
 */
class JikanProvider implements MediaProvider
{
    public function key(): string
    {
        return MediaSource::Jikan->value;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.jikan.base_url'));
    }

    public function supportedTypes(): array
    {
        return [MediaType::Anime];
    }

    public function searchRequests(string $query, array $types, int $limit): array
    {
        return [
            'anime' => ProviderRequest::get(
                url: rtrim((string) config('services.jikan.base_url'), '/').'/anime',
                query: [
                    'q' => $query,
                    // Jikan membatasi limit di 25 per halaman.
                    'limit' => min(max($limit, 1), 25),
                    'order_by' => 'members',
                    'sort' => 'desc',
                    'sfw' => 'true',
                ],
            ),
        ];
    }

    public function parseSearch(string $name, Response $response): array
    {
        $results = [];

        foreach (Arr::get($response->json(), 'data', []) as $item) {
            $malId = Arr::get($item, 'mal_id');
            $title = Arr::get($item, 'title') ?? Arr::get($item, 'title_english');

            if (blank($malId) || blank($title)) {
                continue;
            }

            $airedFrom = Arr::get($item, 'aired.from');
            $airedFrom = filled($airedFrom) ? Str::before($airedFrom, 'T') : null;

            $results[] = new MediaResult(
                source: MediaSource::Jikan,
                mediaType: MediaType::Anime,
                externalId: (string) $malId,
                title: Arr::get($item, 'title_english') ?: $title,
                originalTitle: Arr::get($item, 'title_japanese') ?: $title,
                posterUrl: Arr::get($item, 'images.webp.large_image_url')
                    ?? Arr::get($item, 'images.jpg.large_image_url')
                    ?? Arr::get($item, 'images.jpg.image_url'),
                backdropUrl: Arr::get($item, 'trailer.images.maximum_image_url'),
                synopsis: filled(Arr::get($item, 'synopsis')) ? Arr::get($item, 'synopsis') : null,
                year: Arr::get($item, 'year') ? (int) Arr::get($item, 'year') : ($airedFrom ? (int) Str::before($airedFrom, '-') : null),
                releasedOn: $airedFrom,
                genres: $this->collectGenres($item),
                raw: $item,
                popularity: (float) Arr::get($item, 'members', 0),
            );
        }

        return $results;
    }

    /**
     * Jikan memecah kategori ke genres/themes/demographics — semuanya berguna
     * untuk statistik genre di fase berikutnya.
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function collectGenres(array $item): array
    {
        $names = [];

        foreach (['genres', 'themes', 'demographics'] as $bucket) {
            foreach (Arr::get($item, $bucket, []) as $entry) {
                $name = Arr::get($entry, 'name');

                if (filled($name)) {
                    $names[] = (string) $name;
                }
            }
        }

        return array_values(array_unique($names));
    }
}
