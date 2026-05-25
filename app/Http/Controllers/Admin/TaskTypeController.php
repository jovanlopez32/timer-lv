<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaskType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/TaskTypes', [
            'taskTypes' => TaskType::query()
                ->orderBy('name')
                ->get(['id', 'name', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:task_types,name'],
        ]);

        TaskType::create($data);

        return redirect()->route('task-types.index');
    }

    public function update(Request $request, TaskType $taskType): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:task_types,name,'.$taskType->id,
            ],
        ]);

        $taskType->update($data);

        return redirect()->route('task-types.index');
    }

    public function destroy(TaskType $taskType): RedirectResponse
    {
        $taskType->delete();

        return redirect()->route('task-types.index');
    }
}
