<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\TaskType;
use App\Models\Timer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timer>
 */
class TimerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'task_type_id' => TaskType::factory(),
            'started_at' => now(),
            'ended_at' => null,
            'decimal_hours' => null,
            'completed' => false,
        ];
    }

    public function completed(float $hours = 1.0): static
    {
        return $this->state(fn (array $attributes) => [
            'completed' => true,
            'ended_at' => now(),
            'decimal_hours' => $hours,
        ]);
    }
}
