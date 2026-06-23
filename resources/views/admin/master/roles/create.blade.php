@extends('layouts.admin')
@section('title', 'Add Role')
@section('content')
<x-admin-page-header title="Add Role" subtitle="Create a role and assign permissions">
    <a href="{{ route('admin.roles.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf
            <div class="row g-3">
                @include('admin.master.roles.partials.form-fields', [
                    'permissionsByModule' => $permissionsByModule,
                    'assignedPermissionIds' => old('permission_ids', []),
                ])
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save Role</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
