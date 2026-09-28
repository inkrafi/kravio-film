<?php

namespace App\Contracts;

use Illuminate\Http\Client\Response;

/**
 * Provider yang response pencariannya juga memuat orang (aktor, sutradara, …),
 * mis. /search/multi TMDB. Dipakai MediaSearchService tanpa request tambahan.
 */
interface SearchesPeople
{
    /**
     * @return list<array{id: int, name: string, name_latin: ?string, photo_url: ?string, department: ?string, known_for: list<string>, popularity: float}>
     */
    public function parsePeople(string $name, Response $response): array;
}
