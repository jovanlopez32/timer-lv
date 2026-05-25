<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Brand;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'jorge.linan@leadventure.com'],
            [
                'name' => 'Jorge Linan',
                'password' => Hash::make('$Milk#Chocolate32'),
                'email_verified_at' => now(),
            ],
        );

        /* $brandsWithTaskTypes = [
            'Leadventure' => ['Design', 'Development', 'QA', 'Meeting', 'Research'],
            'Acme' => ['Support', 'Sales call', 'Onboarding'],
        ];

        $brands = [];

        foreach ($brandsWithTaskTypes as $brandName => $taskTypes) {
            $brand = Brand::updateOrCreate(
                ['name' => $brandName],
                ['slug' => Str::slug($brandName)],
            );

            $brands[$brandName] = $brand;

            foreach ($taskTypes as $taskTypeName) {
                TaskType::updateOrCreate(
                    ['brand_id' => $brand->id, 'name' => $taskTypeName],
                    ['brand_id' => $brand->id, 'name' => $taskTypeName],
                );
            }
        }

        $agents = [
            ['name' => 'Luis Hurtado', 'slug' => 'luis-hurtado', 'brand' => 'Leadventure'],
        ];

        foreach ($agents as $agent) {
            Agent::updateOrCreate(
                ['slug' => $agent['slug']],
                [
                    'name' => $agent['name'],
                    'brand_id' => $brands[$agent['brand']]->id,
                ],
            );
        } */
    }
}
