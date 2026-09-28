<?php

namespace App\Services\Media\Dto;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Support\Romanizer;
use Illuminate\Support\Arr;

/**
 * Bentuk seragam hasil pencarian dari sumber mana pun, siap disimpan ke media_cache.
 */
final readonly class MediaResult
{
    /**
     * @param  list<string>  $genres
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public MediaSource $source,
        public MediaType $mediaType,
        public string $externalId,
        public string $title,
        public ?string $originalTitle = null,
        public ?string $posterUrl = null,
        public ?string $backdropUrl = null,
        public ?string $synopsis = null,
        public ?int $year = null,
        public ?string $releasedOn = null,
        public array $genres = [],
        public array $raw = [],
        public float $popularity = 0.0,
        public ?string $titleLatin = null,
        public ?string $originalTitleLatin = null,
    ) {}

    /**
     * Kunci unik lintas sumber.
     */
    public function key(): string
    {
        return "{$this->source->value}:{$this->mediaType->value}:{$this->externalId}";
    }

    /**
     * Lengkapi field yang kosong dengan isi dari hasil lain untuk media yang sama.
     *
     * Dipakai saat satu sumber dipanggil dua kali dengan bahasa berbeda: versi
     * bahasa utama menang, versi cadangan hanya menambal yang kosong.
     */
    public function fillGapsFrom(self $other): self
    {
        return new self(
            source: $this->source,
            mediaType: $this->mediaType,
            externalId: $this->externalId,
            title: $this->title,
            originalTitle: $this->originalTitle ?? $other->originalTitle,
            posterUrl: $this->posterUrl ?? $other->posterUrl,
            backdropUrl: $this->backdropUrl ?? $other->backdropUrl,
            synopsis: $this->synopsis ?? $other->synopsis,
            year: $this->year ?? $other->year,
            releasedOn: $this->releasedOn ?? $other->releasedOn,
            genres: $this->genres !== [] ? $this->genres : $other->genres,
            raw: $this->raw !== [] ? $this->raw : $other->raw,
            popularity: $this->popularity !== 0.0 ? $this->popularity : $other->popularity,
            titleLatin: $this->titleLatin ?? $other->titleLatin,
            originalTitleLatin: $this->originalTitleLatin ?? $other->originalTitleLatin,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toCacheRow(): array
    {
        return [
            'source' => $this->source->value,
            'media_type' => $this->mediaType->value,
            'external_id' => $this->externalId,
            'title' => $this->title,
            'original_title' => $this->originalTitle,
            'poster_url' => $this->posterUrl,
            'backdrop_url' => $this->backdropUrl,
            'synopsis' => $this->synopsis,
            'year' => $this->year,
            'released_on' => $this->releasedOn,
            'genres' => json_encode($this->genres),
            'raw_payload' => json_encode($this->raw),
            // Hanya versi latin dari sumber; romanisasi cadangan diisi terpisah
            // oleh MediaSearchService supaya tidak menimpa versi yang lebih baik.
            ...$this->latinColumns(withFallback: false),
        ];
    }

    /**
     * Versi latin judul yang ditulis dalam aksara lain. Sumber yang punya versi
     * latin sendiri (judul en-US TMDB, romaji AniList/Jikan) diutamakan; sisanya
     * romanisasi cadangan. Judul yang sudah latin tidak perlu versi latin.
     *
     * @return array{title_latin: ?string, original_title_latin: ?string}
     */
    public function latinColumns(bool $withFallback = true): array
    {
        $language = Arr::get($this->raw, 'original_language');

        $latin = fn (?string $text, ?string $fromSource) => match (true) {
            Romanizer::isLatin($text) => null,
            $this->latinOrNull($fromSource) !== null => $fromSource,
            $withFallback => Romanizer::romanize($text, $language),
            default => null,
        };

        return [
            'title_latin' => $latin($this->title, $this->titleLatin),
            'original_title_latin' => $latin($this->originalTitle, $this->originalTitleLatin),
        ];
    }

    private function latinOrNull(?string $text): ?string
    {
        return filled($text) && Romanizer::isLatin($text) ? $text : null;
    }
}
