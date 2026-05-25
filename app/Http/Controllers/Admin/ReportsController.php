<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaskType;
use App\Models\Timer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'task_type_id' => ['nullable', 'integer', 'exists:task_types,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $hasFilters = ! empty($data['task_type_id']) || ! empty($data['from']) || ! empty($data['to']);

        $timersQuery = Timer::query()
            ->where('completed', true)
            ->with(['agent:id,name,slug', 'taskType:id,name'])
            ->latest('ended_at');

        if (! empty($data['task_type_id'])) {
            $timersQuery->where('task_type_id', $data['task_type_id']);
        }

        if (! empty($data['from'])) {
            $timersQuery->whereDate('ended_at', '>=', $data['from']);
        }

        if (! empty($data['to'])) {
            $timersQuery->whereDate('ended_at', '<=', $data['to']);
        }

        $timers = $hasFilters
            ? $timersQuery->get()
            : $timersQuery->limit(50)->get();

        return Inertia::render('admin/Reports', [
            'taskTypes' => TaskType::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'task_type_id' => $data['task_type_id'] ?? null,
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
            ],
            'timers' => $timers->map(fn (Timer $timer) => [
                'id' => $timer->id,
                'agent' => $timer->agent->name,
                'task_type' => $timer->taskType->name,
                'decimal_hours' => (float) $timer->decimal_hours,
                'ended_at' => $timer->ended_at?->toIso8601String(),
            ]),
            'totalHours' => round((float) $timers->sum('decimal_hours'), 2),
        ]);
    }
}
