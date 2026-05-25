<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Timer;
use Inertia\Inertia;
use Inertia\Response;

class ActiveWorkController extends Controller
{
    public function index(): Response
    {
        $agents = Timer::query()
            ->where('completed', false)
            ->with(['agent:id,name,slug', 'taskType:id,name', 'sessions:id,timer_id,ended_at'])
            ->latest('started_at')
            ->get()
            ->map(fn (Timer $timer) => [
                'id' => $timer->id,
                'agent' => $timer->agent->name,
                'agent_slug' => $timer->agent->slug,
                'task_type' => $timer->taskType->name,
                'status' => $timer->sessions->contains(fn ($session) => $session->ended_at === null)
                    ? 'in_progress'
                    : 'on_hold',
                'started_at' => $timer->started_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('admin/ActiveWork', [
            'agents' => $agents,
        ]);
    }
}
