<?php

namespace App\Models;

use Database\Factories\TimerSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['timer_id', 'started_at', 'ended_at'])]
class TimerSession extends Model
{
    /** @use HasFactory<TimerSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function timer(): BelongsTo
    {
        return $this->belongsTo(Timer::class);
    }
}
