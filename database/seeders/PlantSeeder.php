<?php

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        Site::firstOrCreate(
            ['code' => 'PLT-001'],
            [
                'name' => 'Main Plant',
                'type' => 'plant',
                'address' => 'Plot 12, Industrial Area, Pune, Maharashtra',
                'is_active' => true,
            ]
        );

        Site::firstOrCreate(
            ['code' => 'PLT-002'],
            [
                'name' => 'Branch Site North',
                'type' => 'site',
                'address' => 'Sector 5, MIDC, Nashik, Maharashtra',
                'is_active' => true,
            ]
        );
    }
}
