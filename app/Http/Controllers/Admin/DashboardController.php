<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Brand;
use App\Models\TaskType;
use App\Models\Timer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'range' => ['nullable', 'in:this_week,last_week,custom'],
            'from' => ['nullable', 'date', 'required_if:range,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:range,custom'],
        ]);

        $range = $data['range'] ?? 'this_week';
        [$from, $to] = $this->resolvePeriod($range, $data['from'] ?? null, $data['to'] ?? null);

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $completedInPeriod = fn (Builder $query) => $query
            ->where('completed', true)
            ->whereBetween('ended_at', [$from, $to]);

        $developers = Agent::query()
            ->withCount(['timers as tasks_count' => $completedInPeriod])
            ->withSum(['timers as hours_sum' => $completedInPeriod], 'decimal_hours')
            ->orderByDesc('tasks_count')
            ->get(['id', 'name'])
            ->filter(fn (Agent $agent) => $agent->tasks_count > 0)
            ->values()
            ->map(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'tasks_count' => $agent->tasks_count,
                'hours' => round((float) ($agent->hours_sum ?? 0), 2),
            ]);

        $totalTasks = (clone $completedInPeriod(Timer::query()))->count();
        $totalHours = round((float) (clone $completedInPeriod(Timer::query()))->sum('decimal_hours'), 2);

        $topTaskTypes = TaskType::query()
            ->withCount(['timers as tasks_count' => $completedInPeriod])
            ->withSum(['timers as hours_sum' => $completedInPeriod], 'decimal_hours')
            ->orderByDesc('tasks_count')
            ->with('brand:id,name')
            ->get(['id', 'name', 'brand_id'])
            ->filter(fn (TaskType $taskType) => $taskType->tasks_count > 0)
            ->take(5)
            ->values()
            ->map(fn (TaskType $taskType) => [
                'id' => $taskType->id,
                'name' => $taskType->name,
                'brand' => $taskType->brand?->name,
                'tasks_count' => $taskType->tasks_count,
                'hours' => round((float) ($taskType->hours_sum ?? 0), 2),
            ]);

        $topBrands = DB::table('timers')
            ->join('task_types', 'task_types.id', '=', 'timers.task_type_id')
            ->join('brands', 'brands.id', '=', 'task_types.brand_id')
            ->where('timers.completed', true)
            ->whereBetween('timers.ended_at', [$from, $to])
            ->select('brands.id', 'brands.name', 'brands.color')
            ->selectRaw('COUNT(*) as tasks_count')
            ->selectRaw('SUM(timers.decimal_hours) as hours_sum')
            ->groupBy('brands.id', 'brands.name', 'brands.color')
            ->orderByDesc('hours_sum')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'color' => $row->color,
                'tasks_count' => $row->tasks_count,
                'hours' => round((float) $row->hours_sum, 2),
            ]);

        $sitesThisMonth = DB::table('timers')
            ->join('task_types', 'task_types.id', '=', 'timers.task_type_id')
            ->join('brands', 'brands.id', '=', 'task_types.brand_id')
            ->where('timers.completed', true)
            ->whereBetween('timers.ended_at', [$monthStart, $monthEnd])
            ->distinct()
            ->count('brands.id');

        return Inertia::render('Dashboard', [
            'period' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'month' => [
                'name' => now()->format('F'),
                'year' => now()->year,
                'sitesCount' => $sitesThisMonth,
            ],
            'totals' => [
                'tasksCount' => $totalTasks,
                'hoursSum' => $totalHours,
                'avgHours' => $totalTasks > 0 ? round($totalHours / $totalTasks, 2) : null,
            ],
            'developers' => $developers,
            'topTaskTypes' => $topTaskTypes,
            'topBrands' => $topBrands,
            'live' => [
                'active' => Timer::query()->active()->count(),
                'parked' => Timer::query()->parked()->count(),
            ],
            'overview' => [
                'agentsCount' => Agent::count(),
                'brandsCount' => Brand::count(),
            ],
        ]);
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function resolvePeriod(string $range, ?string $from, ?string $to): array
    {
        if ($range === 'custom' && $from && $to) {
            return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
        }

        $weeksAgo = $range === 'last_week' ? 1 : 0;

        return [
            now()->subWeeks($weeksAgo)->startOfWeek(Carbon::MONDAY),
            now()->subWeeks($weeksAgo)->endOfWeek(Carbon::SUNDAY),
        ];
    }
}
