<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $suppliers = [
            [
                'name'           => 'ASHA ENTER.',
                'contact_person' => null,
                'phone'          => null,
                'email'          => null,
                'gstin'          => null,
            ],
            [
                'name'           => 'CPC EQUIPMENT PVT. LTD.',
                'contact_person' => 'CHOURASIYA JI',
                'phone'          => '9431129415',
                'email'          => null,
                'gstin'          => null,
            ],
            [
                'name'           => 'Demo Supplier Co.',
                'contact_person' => 'John Supplier',
                'phone'          => '9876543210',
                'email'          => 'supplier@demo.test',
                'gstin'          => '27ABCDE1234F1Z5',
            ],
            [
                'name'           => 'GLOBE ENTERPRISES',
                'contact_person' => 'GYAN PRAKASH BAGLA',
                'phone'          => '9334428700',
                'email'          => null,
                'gstin'          => null,
            ],
            [
                'name'           => 'Inactive Supplier (Demo)',
                'contact_person' => 'Old Contact',
                'phone'          => '9000000000',
                'email'          => 'inactive@demo.test',
                'gstin'          => null,
            ],
            [
                'name'           => 'POWER SYSTEM ENGINEERS',
                'contact_person' => 'AMIT JI',
                'phone'          => '9110004161',
                'email'          => null,
                'gstin'          => null,
            ],
            [
                'name'           => 'PSS INDUSTRIES',
                'contact_person' => 'PRASHANT PANDEY',
                'phone'          => '8210636067',
                'email'          => null,
                'gstin'          => null,
            ],
            [
                'name'           => 'S.R. SALES',
                'contact_person' => 'MAHESHWARI JI',
                'phone'          => '9835340096/7903595029',
                'email'          => null,
                'gstin'          => null,
            ],
            [
                'name'           => 'SALES AND SELLER',
                'contact_person' => 'SUNIL PODDAR',
                'phone'          => '9431170355',
                'email'          => null,
                'gstin'          => null,
            ],
            [
                'name'           => 'Tata Steel Traders',
                'contact_person' => 'Ravi Mehta',
                'phone'          => '9988776655',
                'email'          => 'tata@demo.test',
                'gstin'          => '29BBBBB0000B1Z5',
            ],
            [
                'name'           => 'Ultratech Materials Ltd.',
                'contact_person' => 'Priya Sharma',
                'phone'          => '9123456780',
                'email'          => 'ultratech@demo.test',
                'gstin'          => '27AAAAA0000A1Z5',
            ],
            [
                'name'           => 'VSP ISPAT PVT.LTD.',
                'contact_person' => 'JENA',
                'phone'          => '8011446188',
                'email'          => null,
                'gstin'          => null,
            ],
            [
                'name'           => 'YUVI DRILLING TOOL',
                'contact_person' => 'PINKU SINHA',
                'phone'          => '9693914519',
                'email'          => null,
                'gstin'          => null,
            ],
        ];

        foreach ($suppliers as $supplierData) {
            DB::table('suppliers')->updateOrInsert(
                ['name' => $supplierData['name']],
                array_merge($supplierData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
