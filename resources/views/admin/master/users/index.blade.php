@extends('layouts.admin')
@section('title', 'Users')
@section('content')
<x-admin-page-header title="Users" subtitle="Add and manage users — assign roles and plants">
    <a href="{{ route('admin.users.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add User
    </a>
</x-admin-page-header>

<div class="admin-filters mb-3">
    <!-- <div class="d-flex flex-wrap gap-2 mb-2">
        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-admin-primary">All users</a>
    </div> -->
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="filter_plant_id">Plant</label>
            <select name="plant_id" id="filter_plant_id" class="form-select form-select-sm">
                <option value="">All plants &amp; mines</option>
                @foreach($plants as $plant)
                    <option value="{{ $plant->id }}" @selected(request('plant_id') == $plant->id)>{{ $plant->name }} ({{ $plant->code }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter_role_id">Role</label>
            <select name="role_id" id="filter_role_id" class="form-select form-select-sm">
                <option value="">All roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected(request('role_id') == $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter_search">Search</label>
            <input type="search" name="search" id="filter_search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name or email...">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter_is_active">Status</label>
            <select name="is_active" id="filter_is_active" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="1" @selected(request('is_active') === '1')>Active</option>
                <option value="0" @selected(request('is_active') === '0')>Inactive</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-admin-outline btn-sm w-100">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
        </div>
        @if(request()->hasAny(['plant_id', 'role_id', 'search', 'is_active']))
            <div class="col-md-3">
                <a href="{{ route('admin.users.index') }}" class="btn btn-link btn-sm text-danger w-100">Clear filters</a>
            </div>
        @endif
    </form>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Plant</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                <tr>
                    <td class="fw-semibold">{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $u->role?->name ?? '—' }}</span>
                    </td>
                    <td>
                        @if($u->plant)
                            <span>{{ $u->plant->name }}</span>
                            <code class="small text-muted ms-1">{{ $u->plant->code }}</code>
                        @else
                            <span class="text-muted">Head Office</span>
                        @endif
                    </td>
                    <td>
                        @if($u->is_active)
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <x-admin-action-edit :href="route('admin.users.edit', $u)" />
                        @if($u->id !== auth()->id())
                            <x-admin-action-delete :action="route('admin.users.destroy', $u)" confirm="Delete this user?" />
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="admin-empty">
                            <i class="bi bi-people d-block"></i>
                            No users found
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
