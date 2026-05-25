<?php

use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\TaskTypeController;
use App\Http\Controllers\PublicTimerController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
      return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('admin')->group(function () {
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('stats', [StatsController::class, 'index'])->name('stats.index');

        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::patch('brands/{brand:id}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('brands/{brand:id}', [BrandController::class, 'destroy'])->name('brands.destroy');

        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
        Route::patch('agents/{agent:id}', [AgentController::class, 'update'])->name('agents.update');
        Route::delete('agents/{agent:id}', [AgentController::class, 'destroy'])->name('agents.destroy');

        Route::get('task-types', [TaskTypeController::class, 'index'])->name('task-types.index');
        Route::post('task-types', [TaskTypeController::class, 'store'])->name('task-types.store');
        Route::patch('task-types/{taskType:id}', [TaskTypeController::class, 'update'])->name('task-types.update');
        Route::delete('task-types/{taskType:id}', [TaskTypeController::class, 'destroy'])->name('task-types.destroy');
    });
});

require __DIR__.'/settings.php';

Route::post('timers/{timer}/pause', [PublicTimerController::class, 'pause'])->name('agent.timer.pause');
Route::post('timers/{timer}/resume', [PublicTimerController::class, 'resume'])->name('agent.timer.resume');
Route::post('timers/{timer}/complete', [PublicTimerController::class, 'complete'])->name('agent.timer.complete');
Route::post('{agent}/timers', [PublicTimerController::class, 'start'])->name('agent.timer.start');
Route::get('{agent}', [PublicTimerController::class, 'show'])->name('agent.timer');
