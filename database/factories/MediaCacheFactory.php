<?php

namespace Database\Factories;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\MediaCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaCache>
 */
class MediaCacheFactory extends Factory
{
    protected $model = MediaCache::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(1980, 2026);

        return [
            'external_id' => (string) fake()->unique()->numberBetween(1, 999999),
            'source' => MediaSource::Tmdb,
            'media_type' => MediaType::Film,
            'title' => ucwords(fake()->words(3, true)),
            'original_title' => null,
            'poster_url' => 'https://image.tmdb.org/t/p/w342/'.fake()->lexify('??????????').'.jpg',
            'backdrop_url' => null,
            'synopsis' => fake()->paragraph(),
            'year' => $year,
            'released_on' => fake()->dateTimeBetween("{$year}-01-01", "{$year}-12-31")->format('Y-m-d'),
            'genres' => fake()->randomElements(['Drama', 'Aksi', 'Komedi', 'Horor', 'Fiksi Ilmiah', 'Romantis'], 2),
            'raw_payload' => [],
            'synced_at' => now(),
        ];
    }

    public function film(): static
    {
        return $this->state(['media_type' => MediaType::Film, 'source' => MediaSource::Tmdb]);
    }

    public function series(): static
    {
        return $this->state(['media_type' => MediaType::Series, 'source' => MediaSource::Tmdb]);
    }

    /**
     * Anime dari AniList: default berepisode (Series), oper Film untuk anime movie.
     */
    public function anime(MediaType $type = MediaType::Series): static
    {
        return $this->state(fn () => [
            'media_type' => $type,
            'source' => MediaSource::Anilist,
            'poster_url' => 'https://img.anili.st/media/'.fake()->lexify('?????').'.jpg',
            'genres' => fake()->randomElements(['Shounen', 'Isekai', 'Slice of Life', 'Mecha', 'Seinen'], 2),
        ]);
    }
}
