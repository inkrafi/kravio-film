<?php

namespace App\Services\Media\Providers;

use App\Contracts\MediaProvider;
use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Services\Media\Dto\MediaResult;
use App\Services\Media\Dto\ProviderRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Sumber anime utama lewat GraphQL AniList. Tanpa API key.
 *
 * Rate limit-nya 90 request/menit, jadi cache hasil di MediaSearchService yang
 * menjaga kita tetap di bawah batas itu.
 */
class AniListProvider implements MediaProvider
{
    private const QUERY = <<<'GRAPHQL'
        query ($search: String, $perPage: Int) {
          Page(page: 1, perPage: $perPage) {
            media(search: $search, type: ANIME, sort: SEARCH_MATCH, isAdult: false) {
              id
              title { romaji english native }
              description(asHtml: false)
              coverImage { extraLarge large }
              bannerImage
              startDate { year month day }
              seasonYear
              format
              genres
              tags { name rank isGeneralSpoiler }
              popularity
            }
          }
        }
        GRAPHQL;

    public function key(): string
    {
        return MediaSource::Anilist->value;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.anilist.base_url'));
    }

    public function supportedTypes(): array
    {
        return [MediaType::Anime];
    }

    public function searchRequests(string $query, array $types, int $limit): array
    {
        return [
            'anime' => ProviderRequest::post(
                url: (string) config('services.anilist.base_url'),
                payload: [
                    'query' => self::QUERY,
                    'variables' => [
                        'search' => $query,
                        // AniList membatasi 50 item per halaman.
                        'perPage' => min(max($limit, 1), 50),
                    ],
                ],
            ),
        ];
    }

    public function parseSearch(string $name, Response $response): array
    {
        $body = $response->json();

        // GraphQL bisa membalas 200 tapi isinya error; anggap itu kegagalan sumber.
        if (filled(Arr::get($body, 'errors'))) {
            throw new RuntimeException(
                'AniList menolak query: '.(Arr::get($body, 'errors.0.message') ?? 'alasan tidak diketahui')
            );
        }

        $items = Arr::get($body, 'data.Page.media');

        if (! is_array($items)) {
            throw new RuntimeException('Bentuk response AniList tidak dikenali.');
        }

        $results = [];

        foreach ($items as $item) {
            $id = Arr::get($item, 'id');
            $romaji = Arr::get($item, 'title.romaji');
            $title = Arr::get($item, 'title.english') ?: $romaji;

            if (blank($id) || blank($title)) {
                continue;
            }

            $results[] = new MediaResult(
                source: MediaSource::Anilist,
                mediaType: MediaType::Anime,
                externalId: (string) $id,
                title: $title,
                originalTitle: Arr::get($item, 'title.native') ?: $romaji,
                posterUrl: Arr::get($item, 'coverImage.extraLarge') ?? Arr::get($item, 'coverImage.large'),
                backdropUrl: Arr::get($item, 'bannerImage'),
                synopsis: $this->plainText(Arr::get($item, 'description')),
                year: $this->year($item),
                releasedOn: $this->startDate($item),
                genres: $this->collectGenres($item),
                raw: $item,
                popularity: (float) Arr::get($item, 'popularity', 0),
            );
        }

        return $results;
    }

    /**
     * Sinopsis AniList mengandung markup HTML ringan walau diminta asHtml: false.
     */
    private function plainText(?string $description): ?string
    {
        if (blank($description)) {
            return null;
        }

        $text = preg_replace('/<br\s*\/?>/i', "\n", $description) ?? $description;
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function year(array $item): ?int
    {
        $year = Arr::get($item, 'seasonYear') ?? Arr::get($item, 'startDate.year');

        return $year ? (int) $year : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function startDate(array $item): ?string
    {
        $year = Arr::get($item, 'startDate.year');
        $month = Arr::get($item, 'startDate.month');
        $day = Arr::get($item, 'startDate.day');

        if (! $year || ! $month || ! $day) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Genre resmi plus beberapa tag paling relevan, supaya statistik genre tetap
     * sebanding dengan data dari Jikan (yang ikut menyertakan theme & demographic).
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function collectGenres(array $item): array
    {
        $names = array_map('strval', Arr::get($item, 'genres') ?? []);

        $tags = collect(Arr::get($item, 'tags') ?? [])
            ->reject(fn ($tag) => (bool) Arr::get($tag, 'isGeneralSpoiler'))
            ->filter(fn ($tag) => (int) Arr::get($tag, 'rank', 0) >= 60)
            ->sortByDesc(fn ($tag) => (int) Arr::get($tag, 'rank', 0))
            ->take(3)
            ->map(fn ($tag) => (string) Arr::get($tag, 'name'))
            ->filter()
            ->all();

        return array_values(array_unique([...$names, ...$tags]));
    }
}
