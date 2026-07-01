<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlantSeeder::class,
            SupplierSeeder::class,
            ProductCatalogSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
            WorkflowSeeder::class,
        ]);
    }
}
