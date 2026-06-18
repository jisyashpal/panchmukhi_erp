@extends('layouts.admin')
@section('title', 'Add Role')
@section('content')
<x-admin-page-header title="Add Role" subtitle="Create a new user role">
    <a href="{{ route('admin.roles.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf
            <div class="row g-3">
                <x-admin-form-input label="Role Name" name="name" required />
                <x-admin-form-input label="Slug" name="slug" placeholder="e.g. site_supervisor (auto if empty)" />
                <x-admin-form-group label="Description" name="description" col="col-md-12">
                    <textarea name="description" id="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </x-admin-form-group>
                <x-admin-form-input label="Sort Order" name="sort_order" type="number" :value="old('sort_order', 10)" />
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input type="hidden" name="requires_site" value="0">
                        <input type="checkbox" name="requires_site" value="1" class="form-check-input" id="requires_site" @checked(old('requires_site', true))>
                        <label class="form-check-label" for="requires_site">Requires site assignment</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', '1') == '1')>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save Role</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
