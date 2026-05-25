<?php

namespace Database\Factories;

use App\Models\Timer;
use App\Models\TimerSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimerSession>
 */
class TimerSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'timer_id' => Timer::factory(),
            'started_at' => now()->subMinutes(30),
            'ended_at' => now(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
        ]);
    }
}
