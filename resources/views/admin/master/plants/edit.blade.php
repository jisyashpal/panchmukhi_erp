@extends('layouts.admin')
@section('title', 'Edit Site')
@section('content')
<x-admin-page-header title="Edit Site" subtitle="Update site or plant details">
    <a href="{{ route('admin.sites.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.sites.update', $site) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" :value="old('name', $site->name)" required />
                <x-admin-form-input label="Code" name="code" :value="old('code', $site->code)" required />
                <x-admin-form-group label="Type" name="type">
                    <select name="type" id="type" class="form-select">
                        <option value="site" @selected(old('type', $site->type) === 'site')>Site</option>
                        <option value="plant" @selected(old('type', $site->type) === 'plant')>Plant</option>
                    </select>
                </x-admin-form-group>
                <x-admin-form-group label="Address" name="address" col="col-md-12">
                    <textarea name="address" id="address" class="form-control" rows="3">{{ old('address', $site->address) }}</textarea>
                </x-admin-form-group>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', $site->is_active))>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Site</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
