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
        $agent = Agent::factory()->forBrand($brand->id)->create();
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

    public function test_it_filters_by_agent(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $agentA = Agent::factory()->forBrand($brand->id)->create();
        $agentB = Agent::factory()->forBrand($brand->id)->create();
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        Timer::factory()->for($agentA)->for($taskType, 'taskType')->completed(2.0)->create();
        Timer::factory()->for($agentB)->for($taskType, 'taskType')->completed(6.0)->create();

        $this->actingAs($user)
            ->get('/admin/stats?'.http_build_query(['agent_ids' => [$agentA->id]]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('brands.0.task_types.0.count', 1)
                ->where('brands.0.task_types.0.average', 2)
                ->where('filters.agent_ids', [$agentA->id]),
            );
    }

    public function test_it_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $agent = Agent::factory()->forBrand($brand->id)->create();
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        Timer::factory()
            ->for($agent)
            ->for($taskType, 'taskType')
            ->completed(3.0)
            ->create(['ended_at' => now()->subDays(10)]);

        Timer::factory()
            ->for($agent)
            ->for($taskType, 'taskType')
            ->completed(9.0)
            ->create(['ended_at' => now()]);

        $this->actingAs($user)
            ->get('/admin/stats?'.http_build_query([
                'from' => now()->subDay()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('brands.0.task_types.0.count', 1)
                ->where('brands.0.task_types.0.average', 9),
            );
    }

    public function test_it_returns_agents_for_the_filter_list(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['name' => 'Leadventure']);
        $agent = Agent::factory()->forBrand($brand->id)->create(['name' => 'Agent Smith']);

        $this->actingAs($user)
            ->get('/admin/stats')
            ->assertInertia(fn ($page) => $page
                ->has('agents', 1)
                ->where('agents.0.id', $agent->id)
                ->where('agents.0.name', 'Agent Smith')
                ->where('agents.0.brand', 'Leadventure'),
            );
    }
}
