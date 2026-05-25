<?php

namespace Tests\Feature;

use App\Models\Agent;
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

    public function test_it_shows_the_agent_timer_page_with_task_types(): void
    {
        $agent = Agent::factory()->create(['slug' => 'luis-hurtado']);
        TaskType::factory()->count(3)->create();

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

        $agent = Agent::factory()->create(['slug' => 'luis-hurtado']);
        $taskType = TaskType::factory()->create();

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

    public function test_it_pauses_a_running_session(): void
    {
        Carbon::setTestNow('2026-05-23 08:00:00');

        $agent = Agent::factory()->create();
        $taskType = TaskType::factory()->create();

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

        $agent = Agent::factory()->create();
        $taskType = TaskType::factory()->create();

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

        $agent = Agent::factory()->create();
        $taskType = TaskType::factory()->create();
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

        $agent = Agent::factory()->create();
        $taskType = TaskType::factory()->create();
        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id]);

        Carbon::setTestNow('2026-05-23 11:30:00');
        $timer = Timer::first();
        $this->post("/timers/{$timer->id}/complete");

        $timer->refresh();
        $this->assertEquals(3.5, (float) $timer->decimal_hours);
    }

    public function test_it_prevents_starting_a_second_timer_for_the_same_agent(): void
    {
        $agent = Agent::factory()->create();
        $taskType = TaskType::factory()->create();

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id])
            ->assertRedirect();

        $this->post("/{$agent->slug}/timers", ['task_type_id' => $taskType->id])
            ->assertSessionHasErrors('task_type_id');

        $this->assertDatabaseCount('timers', 1);
    }
}
