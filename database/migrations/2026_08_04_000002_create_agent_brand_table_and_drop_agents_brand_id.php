<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_brand', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['agent_id', 'brand_id']);
        });

        // Carry every agent's single existing brand over to the pivot table
        // before the column that used to hold it is dropped.
        DB::table('agents')
            ->whereNotNull('brand_id')
            ->select('id', 'brand_id')
            ->orderBy('id')
            ->each(function (object $agent): void {
                DB::table('agent_brand')->insert([
                    'agent_id' => $agent->id,
                    'brand_id' => $agent->brand_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('name')->constrained()->cascadeOnDelete();
        });

        DB::table('agent_brand')
            ->orderBy('id')
            ->each(function (object $pivot): void {
                DB::table('agents')
                    ->where('id', $pivot->agent_id)
                    ->whereNull('brand_id')
                    ->update(['brand_id' => $pivot->brand_id]);
            });

        Schema::dropIfExists('agent_brand');
    }
};
