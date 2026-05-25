<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TimerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'agent_id',
    'task_type_id',
    'started_at',
    'ended_at',
    'decimal_hours',
    'completed',
])]
class Timer extends Model
{
    /** @use HasFactory<TimerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'completed' => 'boolean',
            'decimal_hours' => 'decimal:2',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TaskType::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TimerSession::class)->orderBy('started_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('completed', false);
    }

    public function currentSession(): ?TimerSession
    {
        return $this->sessions()->whereNull('ended_at')->first();
    }

    public function elapsedSeconds(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $total = 0;

        foreach ($this->sessions as $session) {
            $end = $session->ended_at ?? $now;
            $total += $session->started_at->diffInSeconds($end);
        }

        return (int) $total;
    }
}
