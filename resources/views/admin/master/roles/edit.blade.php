@extends('layouts.admin')
@section('title', 'Edit Role')
@section('content')
<x-admin-page-header title="Edit Role" subtitle="Update role settings and permissions">
    <a href="{{ route('admin.roles.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                @include('admin.master.roles.partials.form-fields', [
                    'role' => $role,
                    'permissionsByModule' => $permissionsByModule,
                    'assignedPermissionIds' => $assignedPermissionIds,
                ])
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Role</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
