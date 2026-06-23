<?php

namespace Database\Seeders;

use App\Enums\UserApprovalStatus;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $mainSite = Site::where('code', 'PLT-001')->firstOrFail();
        $branchSite = Site::where('code', 'PLT-002')->firstOrFail();

        $users = [
            [
                'email' => 'admin@gmail.com',
                'name' => 'Super Admin',
                'role' => Role::SLUG_SUPER_ADMIN,
                'site_id' => null,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'ho@gmail.com',
                'name' => 'HO Data Operator Demo',
                'role' => Role::SLUG_HO_DATA_OPERATOR,
                'site_id' => null,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'site@gmail.com',
                'name' => 'Site In-Charge Demo',
                'role' => Role::SLUG_SITE_IN_CHARGE,
                'site_id' => $mainSite->id,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'purchaser@gmail.com',
                'name' => 'Purchaser Demo',
                'role' => Role::SLUG_PURCHASER,
                'site_id' => $mainSite->id,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'site2@gmail.com',
                'name' => 'Branch Site In-Charge',
                'role' => Role::SLUG_SITE_IN_CHARGE,
                'site_id' => $branchSite->id,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'pending@gmail.com',
                'name' => 'Pending Approval User',
                'role' => Role::SLUG_SITE_IN_CHARGE,
                'site_id' => $mainSite->id,
                'approval_status' => UserApprovalStatus::Pending,
            ],
            [
                'email' => 'rejected@gmail.com',
                'name' => 'Rejected User Demo',
                'role' => Role::SLUG_SITE_IN_CHARGE,
                'site_id' => $mainSite->id,
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
                    'site_id' => $user['site_id'],
                    'is_active' => true,
                    'approval_status' => $user['approval_status'],
                    'email_verified_at' => now(),
                    'rejection_reason' => $user['rejection_reason'] ?? null,
                ]
            );
        }
    }
}
