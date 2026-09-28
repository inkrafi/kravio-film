<?php

namespace App\Services\Media\Dto;

use App\Models\MediaCache;
use Illuminate\Support\Collection;

/**
 * Hasil pencarian gabungan: media yang ketemu plus catatan kondisi tiap sumber,
 * supaya UI bisa memberi tahu "anime lagi tidak bisa diambil" tanpa gagal total.
 */
final readonly class MediaSearchResults
{
    /**
     * @param  Collection<int, MediaCache>  $media
     * @param  list<string>  $failedSources  Sumber yang gagal dan tidak tergantikan.
     * @param  list<string>  $skippedSources  Sumber yang dilewati karena belum dikonfigurasi.
     * @param  array<string, string>  $fallbackSources  Sumber gagal => sumber cadangan yang dipakai.
     * @param  list<array{id: int, name: string, name_latin: ?string, photo_url: ?string, department: ?string, known_for: list<string>, popularity: float}>  $people
     *                                                                                                                                                                Orang (aktor, sutradara, …) yang cocok dengan kata kunci.
     */
    public function __construct(
        public Collection $media,
        public array $failedSources = [],
        public array $skippedSources = [],
        public array $fallbackSources = [],
        public array $people = [],
    ) {}

    public static function empty(): self
    {
        return new self(collect());
    }

    public function isEmpty(): bool
    {
        return $this->media->isEmpty() && $this->people === [];
    }

    public function hasProblems(): bool
    {
        return $this->failedSources !== [] || $this->skippedSources !== [];
    }

    public function usedFallback(): bool
    {
        return $this->fallbackSources !== [];
    }
}
