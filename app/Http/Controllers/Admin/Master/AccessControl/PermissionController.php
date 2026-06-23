<?php

namespace App\Http\Controllers\Admin\Master\AccessControl;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $permissions = Permission::query()
            ->withCount('roles')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->string('module')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('slug', 'like', '%'.$request->search.'%');
            }))
            ->orderBy('module')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.master.permissions.index', [
            'permissions' => $permissions,
            'modules' => config('permissions.modules', []),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.permissions.create', [
            'modules' => config('permissions.modules', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->normalizeSlug($data['slug'] ?? null, $data['name']);
        $data['is_system'] = false;

        Permission::create($data);

        return redirect()->route('admin.permissions.index')->with('success', 'Permission created.');
    }

    public function edit(Permission $permission): View
    {
        return view('admin.master.permissions.edit', [
            'permission' => $permission,
            'modules' => config('permissions.modules', []),
        ]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $data = $this->validated($request, $permission);

        if (! $permission->is_system) {
            $data['slug'] = $this->normalizeSlug($data['slug'] ?? null, $data['name']);
        } else {
            unset($data['slug'], $data['module']);
        }

        $permission->update($data);

        return redirect()->route('admin.permissions.index')->with('success', 'Permission updated.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->is_system) {
            return back()->with('error', 'System permissions cannot be deleted.');
        }

        if ($permission->roles()->exists()) {
            return back()->with('error', 'Cannot delete a permission assigned to roles.');
        }

        $permission->delete();

        return redirect()->route('admin.permissions.index')->with('success', 'Permission deleted.');
    }

    protected function validated(Request $request, ?Permission $permission = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9_.]+$/',
                Rule::unique('permissions', 'slug')->ignore($permission?->id),
            ],
            'module' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    protected function normalizeSlug(?string $slug, string $name): string
    {
        if (filled($slug)) {
            return strtolower($slug);
        }

        return Str::slug($name, '.');
    }
}
