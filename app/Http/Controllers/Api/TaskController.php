<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Task::class, 'task');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::query()
            ->where('user_id', $request->user()->id)
            ->with(['user:id,name,email'])
            ->latest()
            ->paginate(min(max((int)$request->query('per_page', 20), 1), 100));

        $tasks->getCollection()->transform(fn (Task $t) => $this->payload($t));

        return response()->json($tasks);
    }

    public function store(StoreTaskRequest $request)
    {
        $this->authorize('create', Task::class);

        $data = $request->validated();

        $task = Task::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? TaskStatus::New->value,
        ])->load('user:id,name,email');

        return response()->json($this->payload($task), 201);
    }

    public function show(Task $task)
    {
        $task->load('user:id,name,email');
        return response()->json($this->payload($task));
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $data = $request->validated();

        unset($data['user_id']);

        $task->fill($data)->save();
        $task->load('user:id,name,email');

        return response()->json($this->payload($task));
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function payload(Task $task): array
    {
        $status = $task->status instanceof \BackedEnum ? $task->status->value : (string)$task->status;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $status,
            'user' => $task->relationLoaded('user') && $task->user
                ? [
                    'id' => $task->user->id,
                    'name' => $task->user->name,
                    'email' => $task->user->email,
                ]
                : null,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
        ];
    }
}
