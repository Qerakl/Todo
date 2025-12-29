<?php

namespace Tests\Feature\Api;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/tasks', [
            'title' => 'T',
            'description' => 'D',
            'status' => TaskStatus::New->value,
        ])->assertStatus(401);
    }

    public function test_show_requires_auth(): void
    {
        $task = Task::factory()->create();
        $this->getJson("/api/tasks/{$task->id}")->assertStatus(401);
    }

    public function test_update_requires_auth(): void
    {
        $task = Task::factory()->create();
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'X'])->assertStatus(401);
    }

    public function test_destroy_requires_auth(): void
    {
        $task = Task::factory()->create();
        $this->deleteJson("/api/tasks/{$task->id}")->assertStatus(401);
    }

    public function test_index_returns_only_current_users_tasks_with_user_payload(): void
    {
        $user = User::factory()->create(['name' => 'U1', 'email' => 'u1@test.com']);
        $other = User::factory()->create();

        Task::factory()->count(3)->create(['user_id' => $user->id, 'status' => TaskStatus::New->value]);
        Task::factory()->count(2)->create(['user_id' => $other->id, 'status' => TaskStatus::New->value]);

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/tasks');

        $res->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'description', 'status', 'user', 'created_at', 'updated_at'
                    ]
                ],
            ]);

        $data = $res->json('data');
        $this->assertCount(3, $data);

        foreach ($data as $item) {
            $this->assertSame($user->id, $item['user']['id']);
            $this->assertSame('U1', $item['user']['name']);
            $this->assertSame('u1@test.com', $item['user']['email']);
        }
    }

    public function test_index_respects_per_page_query_param_bounds(): void
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

    public function test_store_creates_task_for_current_user_and_returns_payload(): void
    {
        $user = User::factory()->create(['name' => 'German', 'email' => 'german@test.com']);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/tasks', [
            'title' => 'Title',
            'description' => 'Desc',
            'status' => TaskStatus::InProgress->value,
        ]);

        $res->assertCreated()
            ->assertJsonStructure(['id', 'title', 'description', 'status', 'user', 'created_at', 'updated_at'])
            ->assertJson([
                'title' => 'Title',
                'description' => 'Desc',
                'status' => TaskStatus::InProgress->value,
                'user' => [
                    'id' => $user->id,
                    'name' => 'German',
                    'email' => 'german@test.com',
                ],
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Title',
            'user_id' => $user->id,
            'status' => TaskStatus::InProgress->value,
        ]);
    }

    public function test_store_sets_default_status_when_not_provided(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/tasks', [
            'title' => 'Title',
            'description' => 'Desc',
        ]);

        $res->assertCreated()->assertJsonPath('status', TaskStatus::New->value);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Title',
            'user_id' => $user->id,
            'status' => TaskStatus::New->value,
        ]);
    }

    public function test_show_returns_task_payload_for_owner_and_forbids_non_owner(): void
    {
        $owner = User::factory()->create(['name' => 'Owner', 'email' => 'owner@test.com']);
        $intruder = User::factory()->create();

        $task = Task::factory()->create([
            'user_id' => $owner->id,
            'title' => 'X',
            'status' => TaskStatus::New->value,
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('id', $task->id)
            ->assertJsonPath('user.id', $owner->id);

        Sanctum::actingAs($intruder);
        $this->getJson("/api/tasks/{$task->id}")->assertStatus(403);
    }

    public function test_update_updates_only_owner_task_and_ignores_user_id_change(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $task = Task::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Old',
            'status' => TaskStatus::New->value,
        ]);

        Sanctum::actingAs($owner);

        $res = $this->putJson("/api/tasks/{$task->id}", [
            'title' => 'New',
            'user_id' => $other->id,
            'status' => TaskStatus::Done->value,
        ]);

        $res->assertOk()
            ->assertJsonPath('title', 'New')
            ->assertJsonPath('status', TaskStatus::Done->value)
            ->assertJsonPath('user.id', $owner->id);

        $task->refresh();
        $this->assertSame($owner->id, $task->user_id);
        $this->assertSame('New', $task->title);
    }

    public function test_update_forbids_non_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $task = Task::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Old',
            'status' => TaskStatus::New->value,
        ]);

        Sanctum::actingAs($intruder);

        $this->putJson("/api/tasks/{$task->id}", [
            'title' => 'Hack',
        ])->assertStatus(403);
    }

    public function test_destroy_soft_deletes_for_owner_and_forbids_non_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $task = Task::factory()->create([
            'user_id' => $owner->id,
            'status' => TaskStatus::New->value,
        ]);

        Sanctum::actingAs($intruder);
        $this->deleteJson("/api/tasks/{$task->id}")->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJson(['message' => 'Deleted']);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
