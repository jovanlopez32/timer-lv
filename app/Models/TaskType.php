<?php

namespace App\Models;

use Database\Factories\TaskTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'brand_id'])]
class TaskType extends Model
{
    /** @use HasFactory<TaskTypeFactory> */
    use HasFactory;

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function timers(): HasMany
    {
        return $this->hasMany(Timer::class);
    }
}
