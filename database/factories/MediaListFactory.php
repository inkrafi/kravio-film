<?php

namespace Database\Factories;

use App\Enums\ListVisibility;
use App\Models\MediaList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaList>
 */
class MediaListFactory extends Factory
{
    protected $model = MediaList::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'visibility' => ListVisibility::Public,
            'is_ranked' => false,
        ];
    }

    public function visibility(ListVisibility $visibility): static
    {
        return $this->state(['visibility' => $visibility]);
    }
}
