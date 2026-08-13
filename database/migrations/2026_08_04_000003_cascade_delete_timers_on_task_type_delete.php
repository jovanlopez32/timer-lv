<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timers', function (Blueprint $table) {
            $table->dropForeign(['task_type_id']);
            $table->foreign('task_type_id')->references('id')->on('task_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timers', function (Blueprint $table) {
            $table->dropForeign(['task_type_id']);
            $table->foreign('task_type_id')->references('id')->on('task_types')->restrictOnDelete();
        });
    }
};
