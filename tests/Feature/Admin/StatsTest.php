<?php

namespace Tests\Feature\Admin;

use App\Models\Agent;
use App\Models\Brand;
use App\Models\TaskType;
use App\Models\Timer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_stats(): void
    {
        $this->get('/admin/stats')->assertRedirect(route('login'));
    }

    public function test_it_computes_average_median_min_max_per_task_type_under_brand(): void
    {
        $user = User::factory()->create();

        $brand = Brand::factory()->create(['name' => 'Leadventure']);
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Development',
        ]);

        foreach ([1.5, 2.5, 4.5, 8.5] as $hours) {
            Timer::factory()
                ->for($agent)
                ->for($taskType, 'taskType')
                ->completed($hours)
                ->create();
        }

        $this->actingAs($user)
            ->get('/admin/stats')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Stats')
                ->has('brands', 1)
                ->where('brands.0.name', 'Leadventure')
                ->has('brands.0.task_types', 1)
                ->where('brands.0.task_types.0.count', 4)
                ->where('brands.0.task_types.0.average', 4.25)
                ->where('brands.0.task_types.0.median', 3.5)
                ->where('brands.0.task_types.0.min', 1.5)
                ->where('brands.0.task_types.0.max', 8.5),
            );
    }

    public function test_it_returns_nulls_for_task_types_without_completed_timers(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->actingAs($user)
            ->get('/admin/stats')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('brands.0.task_types.0.count', 0)
                ->where('brands.0.task_types.0.average', null)
                ->where('brands.0.task_types.0.median', null)
                ->where('brands.0.task_types.0.min', null)
                ->where('brands.0.task_types.0.max', null),
            );
    }
}
