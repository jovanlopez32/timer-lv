<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_users_routes(): void
    {
        $this->get('/admin/users')->assertRedirect(route('login'));
        $this->post('/admin/users', [])->assertRedirect(route('login'));
    }

    public function test_admin_can_list_users(): void
    {
        $user = User::factory()->create();
        User::factory()->count(2)->create();

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Users')
                ->has('users', 3),
            );
    }

    public function test_admin_can_create_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/admin/users', [
                'name' => 'Ana Torres',
                'email' => 'ana@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'name' => 'Ana Torres',
            'email' => 'ana@example.com',
        ]);
    }

    public function test_user_email_must_be_unique(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->post('/admin/users', [
                'name' => 'Someone',
                'email' => 'taken@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_must_be_confirmed_to_create_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/admin/users', [
                'name' => 'Someone',
                'email' => 'someone@example.com',
                'password' => 'password123',
                'password_confirmation' => 'not-matching',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_admin_can_update_a_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->patch("/admin/users/{$other->id}", [
                'name' => 'New Name',
                'email' => $other->email,
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'id' => $other->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_reset_another_users_password(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $originalHash = $other->password;

        $this->actingAs($user)
            ->put("/admin/users/{$other->id}/password", [
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertRedirect('/admin/users');

        $this->assertNotEquals($originalHash, $other->fresh()->password);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->delete("/admin/users/{$other->id}")
            ->assertRedirect('/admin/users');

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete("/admin/users/{$user->id}")
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
