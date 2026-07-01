<?php

namespace Database\Seeders;

use App\Enums\UserApprovalStatus;
use App\Models\Role;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $mainPlant = Plant::where('code', 'PLT-001')->firstOrFail();
        $branchPlant = Plant::where('code', 'PLT-002')->firstOrFail();

        $users = [
            [
                'email' => 'admin@gmail.com',
                'name' => 'Super Admin',
                'role' => Role::SLUG_SUPER_ADMIN,
                'plant_id' => null,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'ho@gmail.com',
                'name' => 'HO Data Operator Demo',
                'role' => Role::SLUG_HO_DATA_OPERATOR,
                'plant_id' => null,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'plant@gmail.com',
                'name' => 'Plant In-Charge Demo',
                'role' => Role::SLUG_PLANT_IN_CHARGE,
                'plant_id' => $mainPlant->id,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'purchaser@gmail.com',
                'name' => 'Purchaser Demo',
                'role' => Role::SLUG_PURCHASER,
                'plant_id' => $mainPlant->id,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'plant2@gmail.com',
                'name' => 'Branch Plant In-Charge',
                'role' => Role::SLUG_PLANT_IN_CHARGE,
                'plant_id' => $branchPlant->id,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'pending@gmail.com',
                'name' => 'Pending Approval User',
                'role' => Role::SLUG_PLANT_IN_CHARGE,
                'plant_id' => $mainPlant->id,
                'approval_status' => UserApprovalStatus::Pending,
            ],
            [
                'email' => 'rejected@gmail.com',
                'name' => 'Rejected User Demo',
                'role' => Role::SLUG_PLANT_IN_CHARGE,
                'plant_id' => $mainPlant->id,
                'approval_status' => UserApprovalStatus::Rejected,
                'rejection_reason' => 'Incomplete documentation provided.',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('12345678'),
                    'role_id' => Role::where('slug', $user['role'])->value('id'),
                    'plant_id' => $user['plant_id'],
                    'is_active' => true,
                    'approval_status' => $user['approval_status'],
                    'email_verified_at' => now(),
                    'rejection_reason' => $user['rejection_reason'] ?? null,
                ]
            );
        }
    }
}
