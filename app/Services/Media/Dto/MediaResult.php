<?php

namespace App\Services\Media\Dto;

use App\Enums\MediaSource;
use App\Enums\MediaType;

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
    ) {}

    /**
     * Kunci unik lintas sumber.
     */
    public function key(): string
    {
        return "{$this->source->value}:{$this->mediaType->value}:{$this->externalId}";
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
        ];
    }
}
