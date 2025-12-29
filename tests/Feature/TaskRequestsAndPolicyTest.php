<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskRequestsAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_task_request_validation_errors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/tasks', [
            'title' => '',
            'description' => 123,
            'status' => 'bad_status',
        ]);

        $res->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['title', 'description', 'status']);
    }

    public function test_store_task_request_accepts_valid_payload_and_sets_default_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/tasks', [
            'title' => 'Test task',
            'description' => 'Test desc',
        ]);

        $res->assertCreated()
            ->assertJsonStructure([
                'id', 'title', 'description', 'status', 'user', 'created_at', 'updated_at'
            ])
            ->assertJson([
                'title' => 'Test task',
                'description' => 'Test desc',
                'status' => TaskStatus::New->value,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Test task',
            'user_id' => $user->id,
            'status' => TaskStatus::New->value,
        ]);
    }

    public function test_update_task_request_validation_errors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $task = Task::factory()->create([
            'user_id' => $user->id,
            'status' => TaskStatus::New->value,
        ]);

        $res = $this->putJson("/api/tasks/{$task->id}", [
            'title' => '',
            'status' => 'wrong',
        ]);

        $res->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['title', 'status']);
    }

    public function test_update_task_request_allows_partial_updates_and_ignores_user_id_change(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($user);

        $task = Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Old',
            'description' => 'Old desc',
            'status' => TaskStatus::New->value,
        ]);

        $res = $this->putJson("/api/tasks/{$task->id}", [
            'title' => 'New Title',
            'user_id' => $other->id,
            'status' => TaskStatus::InProgress->value,
        ]);

        $res->assertOk()
            ->assertJsonPath('id', $task->id)
            ->assertJsonPath('title', 'New Title')
            ->assertJsonPath('status', TaskStatus::InProgress->value)
            ->assertJsonPath('user.id', $user->id);

        $task->refresh();
        $this->assertSame($user->id, $task->user_id);
    }

    public function test_policy_denies_access_to_other_users_task_show_update_delete(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $task = Task::factory()->create([
            'user_id' => $owner->id,
            'status' => TaskStatus::New->value,
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/tasks/{$task->id}")->assertStatus(403);

        $this->putJson("/api/tasks/{$task->id}", [
            'title' => 'Hack',
        ])->assertStatus(403);

        $this->deleteJson("/api/tasks/{$task->id}")->assertStatus(403);
    }

    public function test_index_returns_only_current_users_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->count(3)->create(['user_id' => $user->id, 'status' => TaskStatus::New->value]);
        Task::factory()->count(2)->create(['user_id' => $other->id, 'status' => TaskStatus::New->value]);

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/tasks');

        $res->assertOk();

        $data = $res->json('data');
        $this->assertIsArray($data);
        $this->assertCount(3, $data);

        foreach ($data as $item) {
            $this->assertSame($user->id, $item['user']['id']);
        }
    }

    public function test_index_pagination_per_page_bounds(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(60)->create(['user_id' => $user->id, 'status' => TaskStatus::New->value]);

        Sanctum::actingAs($user);

        $res1 = $this->getJson('/api/tasks?per_page=1')->assertOk();
        $this->assertCount(1, $res1->json('data'));

        $res2 = $this->getJson('/api/tasks?per_page=1000')->assertOk();
        $this->assertCount(60, $res2->json('data'));

        $res3 = $this->getJson('/api/tasks?per_page=0')->assertOk();
        $this->assertGreaterThanOrEqual(1, count($res3->json('data')));
        $this->assertLessThanOrEqual(100, count($res3->json('data')));
    }

    public function test_policy_allows_owner_to_show_update_delete(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'status' => TaskStatus::New->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('id', $task->id);

        $this->putJson("/api/tasks/{$task->id}", [
            'title' => 'Updated',
        ])->assertOk()
            ->assertJsonPath('title', 'Updated');

        $this->deleteJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJson(['message' => 'Deleted']);
    }
}
