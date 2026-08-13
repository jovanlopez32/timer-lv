<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Brand;
use App\Models\TaskType;
use App\Models\Timer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_it_ranks_developers_by_completed_tasks_in_the_current_week(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $brand = Brand::factory()->create();
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $top = Agent::factory()->create(['name' => 'Top Dev']);
        $quiet = Agent::factory()->create(['name' => 'Quiet Dev']);

        Timer::factory()->for($top)->for($taskType, 'taskType')->completed(2.0)->create(['ended_at' => now()]);
        Timer::factory()->for($top)->for($taskType, 'taskType')->completed(3.0)->create(['ended_at' => now()]);
        Timer::factory()->for($quiet)->for($taskType, 'taskType')->completed(1.0)->create(['ended_at' => now()->subWeeks(2)]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('period.range', 'this_week')
            ->where('developers.0.name', 'Top Dev')
            ->where('developers.0.tasks_count', 2)
            ->where('developers.0.hours', 5)
            ->where('totals.tasksCount', 2)
            ->where('month.sitesCount', 1)
        );
    }

    public function test_it_supports_a_custom_date_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $brand = Brand::factory()->create();
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);
        $agent = Agent::factory()->create();

        Timer::factory()->for($agent)->for($taskType, 'taskType')->completed(4.0)->create([
            'ended_at' => now()->subMonth(),
        ]);

        $response = $this->get(route('dashboard', [
            'range' => 'custom',
            'from' => now()->subMonth()->startOfDay()->toDateString(),
            'to' => now()->subMonth()->endOfDay()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('period.range', 'custom')
            ->where('totals.tasksCount', 1)
            ->where('totals.hoursSum', 4)
        );
    }
}
