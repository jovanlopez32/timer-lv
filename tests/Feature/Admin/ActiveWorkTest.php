<?php

namespace Tests\Feature\Admin;

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
                ->where('agents.2.status', 'on_hold'),
            );
    }
}
