<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_site')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Head office — full system access', 'requires_site' => false, 'is_system' => true, 'sort_order' => 1],
            ['name' => 'HO Data Operator', 'slug' => 'ho_data_operator', 'description' => 'Head office operations — stock, PO, reports', 'requires_site' => false, 'is_system' => true, 'sort_order' => 2],
            ['name' => 'Site In-Charge', 'slug' => 'site_in_charge', 'description' => 'Site manager — requests and tracking', 'requires_site' => true, 'is_system' => true, 'sort_order' => 3],
            ['name' => 'Purchaser', 'slug' => 'purchaser', 'description' => 'Site purchaser — suppliers, PO dispatch', 'requires_site' => true, 'is_system' => true, 'sort_order' => 4],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insert(array_merge($role, [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $slugToId = DB::table('roles')->pluck('id', 'slug');

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('email')->constrained('roles');
            $table->string('approval_status')->default('approved')->after('role_id');
            $table->foreignId('created_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('created_by');
        });

        foreach (DB::table('users')->get() as $user) {
            $slug = $user->role ?? 'site_in_charge';
            DB::table('users')->where('id', $user->id)->update([
                'role_id' => $slugToId[$slug] ?? $slugToId['site_in_charge'],
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('site_in_charge')->after('email');
        });

        $idToSlug = DB::table('roles')->pluck('slug', 'id');

        foreach (DB::table('users')->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'role' => $idToSlug[$user->role_id] ?? 'site_in_charge',
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['approval_status', 'rejection_reason']);
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
