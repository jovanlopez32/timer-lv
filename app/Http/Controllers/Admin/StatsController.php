<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Brand;
use App\Models\Timer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StatsController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'agent_ids' => ['nullable', 'array'],
            'agent_ids.*' => ['integer', 'exists:agents,id'],
        ]);

        $agentIds = array_map('intval', $data['agent_ids'] ?? []);

        $timersQuery = Timer::query()
            ->where('completed', true)
            ->whereNotNull('decimal_hours');

        $timersQuery = $this->applyFilters($timersQuery, $data, $agentIds);

        $hoursByTaskType = $timersQuery
            ->get(['task_type_id', 'decimal_hours'])
            ->groupBy('task_type_id')
            ->map(fn (Collection $rows) => $rows
                ->pluck('decimal_hours')
                ->map(fn ($h) => (float) $h)
                ->values()
                ->all());

        $brands = Brand::query()
            ->with(['taskTypes' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'color' => $brand->color,
                'task_types' => $brand->taskTypes->map(function ($taskType) use ($hoursByTaskType) {
                    $hours = $hoursByTaskType->get($taskType->id, []);

                    return [
                        'id' => $taskType->id,
                        'name' => $taskType->name,
                        'count' => count($hours),
                        'average' => $this->average($hours),
                        'median' => $this->median($hours),
                        'min' => $hours === [] ? null : min($hours),
                        'max' => $hours === [] ? null : max($hours),
                    ];
                })->values()->all(),
            ]);

        return Inertia::render('admin/Stats', [
            'brands' => $brands,
            'agents' => Agent::query()
                ->with(['brands' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Agent $agent) => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'brand' => $agent->brands->pluck('name')->implode(', ') ?: null,
                ]),
            'filters' => [
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
                'agent_ids' => $agentIds,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $agentIds
     */
    private function applyFilters(Builder $query, array $data, array $agentIds): Builder
    {
        if (! empty($data['from'])) {
            $query->whereDate('ended_at', '>=', $data['from']);
        }

        if (! empty($data['to'])) {
            $query->whereDate('ended_at', '<=', $data['to']);
        }

        if ($agentIds !== []) {
            $query->whereIn('agent_id', $agentIds);
        }

        return $query;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function average(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = (int) floor(($count - 1) / 2);

        if ($count % 2 === 1) {
            return round($values[$middle], 2);
        }

        return round(($values[$middle] + $values[$middle + 1]) / 2, 2);
    }
}
