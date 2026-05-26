<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\TaskType;
use App\Models\Timer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicTimerController extends Controller
{
    public function show(Agent $agent): Response
    {
        $agent->load('brand:id,name');

        $activeTimer = Timer::query()
            ->where('agent_id', $agent->id)
            ->active()
            ->with(['taskType', 'sessions'])
            ->first();

        $parkedTimers = Timer::query()
            ->where('agent_id', $agent->id)
            ->parked()
            ->with(['taskType', 'sessions'])
            ->orderBy('parked_at')
            ->get()
            ->map(fn (Timer $timer) => [
                'id' => $timer->id,
                'task_type' => [
                    'id' => $timer->taskType->id,
                    'name' => $timer->taskType->name,
                ],
                'parked_at' => $timer->parked_at?->toIso8601String(),
                'elapsed_seconds' => $timer->elapsedSeconds(),
            ])
            ->all();

        $payload = null;

        if ($activeTimer) {
            $payload = [
                'id' => $activeTimer->id,
                'task_type_id' => $activeTimer->task_type_id,
                'task_type' => [
                    'id' => $activeTimer->taskType->id,
                    'name' => $activeTimer->taskType->name,
                ],
                'started_at' => $activeTimer->started_at->toIso8601String(),
                'elapsed_seconds' => $activeTimer->elapsedSeconds(),
                'is_running' => $activeTimer->currentSession() !== null,
            ];
        }

        return Inertia::render('PublicTimer', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'brand' => $agent->brand?->name,
            ],
            'taskTypes' => TaskType::query()
                ->where('brand_id', $agent->brand_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'activeTimer' => $payload,
            'parkedTimers' => $parkedTimers,
        ]);
    }

    public function start(Request $request, Agent $agent): RedirectResponse
    {
        $data = $request->validate([
            'task_type_id' => [
                'required',
                'integer',
                Rule::exists('task_types', 'id')
                    ->where(fn ($q) => $q->where('brand_id', $agent->brand_id)),
            ],
        ]);

        if (Timer::where('agent_id', $agent->id)->active()->exists()) {
            throw ValidationException::withMessages([
                'task_type_id' => 'This agent already has an active timer.',
            ]);
        }

        $timer = Timer::create([
            'agent_id' => $agent->id,
            'task_type_id' => $data['task_type_id'],
            'started_at' => now(),
            'completed' => false,
        ]);

        $timer->sessions()->create([
            'started_at' => now(),
        ]);

        return redirect()->route('agent.timer', $agent);
    }

    public function pause(Timer $timer): RedirectResponse
    {
        abort_if($timer->completed, 422, 'Timer is already completed.');

        $session = $timer->currentSession();

        if ($session) {
            $session->update(['ended_at' => now()]);
        }

        return redirect()->route('agent.timer', $timer->agent);
    }

    public function resume(Timer $timer): RedirectResponse
    {
        abort_if($timer->completed, 422, 'Timer is already completed.');

        if ($timer->currentSession()) {
            return redirect()->route('agent.timer', $timer->agent);
        }

        $timer->sessions()->create([
            'started_at' => now(),
        ]);

        return redirect()->route('agent.timer', $timer->agent);
    }

    public function park(Timer $timer): RedirectResponse
    {
        abort_if($timer->completed, 422, 'Timer is already completed.');
        abort_if($timer->isParked(), 422, 'Timer is already parked.');

        if ($timer->currentSession() !== null) {
            throw ValidationException::withMessages([
                'timer' => 'Pause the timer before parking it.',
            ]);
        }

        $timer->update(['parked_at' => now()]);

        return redirect()->route('agent.timer', $timer->agent);
    }

    public function unpark(Timer $timer): RedirectResponse
    {
        abort_if($timer->completed, 422, 'Timer is already completed.');
        abort_if(! $timer->isParked(), 422, 'Timer is not parked.');

        $hasActive = Timer::query()
            ->where('agent_id', $timer->agent_id)
            ->active()
            ->exists();

        if ($hasActive) {
            throw ValidationException::withMessages([
                'timer' => 'Park the current active task before resuming another.',
            ]);
        }

        $timer->update(['parked_at' => null]);

        return redirect()->route('agent.timer', $timer->agent);
    }

    public function complete(Timer $timer): RedirectResponse
    {
        abort_if($timer->completed, 422, 'Timer is already completed.');

        $session = $timer->currentSession();

        if ($session) {
            $session->update(['ended_at' => now()]);
        }

        $timer->load('sessions');

        $seconds = $timer->elapsedSeconds();
        $hours = round($seconds / 3600, 2);

        $timer->update([
            'ended_at' => now(),
            'decimal_hours' => $hours,
            'completed' => true,
        ]);

        return redirect()
            ->route('agent.timer', $timer->agent)
            ->with('completedHours', $hours)
            ->with('completedSeconds', $seconds)
            ->with('completedTimerId', $timer->id);
    }
}
