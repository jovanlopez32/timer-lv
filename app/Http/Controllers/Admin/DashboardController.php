<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\TaskType;
use App\Models\Timer;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'agents' => Agent::count(),
                'taskTypes' => TaskType::count(),
                'timers' => Timer::count(),
            ],
            'recentTimers' => Timer::query()
                ->where('completed', true)
                ->with(['agent:id,name,slug', 'taskType:id,name'])
                ->latest('ended_at')
                ->limit(10)
                ->get()
                ->map(fn (Timer $timer) => [
                    'id' => $timer->id,
                    'agent' => $timer->agent->name,
                    'task_type' => $timer->taskType->name,
                    'decimal_hours' => (float) $timer->decimal_hours,
                    'ended_at' => $timer->ended_at?->toIso8601String(),
                ]),
        ]);
    }
}
