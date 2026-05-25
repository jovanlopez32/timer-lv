<?php

namespace Tests\Feature\Admin;

use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_task_types_routes(): void
    {
        $this->get('/admin/task-types')->assertRedirect(route('login'));
    }

    public function test_admin_can_list_task_types(): void
    {
        $user = User::factory()->create();
        TaskType::factory()->count(3)->create();

        $this->actingAs($user)
            ->get('/admin/task-types')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/TaskTypes')
                ->has('taskTypes', 3),
            );
    }

    public function test_admin_can_create_a_task_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/admin/task-types', ['name' => 'Development'])
            ->assertRedirect('/admin/task-types');

        $this->assertDatabaseHas('task_types', ['name' => 'Development']);
    }

    public function test_task_type_name_must_be_unique(): void
    {
        $user = User::factory()->create();
        TaskType::factory()->create(['name' => 'QA']);

        $this->actingAs($user)
            ->post('/admin/task-types', ['name' => 'QA'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_delete_a_task_type(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create();

        $this->actingAs($user)
            ->delete("/admin/task-types/{$taskType->id}")
            ->assertRedirect('/admin/task-types');

        $this->assertDatabaseMissing('task_types', ['id' => $taskType->id]);
    }
}
