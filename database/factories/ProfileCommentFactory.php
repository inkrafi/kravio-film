<?php

namespace Database\Factories;

use App\Models\ProfileComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileComment>
 */
class ProfileCommentFactory extends Factory
{
    protected $model = ProfileComment::class;

    public function definition(): array
    {
        return [
            'commenter_id' => User::factory(),
            'profile_user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
