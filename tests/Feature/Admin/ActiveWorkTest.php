<?php

namespace Tests\Feature\Admin;

use App\Models\TaskType;
use App\Models\Timer;
use App\Models\TimerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_active_work(): void
    {
        $this->get('/admin/active-work')->assertRedirect(route('login'));
    }

    public function test_it_lists_active_agents_with_progress_status(): void
    {
        $user = User::factory()->create();

        $runningTimer = Timer::factory()->create([
            'completed' => false,
            'started_at' => now()->subMinutes(20),
        ]);

        TimerSession::factory()->for($runningTimer)->running()->create();

        $parkedTimer = Timer::factory()->create([
            'completed' => false,
            'started_at' => now()->subMinutes(40),
            'parked_at' => now()->subMinutes(5),
        ]);

        TimerSession::factory()->for($parkedTimer)->create([
            'started_at' => now()->subMinutes(35),
            'ended_at' => now()->subMinutes(20),
        ]);

        $pausedTimer = Timer::factory()->create([
            'completed' => false,
            'started_at' => now()->subMinutes(50),
        ]);

        TimerSession::factory()->for($pausedTimer)->create([
            'started_at' => now()->subMinutes(45),
            'ended_at' => now()->subMinutes(30),
        ]);

        Timer::factory()->completed(2.5)->create();

        $this->actingAs($user)
            ->get('/admin/active-work')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/ActiveWork')
                ->has('agents', 3)
                ->where('agents.0.id', $runningTimer->id)
                ->where('agents.0.status', 'in_progress')
                ->where('agents.1.id', $parkedTimer->id)
                ->where('agents.1.status', 'parked')
                ->where('agents.2.id', $pausedTimer->id)
                ->where('agents.2.status', 'on_hold')
                ->where('agents.2.elapsed_seconds', 900),
            );
    }

    public function test_it_lists_task_types_with_their_project(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create();

        $this->actingAs($user)
            ->get('/admin/active-work')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('taskTypes.0.id', $taskType->id)
                ->where('taskTypes.0.brand', $taskType->brand->name),
            );
    }

    public function test_it_updates_the_task_type_and_total_time_of_a_paused_timer(): void
    {
        $user = User::factory()->create();
        $timer = Timer::factory()->create([
            'completed' => false,
            'started_at' => now()->subHour(),
        ]);

        TimerSession::factory()->for($timer)->create([
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(50),
        ]);

        $taskType = TaskType::factory()->create();

        $this->actingAs($user)
            ->patch("/admin/active-work/{$timer->id}", [
                'task_type_id' => $taskType->id,
                'elapsed_seconds' => 5400,
            ])
            ->assertRedirect();

        $timer->refresh()->load('sessions');

        $this->assertSame($taskType->id, $timer->task_type_id);
        $this->assertSame(5400, $timer->elapsedSeconds());
    }

    public function test_it_updates_the_total_time_of_a_running_timer_without_stopping_it(): void
    {
        $user = User::factory()->create();
        $timer = Timer::factory()->create(['completed' => false]);

        TimerSession::factory()->for($timer)->running()->create([
            'started_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($user)
            ->patch("/admin/active-work/{$timer->id}", [
                'task_type_id' => $timer->task_type_id,
                'elapsed_seconds' => 3600,
            ])
            ->assertRedirect();

        $timer->refresh()->load('sessions');

        $this->assertNotNull($timer->currentSession());
        $this->assertSame(3600, $timer->elapsedSeconds());
    }

    public function test_it_shortens_a_timer_below_its_earlier_sessions(): void
    {
        $user = User::factory()->create();
        $timer = Timer::factory()->create(['completed' => false]);

        TimerSession::factory()->for($timer)->create([
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2),
        ]);
        TimerSession::factory()->for($timer)->create([
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($user)
            ->patch("/admin/active-work/{$timer->id}", [
                'task_type_id' => $timer->task_type_id,
                'elapsed_seconds' => 600,
            ])
            ->assertRedirect();

        $timer->refresh()->load('sessions');

        $this->assertCount(1, $timer->sessions);
        $this->assertSame(600, $timer->elapsedSeconds());
    }

    public function test_it_cannot_update_a_completed_timer(): void
    {
        $user = User::factory()->create();
        $timer = Timer::factory()->completed(2.0)->create();
        $taskType = TaskType::factory()->create();

        $this->actingAs($user)
            ->patch("/admin/active-work/{$timer->id}", [
                'task_type_id' => $taskType->id,
                'elapsed_seconds' => 3600,
            ])
            ->assertNotFound();
    }

    public function test_it_validates_the_active_timer_update(): void
    {
        $user = User::factory()->create();
        $timer = Timer::factory()->create(['completed' => false]);

        $this->actingAs($user)
            ->patch("/admin/active-work/{$timer->id}", [
                'task_type_id' => 999999,
                'elapsed_seconds' => -5,
            ])
            ->assertSessionHasErrors(['task_type_id', 'elapsed_seconds']);
    }

    public function test_guests_cannot_update_an_active_timer(): void
    {
        $timer = Timer::factory()->create(['completed' => false]);

        $this->patch("/admin/active-work/{$timer->id}", [])
            ->assertRedirect(route('login'));
    }
}
