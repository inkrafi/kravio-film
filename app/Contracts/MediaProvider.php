<?php

namespace App\Contracts;

use App\Enums\MediaType;
use App\Services\Media\Dto\MediaResult;
use App\Services\Media\Dto\ProviderRequest;
use Illuminate\Http\Client\Response;

/**
 * Sumber data media eksternal (TMDB, Jikan, AniList, ...).
 *
 * Provider tidak menembak HTTP sendiri: ia hanya mendeskripsikan request yang
 * perlu dijalankan lalu menerjemahkan response-nya. MediaSearchService yang
 * menjalankan semua request itu paralel lewat Http::pool().
 */
interface MediaProvider
{
    /**
     * Identitas singkat provider, dipakai sebagai prefix key di pool HTTP.
     */
    public function key(): string;

    /**
     * False kalau kredensial belum diisi — provider akan dilewati, bukan error.
     */
    public function isConfigured(): bool;

    /**
     * Media type yang bisa dilayani provider ini.
     *
     * @return list<MediaType>
     */
    public function supportedTypes(): array;

    /**
     * Request yang perlu dijalankan untuk sebuah pencarian.
     *
     * @param  list<MediaType>  $types  Media type yang diminta (sudah difilter).
     * @return array<string, ProviderRequest>
     */
    public function searchRequests(string $query, array $types, int $limit): array;

    /**
     * Terjemahkan satu response jadi hasil ternormalisasi.
     *
     * @return list<MediaResult>
     */
    public function parseSearch(string $name, Response $response): array;
}
