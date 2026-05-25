<?php

namespace Tests\Feature\Admin;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_admin_routes(): void
    {
        $this->get('/admin/agents')->assertRedirect(route('login'));
        $this->post('/admin/agents', [])->assertRedirect(route('login'));
    }

    public function test_admin_can_list_agents(): void
    {
        $user = User::factory()->create();
        Agent::factory()->count(2)->create();

        $this->actingAs($user)
            ->get('/admin/agents')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Agents')
                ->has('agents', 2),
            );
    }

    public function test_admin_can_create_an_agent_with_auto_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/admin/agents', [
                'name' => 'Luis Hurtado',
                'brand' => 'Leadventure',
            ])
            ->assertRedirect('/admin/agents');

        $this->assertDatabaseHas('agents', [
            'name' => 'Luis Hurtado',
            'slug' => 'luis-hurtado',
            'brand' => 'Leadventure',
        ]);
    }

    public function test_slug_is_unique_when_name_collides(): void
    {
        $user = User::factory()->create();
        Agent::factory()->create(['slug' => 'luis-hurtado']);

        $this->actingAs($user)->post('/admin/agents', [
            'name' => 'Luis Hurtado',
            'brand' => 'Other Brand',
        ]);

        $this->assertDatabaseHas('agents', ['slug' => 'luis-hurtado-2']);
    }

    public function test_admin_can_delete_an_agent(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create();

        $this->actingAs($user)
            ->delete("/admin/agents/{$agent->id}")
            ->assertRedirect('/admin/agents');

        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
    }
}
