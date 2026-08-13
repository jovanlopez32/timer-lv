<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaskType;
use App\Models\Timer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'task_type_id' => ['nullable', 'integer', 'exists:task_types,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'string', 'in:recent,hours_asc,hours_desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $timersQuery = Timer::query()
            ->where('completed', true)
            ->with(['agent:id,name,slug', 'taskType:id,name,brand_id', 'taskType.brand:id,name']);

        $timersQuery = $this->applyFilters($timersQuery, $data);
        $timersQuery = $this->applySort($timersQuery, $data['sort'] ?? null);
        $totalHours = (clone $timersQuery)->sum('decimal_hours');
        $timers = $timersQuery
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/Reports', [
            'taskTypes' => TaskType::query()
                ->with('brand:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'brand_id'])
                ->map(fn (TaskType $taskType) => [
                    'id' => $taskType->id,
                    'name' => $taskType->name,
                    'brand' => $taskType->brand?->name,
                ])
                ->sortBy([
                    fn (array $a, array $b) => ($a['brand'] ?? '') <=> ($b['brand'] ?? ''),
                    fn (array $a, array $b) => $a['name'] <=> $b['name'],
                ])
                ->values(),
            'filters' => [
                'task_type_id' => $data['task_type_id'] ?? null,
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
                'sort' => $data['sort'] ?? 'recent',
            ],
            'timers' => [
                'data' => $timers->getCollection()->map(fn (Timer $timer) => [
                    'id' => $timer->id,
                    'agent' => $timer->agent->name,
                    'brand' => $timer->taskType->brand?->name,
                    'task_type_id' => $timer->task_type_id,
                    'task_type' => $timer->taskType->name,
                    'decimal_hours' => (float) $timer->decimal_hours,
                    'started_at' => $timer->started_at?->toIso8601String(),
                    'ended_at' => $timer->ended_at?->toIso8601String(),
                ]),
                'current_page' => $timers->currentPage(),
                'last_page' => $timers->lastPage(),
                'per_page' => $timers->perPage(),
                'total' => $timers->total(),
                'from' => $timers->firstItem(),
                'to' => $timers->lastItem(),
            ],
            'totalHours' => round((float) $totalHours, 2),
        ]);
    }

    public function update(Request $request, Timer $timer): RedirectResponse
    {
        abort_unless($timer->completed, 404);

        $data = $request->validate([
            'task_type_id' => ['required', 'integer', 'exists:task_types,id'],
            'decimal_hours' => ['required', 'numeric', 'min:0'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
        ]);

        $timer->update([
            'task_type_id' => $data['task_type_id'],
            'decimal_hours' => round((float) $data['decimal_hours'], 2),
            'started_at' => $data['started_at'],
            'ended_at' => $data['ended_at'],
        ]);

        return back();
    }

    public function destroy(Timer $timer): RedirectResponse
    {
        abort_unless($timer->completed, 404);

        $timer->delete();

        return back();
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'task_type_id' => ['nullable', 'integer', 'exists:task_types,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', 'string', 'in:recent,hours_asc,hours_desc'],
        ]);

        $query = Timer::query()
            ->where('completed', true)
            ->with(['agent:id,name', 'taskType:id,name,brand_id', 'taskType.brand:id,name']);

        $query = $this->applyFilters($query, $data);
        $query = $this->applySort($query, $data['sort'] ?? null);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Agent', 'Brand', 'Task type', 'Hours', 'Started at', 'Ended at']);

            foreach ($query->lazy(500) as $timer) {
                fputcsv($output, [
                    $timer->agent->name,
                    $timer->taskType->brand?->name,
                    $timer->taskType->name,
                    number_format((float) $timer->decimal_hours, 2, '.', ''),
                    $timer->started_at?->toDateTimeString(),
                    $timer->ended_at?->toDateTimeString(),
                ]);
            }

            fclose($output);
        }, 'reports-export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function applyFilters($query, array $data)
    {
        if (! empty($data['task_type_id'])) {
            $query->where('task_type_id', $data['task_type_id']);
        }

        if (! empty($data['from'])) {
            $query->whereDate('ended_at', '>=', $data['from']);
        }

        if (! empty($data['to'])) {
            $query->whereDate('ended_at', '<=', $data['to']);
        }

        return $query;
    }

    private function applySort($query, ?string $sort)
    {
        return match ($sort) {
            'hours_asc' => $query->orderBy('decimal_hours')->orderByDesc('ended_at'),
            'hours_desc' => $query->orderByDesc('decimal_hours')->orderByDesc('ended_at'),
            default => $query->latest('ended_at'),
        };
    }
}
