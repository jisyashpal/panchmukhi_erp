<?php

namespace Database\Seeders;

use App\Models\Plant;
use Illuminate\Database\Seeder;

class PlantSeeder extends Seeder
{
    public function run(): void
    {
        Plant::firstOrCreate(
            ['code' => 'PMCMPL-01'],
            [
                'name' => 'PISKA-PLANT',
                'type' => 'plant',
                'address' => 'Piska, Ranchi, Jharkhand',
                'is_active' => true,
            ]
        );

        Plant::firstOrCreate(
            ['code' => 'PMCMPL-02'],
            [
                'name' => 'GURGAIN-MINES',
                'type' => 'mine',
                'address' => 'Gurgain, Jharkhand',
                'is_active' => true,
            ]
        );

        Plant::firstOrCreate(
            ['code' => 'PMCMPL-03'],
            [
                'name' => 'DTZ-PLANT',
                'type' => 'plant',
                'address' => 'DTZ, Jharkhand',
                'is_active' => true,
            ]
        );

        Plant::whereNotIn('code', ['PMCMPL-01', 'PMCMPL-02', 'PMCMPL-03'])->delete();
    }
}
