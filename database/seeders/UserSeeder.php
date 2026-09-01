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
        $piskaPlant = Plant::where('code', 'PMCMPL-01')->first();
        $gurgainMines = Plant::where('code', 'PMCMPL-02')->first();
        $dtzPlant = Plant::where('code', 'PMCMPL-03')->first();

        $users = [
            [
                'email' => 'ho@gmail.com',
                'name' => 'HO Data Operator Demo',
                'role' => Role::SLUG_HO_DATA_OPERATOR,
                'plant_id' => null,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'purchaser@gmail.com',
                'name' => 'KAMLESH KUMAR',
                'role' => Role::SLUG_PURCHASER,
                'plant_id' => null,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'gurgai@gmail.com',
                'name' => 'PAWAN KUMAR',
                'role' => Role::SLUG_PLANT_IN_CHARGE,
                'plant_id' => $gurgainMines?->id,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'pending@gmail.com',
                'name' => 'Pending Approval User',
                'role' => Role::SLUG_PLANT_IN_CHARGE,
                'plant_id' => $piskaPlant?->id,
                'approval_status' => UserApprovalStatus::Pending,
            ],
            [
                'email' => 'dtzplant@gmail.com',
                'name' => 'RAKESH KUMAR',
                'role' => Role::SLUG_PLANT_IN_CHARGE,
                'plant_id' => $dtzPlant?->id,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'rejected@gmail.com',
                'name' => 'Rejected User Demo',
                'role' => Role::SLUG_PLANT_IN_CHARGE,
                'plant_id' => $piskaPlant?->id,
                'approval_status' => UserApprovalStatus::Rejected,
                'rejection_reason' => 'Incomplete documentation provided.',
            ],
            [
                'email' => 'piska@gmail.com',
                'name' => 'SUBODH KUMAR',
                'role' => Role::SLUG_PLANT_IN_CHARGE,
                'plant_id' => $piskaPlant?->id,
                'approval_status' => UserApprovalStatus::Approved,
            ],
            [
                'email' => 'admin@gmail.com',
                'name' => 'Super Admin',
                'role' => Role::SLUG_SUPER_ADMIN,
                'plant_id' => null,
                'approval_status' => UserApprovalStatus::Approved,
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

        User::whereNotIn('email', array_column($users, 'email'))->delete();
    }
}
