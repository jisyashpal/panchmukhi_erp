@extends('layouts.admin')
@section('title', 'Roles & Permissions')
@section('content')
<x-admin-page-header title="Roles" subtitle="Manage user roles and assign permissions">
    <div class="d-flex gap-2">
        <a href="{{ route('admin.permissions.index') }}" class="btn btn-admin-outline btn-sm">
            <i class="bi bi-key me-1"></i>Permissions
        </a>
        <a href="{{ route('admin.roles.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Role
        </a>
    </div>
</x-admin-page-header>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Plant required</th>
                    <th>Permissions</th>
                    <th>Users</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                <tr>
                    <td class="fw-semibold">
                        {{ $role->name }}
                        @if($role->is_system)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">System</span>
                        @endif
                    </td>
                    <td><code class="small">{{ $role->slug }}</code></td>
                    <td>
                        @if($role->requires_plant)
                            <span class="text-success small"><i class="bi bi-check-circle me-1"></i>Yes</span>
                        @else
                            <span class="text-muted small">No</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $role->permissions_count }}</span>
                    </td>
                    <td>{{ $role->users_count }}</td>
                    <td>
                        @if($role->is_active)
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <x-admin-action-edit :href="route('admin.roles.edit', $role)" />
                        @if(!$role->is_system && $role->users_count === 0)
                            <x-admin-action-delete :action="route('admin.roles.destroy', $role)" confirm="Delete this role?" />
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="admin-empty"><i class="bi bi-shield-lock d-block"></i>No roles found</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $roles->links() }}</div>
@endsection
