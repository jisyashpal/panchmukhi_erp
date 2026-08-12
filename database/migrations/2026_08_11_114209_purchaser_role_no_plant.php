<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('slug', Role::SLUG_PURCHASER)
            ->update([
                'requires_plant' => false,
                'description' => 'Purchaser — all plants, suppliers, PO dispatch',
                'updated_at' => now(),
            ]);

        $purchaserRoleId = DB::table('roles')->where('slug', Role::SLUG_PURCHASER)->value('id');

        if ($purchaserRoleId) {
            DB::table('users')
                ->where('role_id', $purchaserRoleId)
                ->update([
                    'plant_id' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('slug', Role::SLUG_PURCHASER)
            ->update([
                'requires_plant' => true,
                'description' => 'Plant purchaser — suppliers, PO dispatch',
                'updated_at' => now(),
            ]);
    }
};
