<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\TaskType;
use App\Models\Timer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_reports(): void
    {
        $this->get('/admin/reports')->assertRedirect(route('login'));
    }

    public function test_it_lists_task_types_grouped_by_project(): void
    {
        $user = User::factory()->create();

        $zebra = Brand::factory()->create(['name' => 'Zebra']);
        $acme = Brand::factory()->create(['name' => 'Acme']);

        $zebraTask = TaskType::factory()->for($zebra)->create(['name' => 'Design']);
        $acmeTask = TaskType::factory()->for($acme)->create(['name' => 'Design']);

        $this->actingAs($user)
            ->get('/admin/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('taskTypes', 2)
                ->where('taskTypes.0.id', $acmeTask->id)
                ->where('taskTypes.0.brand', 'Acme')
                ->where('taskTypes.1.id', $zebraTask->id)
                ->where('taskTypes.1.brand', 'Zebra'),
            );
    }

    public function test_it_sorts_completed_timers_by_most_recent_by_default(): void
    {
        $user = User::factory()->create();

        $oldest = Timer::factory()->completed(5.0)->create([
            'ended_at' => now()->subDays(3),
        ]);
        $newest = Timer::factory()->completed(1.0)->create([
            'ended_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get('/admin/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Reports')
                ->where('filters.sort', 'recent')
                ->where('timers.data.0.id', $newest->id)
                ->where('timers.data.1.id', $oldest->id),
            );
    }

    public function test_it_sorts_completed_timers_by_hours_descending(): void
    {
        $user = User::factory()->create();

        $shortest = Timer::factory()->completed(0.5)->create();
        $longest = Timer::factory()->completed(4.25)->create();

        $this->actingAs($user)
            ->get('/admin/reports?sort=hours_desc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.sort', 'hours_desc')
                ->where('timers.data.0.id', $longest->id)
                ->where('timers.data.1.id', $shortest->id),
            );
    }

    public function test_it_sorts_completed_timers_by_hours_ascending(): void
    {
        $user = User::factory()->create();

        $longest = Timer::factory()->completed(4.25)->create();
        $shortest = Timer::factory()->completed(0.5)->create();

        $this->actingAs($user)
            ->get('/admin/reports?sort=hours_asc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('timers.data.0.id', $shortest->id)
                ->where('timers.data.1.id', $longest->id),
            );
    }

    public function test_it_rejects_an_unknown_sort(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/reports?sort=agent')
            ->assertSessionHasErrors('sort');
    }

    public function test_it_exports_sorted_timers(): void
    {
        $user = User::factory()->create();

        Timer::factory()->completed(0.5)->create();
        Timer::factory()->completed(4.25)->create();

        $response = $this->actingAs($user)
            ->get('/admin/reports/export?sort=hours_desc')
            ->assertOk();

        $rows = array_values(array_filter(explode("\n", $response->streamedContent())));

        $this->assertStringContainsString('4.25', $rows[1]);
        $this->assertStringContainsString('0.50', $rows[2]);
    }
}
