@extends('layouts.admin')
@section('title', 'Edit Role')
@section('content')
<x-admin-page-header title="Edit Role" subtitle="Update role settings">
    <a href="{{ route('admin.roles.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <x-admin-form-input label="Role Name" name="name" :value="old('name', $role->name)" required />
                <x-admin-form-group label="Slug" name="slug">
                    <input
                        type="text"
                        name="slug"
                        id="slug"
                        value="{{ old('slug', $role->slug) }}"
                        class="form-control @error('slug') is-invalid @enderror"
                        @if($role->is_system) readonly @endif
                    >
                    @if($role->is_system)
                        <div class="form-text">System role slug cannot be changed.</div>
                    @endif
                </x-admin-form-group>
                <x-admin-form-group label="Description" name="description" col="col-md-12">
                    <textarea name="description" id="description" class="form-control" rows="2">{{ old('description', $role->description) }}</textarea>
                </x-admin-form-group>
                <x-admin-form-input label="Sort Order" name="sort_order" type="number" :value="old('sort_order', $role->sort_order)" />
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input type="hidden" name="requires_site" value="0">
                        <input type="checkbox" name="requires_site" value="1" class="form-check-input" id="requires_site" @checked(old('requires_site', $role->requires_site))>
                        <label class="form-check-label" for="requires_site">Requires site assignment</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', $role->is_active))>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Role</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
