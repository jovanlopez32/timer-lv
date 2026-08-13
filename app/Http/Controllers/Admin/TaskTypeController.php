<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\TaskType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/TaskTypes', [
            'taskTypes' => TaskType::query()
                ->with('brand:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'brand_id', 'created_at'])
                ->map(fn (TaskType $taskType) => [
                    'id' => $taskType->id,
                    'name' => $taskType->name,
                    'brand_id' => $taskType->brand_id,
                    'brand' => $taskType->brand?->name,
                    'created_at' => $taskType->created_at?->toIso8601String(),
                ]),
            'brands' => Brand::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('task_types', 'name')
                    ->where(fn ($q) => $q->where('brand_id', $request->input('brand_id'))),
            ],
        ]);

        TaskType::create($data);

        return redirect()->route('task-types.index');
    }

    public function update(Request $request, TaskType $taskType): RedirectResponse
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('task_types', 'name')
                    ->ignore($taskType->id)
                    ->where(fn ($q) => $q->where('brand_id', $request->input('brand_id'))),
            ],
        ]);

        $taskType->update($data);

        return redirect()->route('task-types.index');
    }

    public function destroy(Request $request, TaskType $taskType): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $taskType->delete();

        return redirect()->route('task-types.index');
    }
}
