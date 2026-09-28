<?php

namespace Database\Factories;

use App\Models\MediaCache;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'media_cache_id' => MediaCache::factory(),
            'body' => fake()->paragraph(),
            'contains_spoiler' => false,
        ];
    }
}
