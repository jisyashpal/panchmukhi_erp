<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\Site;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    public function run(): void
    {
        $machinesBySite = [
            'PLT-001' => [
                ['name' => 'Mixer Unit A', 'code' => 'MX-A'],
                ['name' => 'Conveyor Line 1', 'code' => 'CV-01'],
                ['name' => 'Packaging Unit', 'code' => 'PK-01'],
            ],
            'PLT-002' => [
                ['name' => 'Batch Plant 1', 'code' => 'BP-01'],
                ['name' => 'Crusher Unit', 'code' => 'CR-01'],
            ],
        ];

        foreach ($machinesBySite as $siteCode => $machines) {
            $site = Site::where('code', $siteCode)->firstOrFail();

            foreach ($machines as $machine) {
                Machine::firstOrCreate(
                    ['site_id' => $site->id, 'code' => $machine['code']],
                    array_merge($machine, ['is_active' => true])
                );
            }
        }
    }
}
