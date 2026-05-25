<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'jorge.linan@leadventure.com'],
            [
                'name' => 'Jorge Linan',
                'password' => Hash::make('Password'),
                'email_verified_at' => now(),
            ],
        );

        $agents = [
            ['name' => 'Luis Hurtado', 'slug' => 'luis-hurtado', 'brand' => 'Leadventure'],
        ];

        foreach ($agents as $agent) {
            Agent::updateOrCreate(['slug' => $agent['slug']], $agent);
        }

        $taskTypes = ['Design', 'Development', 'QA', 'Meeting', 'Research'];

        foreach ($taskTypes as $name) {
            TaskType::updateOrCreate(['name' => $name], ['name' => $name]);
        }
    }
}
