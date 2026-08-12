<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        Role::where('slug', Role::SLUG_PURCHASER)->update([
            'requires_plant' => false,
            'description' => 'Purchaser — all plants, suppliers, PO dispatch',
        ]);

        $roleIds = Role::pluck('id', 'slug');

        foreach (config('permissions.defaults', []) as $definition) {
            $roles = $definition['roles'] ?? [];
            unset($definition['roles']);

            $permission = Permission::updateOrCreate(
                ['slug' => $definition['slug']],
                array_merge($definition, ['is_system' => true])
            );

            $ids = collect($roles)
                ->map(fn (string $slug) => $roleIds[$slug] ?? null)
                ->filter()
                ->values()
                ->all();

            if ($ids !== []) {
                $permission->roles()->sync($ids);
            }
        }
    }
}
