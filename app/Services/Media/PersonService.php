<?php

namespace App\Services\Media;

use App\Models\MediaCache;
use App\Services\Media\Providers\TmdbProvider;
use App\Support\PersonNames;
use App\Support\Romanizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Halaman orang (sutradara, kreator, pemain): profil dari TMDB plus semua judul
 * yang pernah ia garap. Setiap judul disimpan ke media_cache supaya bisa dibuka
 * halaman detailnya.
 */
class PersonService
{
    private const CACHE_TTL = 60 * 60 * 24;

    /** Pekerjaan kru yang dianggap "menggarap" sebuah judul. */
    private const CREW_JOBS = ['Director', 'Creator'];

    public function __construct(
        private readonly TmdbProvider $tmdb,
        private readonly MediaSearchService $media,
    ) {}

    private const DEPARTMENTS = [
        'Acting' => 'Akting', 'Directing' => 'Penyutradaraan', 'Writing' => 'Penulisan',
        'Production' => 'Produksi', 'Creator' => 'Kreator', 'Camera' => 'Kamera',
        'Editing' => 'Penyuntingan', 'Sound' => 'Suara', 'Art' => 'Artistik',
        'Visual Effects' => 'Efek Visual', 'Crew' => 'Kru', 'Costume & Make-Up' => 'Kostum & Tata Rias',
        'Lighting' => 'Tata Cahaya',
    ];

    /**
     * known_for_department TMDB dalam bahasa Indonesia.
     */
    public static function departmentLabel(?string $department): ?string
    {
        return $department === null ? null : (self::DEPARTMENTS[$department] ?? $department);
    }

    public static function url(int $id, string $name): string
    {
        $slug = Str::slug(Romanizer::isLatin($name) ? $name : (Romanizer::romanize($name) ?? ''));

        return route('person.show', ['person' => $slug !== '' ? "{$id}-{$slug}" : (string) $id]);
    }

    /**
     * @return array{
     *     profile: array<string, mixed>,
     *     directed: list<array{media: MediaCache, roles: list<string>}>,
     *     acted: list<array{media: MediaCache, roles: list<string>}>
     * }|null  Null kalau orangnya tidak ada di TMDB.
     *
     * @throws \RuntimeException kalau TMDB gagal dihubungi.
     */
    public function find(int $id, ?string $preferredSlug = null): ?array
    {
        $cacheKey = "tmdb:person:{$id}:".config('services.tmdb.language');

        /** @var array{profile: array<string, mixed>, directed: list<array{id: int, roles: list<string>}>, acted: list<array{id: int, roles: list<string>}>}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            $person = $this->tmdb->person($id);

            if ($person === null) {
                return null;
            }

            $cached = $this->summarize($person);

            Cache::put($cacheKey, $cached, self::CACHE_TTL);
        }

        $ids = collect([...$cached['directed'], ...$cached['acted']])->pluck('id')->unique();
        $models = MediaCache::query()->whereIn('id', $ids)->get()->keyBy('id');

        $hydrate = fn (array $rows) => collect($rows)
            ->filter(fn (array $row) => $models->has($row['id']))
            ->map(fn (array $row) => ['media' => $models->get($row['id']), 'roles' => $row['roles']])
            ->values()
            ->all();

        $profile = $cached['profile'];

        // Samakan ejaan dengan halaman detail: pertama ejaan dari Gemini yang
        // sudah tersimpan di credits judul mana pun, lalu alias TMDB yang cocok
        // dengan slug tautan yang diklik.
        if ($profile['name_latin'] !== null) {
            $profile['name_latin'] = PersonNames::latinFromCredits($id)
                ?? collect($profile['latin_aliases'])->first(fn (string $alias) => Str::slug($alias) === $preferredSlug)
                ?? $profile['name_latin'];
        }

        return [
            'profile' => $profile,
            'directed' => $hydrate($cached['directed']),
            'acted' => $hydrate($cached['acted']),
        ];
    }

    /**
     * @param  array<string, mixed>  $person  Hasil TmdbProvider::person().
     * @return array<string, mixed>
     */
    private function summarize(array $person): array
    {
        $credits = collect($person['credits']);

        // Simpan semua judul sekaligus; id model dipakai untuk menyusun daftar.
        $models = $this->media->remember($credits->pluck('result')->all())
            ->keyBy(fn (MediaCache $media) => $media->source->value.':'.$media->media_type->value.':'.$media->external_id);

        $group = fn (Collection $rows) => $rows
            ->groupBy(fn (array $row) => $row['result']->key())
            ->filter(fn (Collection $rows, string $key) => $models->has($key))
            ->map(fn (Collection $rows, string $key) => [
                'id' => $models->get($key)->id,
                'date' => $rows->first()['result']->releasedOn,
                'roles' => $rows->pluck('role')->filter()->unique()->values()->all(),
            ])
            // Terbaru di atas; judul tanpa tanggal (belum rilis/tak diketahui) di bawah.
            ->sortByDesc(fn (array $row) => $row['date'] ?? '0000')
            ->map(fn (array $row) => ['id' => $row['id'], 'roles' => $row['roles']])
            ->values()
            ->all();

        return [
            'profile' => [
                'id' => $person['id'],
                'name' => $person['name'],
                'name_latin' => $this->latinName($person),
                'latin_aliases' => array_values(array_map('trim', array_filter($person['also_known_as'], fn ($alias) => filled($alias) && Romanizer::isLatin($alias)))),
                'photo_url' => $person['photo_url'],
                'department' => $person['department'],
                'birthday' => $person['birthday'],
                'deathday' => $person['deathday'],
                'place_of_birth' => $person['place_of_birth'],
                'biography' => $person['biography'],
                'biography_language' => $person['biography_language'],
            ],
            'directed' => $group($credits->where('as', 'crew')->filter(fn (array $row) => in_array($row['role'], self::CREW_JOBS, true))),
            'acted' => $group($credits->where('as', 'cast')),
        ];
    }

    /**
     * Nama beraksara non-latin: pakai ejaan latin dari also_known_as TMDB kalau
     * ada (biasanya ejaan resmi, mis. "Lee Sun-kyun"), selain itu romanisasi.
     *
     * @param  array<string, mixed>  $person
     */
    private function latinName(array $person): ?string
    {
        if (Romanizer::isLatin($person['name'])) {
            return null;
        }

        foreach ($person['also_known_as'] as $alias) {
            if (filled($alias) && Romanizer::isLatin($alias)) {
                return trim($alias);
            }
        }

        return Romanizer::romanize($person['name']);
    }
}
