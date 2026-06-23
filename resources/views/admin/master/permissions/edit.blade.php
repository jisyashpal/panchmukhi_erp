@extends('layouts.admin')
@section('title', 'Edit Permission')
@section('content')
<x-admin-page-header title="Edit Permission" subtitle="Update permission details">
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.permissions.update', $permission) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                @include('admin.master.permissions.partials.form-fields', [
                    'permission' => $permission,
                    'modules' => $modules,
                ])
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Permission</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
