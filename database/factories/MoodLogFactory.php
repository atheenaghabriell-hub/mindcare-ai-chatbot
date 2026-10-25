<?php

namespace Database\Factories;

use App\Models\MoodLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoodLog>
 */
class MoodLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mood' => fake()->randomElement(['very_low', 'low', 'neutral', 'good', 'very_good']),
            'intensity' => fake()->numberBetween(1, 5),
            'recorded_at' => now(),
        ];
    }
}
