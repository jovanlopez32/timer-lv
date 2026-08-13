<?php

namespace Tests\Feature\Admin;

use App\Models\Agent;
use App\Models\Brand;
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
                ->has('agents', 2)
                ->has('brands'),
            );
    }

    public function test_admin_can_create_an_agent_with_auto_slug(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();

        $this->actingAs($user)
            ->post('/admin/agents', [
                'name' => 'Luis Hurtado',
                'brand_ids' => [$brand->id],
            ])
            ->assertRedirect('/admin/agents');

        $this->assertDatabaseHas('agents', [
            'name' => 'Luis Hurtado',
            'slug' => 'luis-hurtado',
        ]);
        $agent = Agent::where('slug', 'luis-hurtado')->firstOrFail();
        $this->assertDatabaseHas('agent_brand', [
            'agent_id' => $agent->id,
            'brand_id' => $brand->id,
        ]);
    }

    public function test_admin_can_create_an_agent_with_multiple_brands(): void
    {
        $user = User::factory()->create();
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();

        $this->actingAs($user)
            ->post('/admin/agents', [
                'name' => 'Multi Brand Agent',
                'brand_ids' => [$brandA->id, $brandB->id],
            ])
            ->assertRedirect('/admin/agents');

        $agent = Agent::where('name', 'Multi Brand Agent')->firstOrFail();
        $this->assertCount(2, $agent->brands);
    }

    public function test_slug_is_unique_when_name_collides(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        Agent::factory()->create(['slug' => 'luis-hurtado']);

        $this->actingAs($user)->post('/admin/agents', [
            'name' => 'Luis Hurtado',
            'brand_ids' => [$brand->id],
        ]);

        $this->assertDatabaseHas('agents', ['slug' => 'luis-hurtado-2']);
    }

    public function test_brand_ids_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/admin/agents', ['name' => 'No Brand'])
            ->assertSessionHasErrors('brand_ids');
    }

    public function test_admin_can_update_an_agents_brands(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create();
        $newBrand = Brand::factory()->create();

        $this->actingAs($user)
            ->patch("/admin/agents/{$agent->id}", [
                'name' => $agent->name,
                'brand_ids' => [$newBrand->id],
            ])
            ->assertRedirect('/admin/agents');

        $this->assertSame([$newBrand->id], $agent->brands()->pluck('brands.id')->all());
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
