<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaskType;
use App\Models\Timer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActiveWorkController extends Controller
{
    public function index(): Response
    {
        $agents = Timer::query()
            ->where('completed', false)
            ->with(['agent:id,name,slug', 'taskType:id,name', 'sessions:id,timer_id,started_at,ended_at'])
            ->latest('started_at')
            ->get()
            ->map(fn (Timer $timer) => [
                'id' => $timer->id,
                'agent' => $timer->agent->name,
                'agent_slug' => $timer->agent->slug,
                'task_type_id' => $timer->task_type_id,
                'task_type' => $timer->taskType->name,
                'status' => match (true) {
                    $timer->isParked() => 'parked',
                    $timer->sessions->contains(fn ($session) => $session->ended_at === null) => 'in_progress',
                    default => 'on_hold',
                },
                'started_at' => $timer->started_at?->toIso8601String(),
                'elapsed_seconds' => $timer->elapsedSeconds(),
            ])
            ->values();

        return Inertia::render('admin/ActiveWork', [
            'agents' => $agents,
            'taskTypes' => TaskType::query()
                ->with('brand:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'brand_id'])
                ->map(fn (TaskType $taskType) => [
                    'id' => $taskType->id,
                    'name' => $taskType->name,
                    'brand' => $taskType->brand?->name,
                ]),
        ]);
    }

    public function update(Request $request, Timer $timer): RedirectResponse
    {
        abort_if($timer->completed, 404);

        $data = $request->validate([
            'task_type_id' => ['required', 'integer', 'exists:task_types,id'],
            'elapsed_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $timer->update(['task_type_id' => $data['task_type_id']]);

        $this->applyElapsedSeconds($timer, $data['elapsed_seconds']);

        return back();
    }

    /**
     * Rewrites the timer's sessions so its elapsed time matches the given total.
     * Only the latest session is stretched or shrunk; earlier sessions are kept
     * untouched unless the new total is shorter than they already account for.
     */
    private function applyElapsedSeconds(Timer $timer, int $seconds): void
    {
        $sessions = $timer->sessions()->get();
        $last = $sessions->last();

        if ($last === null) {
            $timer->sessions()->create([
                'started_at' => now()->subSeconds($seconds),
                'ended_at' => now(),
            ]);

            return;
        }

        $earlierTotal = $sessions
            ->filter(fn ($session) => $session->id !== $last->id)
            ->sum(fn ($session) => $session->started_at->diffInSeconds($session->ended_at ?? now()));

        $remaining = $seconds - (int) $earlierTotal;

        if ($remaining < 0) {
            $timer->sessions()->whereKeyNot($last->id)->delete();
            $remaining = $seconds;
        }

        if ($last->ended_at === null) {
            $last->update(['started_at' => now()->subSeconds($remaining)]);

            return;
        }

        $last->update(['ended_at' => $last->started_at->copy()->addSeconds($remaining)]);
    }
}
