<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\SentimentLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SentimentLog>
 */
class SentimentLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'label' => fake()->randomElement(['positive', 'neutral', 'negative']),
            'score' => fake()->randomFloat(5, -1, 1),
            'analyzer' => 'test',
            'analyzed_at' => now(),
        ];
    }
}
