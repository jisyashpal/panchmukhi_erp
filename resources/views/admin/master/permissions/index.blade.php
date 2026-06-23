@extends('layouts.admin')
@section('title', 'Permissions')
@section('content')
<x-admin-page-header title="Permissions" subtitle="Define capabilities that can be assigned to roles">
    <div class="d-flex gap-2">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-admin-outline btn-sm">
            <i class="bi bi-shield-lock me-1"></i>Roles
        </a>
        <a href="{{ route('admin.permissions.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Permission
        </a>
    </div>
</x-admin-page-header>

<div class="admin-card mb-3">
    <div class="admin-card-body py-3">
        <form method="GET" action="{{ route('admin.permissions.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="filter_module">Module</label>
                <select name="module" id="filter_module" class="form-select form-select-sm">
                    <option value="">All modules</option>
                    @foreach($modules as $key => $label)
                        <option value="{{ $key }}" @selected(request('module') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="filter_search">Search</label>
                <input type="text" name="search" id="filter_search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Name or slug">
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-admin-primary btn-sm">Filter</button>
                @if(request()->hasAny(['module', 'search']))
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Module</th>
                    <th>Roles</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                <tr>
                    <td class="fw-semibold">
                        {{ $permission->name }}
                        @if($permission->is_system)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">System</span>
                        @endif
                    </td>
                    <td><code class="small">{{ $permission->slug }}</code></td>
                    <td>{{ $permission->moduleLabel() }}</td>
                    <td>{{ $permission->roles_count }}</td>
                    <td class="text-end">
                        <x-admin-action-edit :href="route('admin.permissions.edit', $permission)" />
                        @if(!$permission->is_system && $permission->roles_count === 0)
                            <x-admin-action-delete :action="route('admin.permissions.destroy', $permission)" confirm="Delete this permission?" />
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="admin-empty"><i class="bi bi-key d-block"></i>No permissions found</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $permissions->links() }}</div>
@endsection
