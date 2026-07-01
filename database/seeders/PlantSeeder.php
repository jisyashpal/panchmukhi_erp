<?php

namespace Database\Seeders;

use App\Models\Plant;
use Illuminate\Database\Seeder;

class PlantSeeder extends Seeder
{
    public function run(): void
    {
        Plant::firstOrCreate(
            ['code' => 'PLT-001'],
            [
                'name' => 'Main Plant',
                'type' => 'plant',
                'address' => 'Plot 12, Industrial Area, Pune, Maharashtra',
                'is_active' => true,
            ]
        );

        Plant::firstOrCreate(
            ['code' => 'PLT-002'],
            [
                'name' => 'Branch Plant North',
                'type' => 'plant',
                'address' => 'Sector 5, MIDC, Nashik, Maharashtra',
                'is_active' => true,
            ]
        );
    }
}
