<?php

namespace App\Http\Controllers\Admin\Master\AccessControl;

use App\Http\Controllers\Admin\Master\AccessControl\Concerns\BuildsPermissionCatalog;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    use BuildsPermissionCatalog;

    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.master.roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('admin.master.roles.create', [
            'permissionsByModule' => $this->permissionsByModule(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $permissionIds = $data['permission_ids'] ?? [];
        unset($data['permission_ids']);

        $data['slug'] = Str::slug($data['slug'] ?? $data['name'], '_');
        $data['is_system'] = false;

        $role = Role::create($data);
        $role->syncPermissions($permissionIds);

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');

        return view('admin.master.roles.edit', [
            'role' => $role,
            'permissionsByModule' => $this->permissionsByModule(),
            'assignedPermissionIds' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);
        $permissionIds = $data['permission_ids'] ?? [];
        unset($data['permission_ids']);

        if (! $role->is_system) {
            $data['slug'] = Str::slug($data['slug'] ?? $data['name'], '_');
        } else {
            unset($data['slug']);
        }

        $role->update($data);
        $role->syncPermissions($permissionIds);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Cannot delete a role that is assigned to users.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }

    protected function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'slug')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'requires_site' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
    }
}
