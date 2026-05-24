<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_brands_routes(): void
    {
        $this->get('/admin/brands')->assertRedirect(route('login'));
    }

    public function test_admin_can_list_brands(): void
    {
        $user = User::factory()->create();
        Brand::factory()->count(2)->create();

        $this->actingAs($user)
            ->get('/admin/brands')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Brands')
                ->has('brands', 2),
            );
    }

    public function test_admin_can_create_a_brand_with_auto_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/admin/brands', ['name' => 'Leadventure'])
            ->assertRedirect('/admin/brands');

        $this->assertDatabaseHas('brands', [
            'name' => 'Leadventure',
            'slug' => 'leadventure',
        ]);
    }

    public function test_brand_name_must_be_unique(): void
    {
        $user = User::factory()->create();
        Brand::factory()->create(['name' => 'Acme']);

        $this->actingAs($user)
            ->post('/admin/brands', ['name' => 'Acme'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_delete_a_brand(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();

        $this->actingAs($user)
            ->delete("/admin/brands/{$brand->id}")
            ->assertRedirect('/admin/brands');

        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }
}
