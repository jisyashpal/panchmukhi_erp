@extends('layouts.admin')
@section('title', 'Edit Plant')
@section('content')
<x-admin-page-header title="Edit Plant" subtitle="Update plant or mine details">
    <a href="{{ route('admin.plants.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.plants.update', $plant) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" :value="old('name', $plant->name)" required />
                <x-admin-form-input label="Code" name="code" :value="old('code', $plant->code)" required />
                <x-admin-form-group label="Type" name="type">
                    <select name="type" id="type" class="form-select">
                        <option value="plant" @selected(old('type', $plant->type) === 'plant')>Plant</option>
                        <option value="mine" @selected(old('type', $plant->type) === 'mine')>Mine</option>
                    </select>
                </x-admin-form-group>
                <x-admin-form-group label="Address" name="address" col="col-md-12">
                    <textarea name="address" id="address" class="form-control" rows="3">{{ old('address', $plant->address) }}</textarea>
                </x-admin-form-group>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', $plant->is_active))>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Plant</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
