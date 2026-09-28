<?php

namespace Database\Factories;

use App\Enums\WatchStatus;
use App\Models\MediaCache;
use App\Models\User;
use App\Models\WatchEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchEntry>
 */
class WatchEntryFactory extends Factory
{
    protected $model = WatchEntry::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'media_cache_id' => MediaCache::factory(),
            'status' => WatchStatus::Watched,
            'rating' => fake()->numberBetween(1, 10),
            'watched_at' => now(),
        ];
    }

    public function watchlist(): static
    {
        return $this->state([
            'status' => WatchStatus::Watchlist,
            'rating' => null,
            'watched_at' => null,
        ]);
    }

    public function unrated(): static
    {
        return $this->state(['rating' => null]);
    }
}
