<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'email' => 'supplier@demo.test',
                'name' => 'Demo Supplier Co.',
                'contact_person' => 'John Supplier',
                'phone' => '9876543210',
                'gstin' => '27ABCDE1234F1Z5',
                'address' => 'Warehouse 3, Pune MIDC',
            ],
            [
                'email' => 'ultratech@demo.test',
                'name' => 'Ultratech Materials Ltd.',
                'contact_person' => 'Priya Sharma',
                'phone' => '9123456780',
                'gstin' => '27AAAAA0000A1Z5',
                'address' => 'Cement Depot, Mumbai',
            ],
            [
                'email' => 'tata@demo.test',
                'name' => 'Tata Steel Traders',
                'contact_person' => 'Ravi Mehta',
                'phone' => '9988776655',
                'gstin' => '29BBBBB0000B1Z5',
                'address' => 'Steel Yard, Bengaluru (interstate)',
            ],
            [
                'email' => 'inactive@demo.test',
                'name' => 'Inactive Supplier (Demo)',
                'contact_person' => 'Old Contact',
                'phone' => '9000000000',
                'gstin' => null,
                'address' => null,
                'is_active' => false,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(
                ['email' => $supplier['email']],
                array_merge($supplier, ['is_active' => $supplier['is_active'] ?? true])
            );
        }
    }
}
