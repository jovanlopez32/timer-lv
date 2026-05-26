<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Brand;
use App\Models\TaskType;
use App\Models\Timer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_returns_the_landing_page(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_it_shows_the_agent_timer_page_with_task_types_of_its_brand(): void
    {
        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create([
            'slug' => 'luis-hurtado',
            'brand_id' => $brand->id,
        ]);
        TaskType::factory()->count(3)->create(['brand_id' => $brand->id]);
        TaskType::factory()->count(2)->create();

        $this->get('/luis-hurtado')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicTimer')
                ->where('agent.slug', 'luis-hurtado')
                ->where('agent.id', $agent->id)
                ->has('taskTypes', 3)
                ->where('activeTimer', null),
            );
    }

    public function test_it_starts_a_timer_and_creates_a_session(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create([
            'slug' => 'luis-hurtado',
            'brand_id' => $brand->id,
        ]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->post("/{$agent->slug}/timers", [
            'task_type_id' => $taskType->id,
        ])->assertRedirect("/{$agent->slug}");

        $this->assertDatabaseCount('timers', 1);
        $this->assertDatabaseCount('timer_sessions', 1);

        $timer = Timer::first();
        $this->assertFalse($timer->completed);
        $this->assertEquals($agent->id, $timer->agent_id);
        $this->assertEquals($taskType->id, $timer->task_type_id);
        $this->assertNull($timer->sessions->first()->ended_at);
    }

    public function test_it_rejects_a_task_type_from_another_brand(): void
    {
        $brand = Brand::factory()->create();
        $otherBrand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $foreignTaskType = TaskType::factory()->create(['brand_id' => $otherBrand->id]);

        $this->post("/{$agent->slug}/timers", [
            'task_type_id' => $foreignTaskType->id,
        ])->assertSessionHasErrors('task_type_id');

        $this->assertDatabaseCount('timers', 0);
    }

    public function test_it_pauses_a_running_session(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        Carbon::setTestNow('2026-05-23 10:00:00');
        $timer = Timer::first();

        $this->post("/timers/{$timer->id}/pause")
            ->assertRedirect("/{$agent->slug}");

        $session = $timer->sessions()->first();
        $this->assertNotNull($session->ended_at);
        $this->assertEquals('2026-05-23 10:00:00', $session->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_it_resumes_after_a_pause_creating_a_new_session(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        Carbon::setTestNow('2026-05-23 10:00:00');
        $timer = Timer::first();
        $this->post("/timers/{$timer->id}/pause");

        Carbon::setTestNow('2026-05-23 11:00:00');
        $this->post("/timers/{$timer->id}/resume")
            ->assertRedirect("/{$agent->slug}");

        $this->assertEquals(2, $timer->sessions()->count());
        $this->assertNull($timer->currentSession()->ended_at);
    }

    public function test_it_completes_a_timer_and_calculates_decimal_hours(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);
        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        Carbon::setTestNow('2026-05-23 10:00:00');
        $timer = Timer::first();
        $this->post("/timers/{$timer->id}/pause");

        Carbon::setTestNow('2026-05-23 11:00:00');
        $this->post("/timers/{$timer->id}/resume");

        Carbon::setTestNow('2026-05-23 12:00:00');
        $this->post("/timers/{$timer->id}/complete")
            ->assertRedirect("/{$agent->slug}");

        $timer->refresh();
        $this->assertTrue($timer->completed);
        $this->assertEquals(3.0, (float) $timer->decimal_hours);
        $this->assertEquals('2026-05-23 12:00:00', $timer->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_it_calculates_decimal_hours_with_half_hour(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);
        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        Carbon::setTestNow('2026-05-23 11:30:00');
        $timer = Timer::first();
        $this->post("/timers/{$timer->id}/complete");

        $timer->refresh();
        $this->assertEquals(3.5, (float) $timer->decimal_hours);
    }

    public function test_show_returns_a_correct_elapsed_seconds_snapshot_for_running_timer(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create([
            'slug' => 'snapshot-agent',
            'brand_id' => $brand->id,
        ]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        Carbon::setTestNow('2026-05-23 10:05:00');

        $this->get('/snapshot-agent')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeTimer.is_running', true)
                ->where('activeTimer.elapsed_seconds', 300),
            );
    }

    public function test_show_returns_paused_elapsed_seconds_without_advancing(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create([
            'slug' => 'paused-agent',
            'brand_id' => $brand->id,
        ]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        Carbon::setTestNow('2026-05-23 10:05:00');
        $timer = Timer::first();
        $this->post("/timers/{$timer->id}/pause");

        Carbon::setTestNow('2026-05-23 11:00:00');

        $this->get('/paused-agent')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeTimer.is_running', false)
                ->where('activeTimer.elapsed_seconds', 300),
            );
    }

    public function test_it_prevents_starting_a_second_timer_for_the_same_agent(): void
    {
        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id])
            ->assertRedirect();

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id])
            ->assertSessionHasErrors('task_type_id');

        $this->assertDatabaseCount('timers', 1);
    }

    public function test_it_rejects_parking_a_running_timer(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);
        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        $timer = Timer::first();

        $this->post("/timers/{$timer->id}/park")
            ->assertSessionHasErrors('timer');

        $this->assertNull($timer->fresh()->parked_at);
    }

    public function test_it_parks_a_paused_timer(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);
        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        Carbon::setTestNow('2026-05-23 09:00:00');
        $timer = Timer::first();
        $this->post("/timers/{$timer->id}/pause");

        Carbon::setTestNow('2026-05-23 09:05:00');
        $this->post("/timers/{$timer->id}/park")
            ->assertRedirect("/{$agent->slug}");

        $timer->refresh();
        $this->assertNotNull($timer->parked_at);
        $this->assertEquals('2026-05-23 09:05:00', $timer->parked_at->format('Y-m-d H:i:s'));
    }

    public function test_parked_timer_elapsed_seconds_stay_frozen(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create([
            'slug' => 'parker',
            'brand_id' => $brand->id,
        ]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);
        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        Carbon::setTestNow('2026-05-23 08:10:00');
        $timer = Timer::first();
        $this->post("/timers/{$timer->id}/pause");
        $this->post("/timers/{$timer->id}/park");

        Carbon::setTestNow('2026-05-23 12:00:00');

        $this->get('/parker')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeTimer', null)
                ->has('parkedTimers', 1)
                ->where('parkedTimers.0.elapsed_seconds', 600)
                ->where('parkedTimers.0.id', $timer->id),
            );
    }

    public function test_a_parked_timer_does_not_block_starting_a_new_one(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);
        $otherTaskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);
        $timer = Timer::first();
        $this->post("/timers/{$timer->id}/pause");
        $this->post("/timers/{$timer->id}/park");

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $otherTaskType->id])
            ->assertRedirect("/{$agent->slug}");

        $this->assertDatabaseCount('timers', 2);
    }

    public function test_unpark_is_blocked_when_another_active_timer_exists(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);
        $otherTaskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);
        $first = Timer::first();
        $this->post("/timers/{$first->id}/pause");
        $this->post("/timers/{$first->id}/park");

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $otherTaskType->id]);

        $this->post("/timers/{$first->id}/unpark")
            ->assertSessionHasErrors('timer');

        $this->assertNotNull($first->fresh()->parked_at);
    }

    public function test_unpark_restores_a_parked_timer_as_paused(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $brand = Brand::factory()->create();
        $agent = Agent::factory()->create(['brand_id' => $brand->id]);
        $taskType = TaskType::factory()->create(['brand_id' => $brand->id]);

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);
        $timer = Timer::first();
        $this->post("/timers/{$timer->id}/pause");
        $this->post("/timers/{$timer->id}/park");

        $this->post("/timers/{$timer->id}/unpark")
            ->assertRedirect("/{$agent->slug}");

        $timer->refresh();
        $this->assertNull($timer->parked_at);
        $this->assertFalse($timer->completed);
        $this->assertNull($timer->currentSession());
    }
}
