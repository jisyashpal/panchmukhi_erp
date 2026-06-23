<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SiteSeeder::class,
            MachineSeeder::class,
            SupplierSeeder::class,
            ProductCatalogSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
            WorkflowSeeder::class,
        ]);
    }
}
