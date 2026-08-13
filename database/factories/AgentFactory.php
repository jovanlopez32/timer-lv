<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        ];
    }

    /**
     * Attach the given brand to the agent once created, on top of any brand
     * already attached via another forBrand() call in the same chain.
     */
    public function forBrand(Brand|int $brand): static
    {
        $brandId = $brand instanceof Brand ? $brand->id : $brand;

        return $this->afterCreating(function (Agent $agent) use ($brandId) {
            $agent->brands()->syncWithoutDetaching([$brandId]);
        });
    }
}
