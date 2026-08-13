<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\TaskType;
use App\Models\Timer;
use App\Models\TimerSession;
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
                ->has('taskTypes', 3)
                ->has('brands'),
            );
    }

    public function test_admin_can_create_a_task_type(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();

        $this->actingAs($user)
            ->post('/admin/task-types', [
                'name' => 'Development',
                'brand_id' => $brand->id,
            ])
            ->assertRedirect('/admin/task-types');

        $this->assertDatabaseHas('task_types', [
            'name' => 'Development',
            'brand_id' => $brand->id,
        ]);
    }

    public function test_task_type_name_is_unique_per_brand(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        TaskType::factory()->create(['name' => 'QA', 'brand_id' => $brand->id]);

        $this->actingAs($user)
            ->post('/admin/task-types', [
                'name' => 'QA',
                'brand_id' => $brand->id,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_task_type_name_can_repeat_across_brands(): void
    {
        $user = User::factory()->create();
        $brandA = Brand::factory()->create();
        $brandB = Brand::factory()->create();
        TaskType::factory()->create(['name' => 'QA', 'brand_id' => $brandA->id]);

        $this->actingAs($user)
            ->post('/admin/task-types', [
                'name' => 'QA',
                'brand_id' => $brandB->id,
            ])
            ->assertRedirect('/admin/task-types');

        $this->assertDatabaseHas('task_types', [
            'name' => 'QA',
            'brand_id' => $brandB->id,
        ]);
    }

    public function test_deleting_a_task_type_requires_the_admins_password(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create();

        $this->actingAs($user)
            ->delete("/admin/task-types/{$taskType->id}")
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('task_types', ['id' => $taskType->id]);
    }

    public function test_deleting_a_task_type_rejects_the_wrong_password(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create();

        $this->actingAs($user)
            ->delete("/admin/task-types/{$taskType->id}", ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('task_types', ['id' => $taskType->id]);
    }

    public function test_admin_can_delete_a_task_type_with_the_correct_password(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create();

        $this->actingAs($user)
            ->delete("/admin/task-types/{$taskType->id}", ['password' => 'password'])
            ->assertRedirect('/admin/task-types');

        $this->assertDatabaseMissing('task_types', ['id' => $taskType->id]);
    }

    public function test_deleting_a_task_type_cascades_its_timers_and_sessions(): void
    {
        $user = User::factory()->create();
        $taskType = TaskType::factory()->create();
        $timer = Timer::factory()->for($taskType, 'taskType')->completed(2.0)->create();
        TimerSession::factory()->for($timer)->create();

        $this->actingAs($user)
            ->delete("/admin/task-types/{$taskType->id}", ['password' => 'password'])
            ->assertRedirect('/admin/task-types');

        $this->assertDatabaseMissing('timers', ['id' => $timer->id]);
        $this->assertDatabaseMissing('timer_sessions', ['timer_id' => $timer->id]);
    }
}
