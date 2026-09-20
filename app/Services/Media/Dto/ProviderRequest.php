<?php

namespace App\Services\Media\Dto;

/**
 * Deskripsi satu panggilan HTTP yang akan dijalankan MediaSearchService.
 */
final readonly class ProviderRequest
{
    /**
     * @param  array<string, mixed>  $query  Query string, untuk request GET.
     * @param  array<string, mixed>  $payload  Body JSON, untuk request POST (mis. GraphQL).
     * @param  array<string, string>  $headers
     */
    private function __construct(
        public string $method,
        public string $url,
        public array $query = [],
        public array $payload = [],
        public array $headers = [],
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public static function get(string $url, array $query = [], array $headers = []): self
    {
        return new self('get', $url, query: $query, headers: $headers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public static function post(string $url, array $payload = [], array $headers = []): self
    {
        return new self('post', $url, payload: $payload, headers: $headers);
    }
}
