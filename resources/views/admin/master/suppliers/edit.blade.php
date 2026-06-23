@extends('layouts.admin')
@section('title', 'Edit Supplier')
@section('content')
<x-admin-page-header title="Edit Supplier" subtitle="Update supplier details">
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" :value="old('name', $supplier->name)" required />
                <x-admin-form-input label="Contact Person" name="contact_person" :value="old('contact_person', $supplier->contact_person)" />
                <x-admin-form-input label="Phone" name="phone" :value="old('phone', $supplier->phone)" />
                <x-admin-form-input label="Email" name="email" type="email" :value="old('email', $supplier->email)" />
                <x-admin-form-input label="GSTIN" name="gstin" :value="old('gstin', $supplier->gstin)" />
                <x-admin-form-group label="Address" name="address" col="col-md-12">
                    <textarea name="address" id="address" class="form-control" rows="2">{{ old('address', $supplier->address) }}</textarea>
                </x-admin-form-group>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', $supplier->is_active))>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Supplier</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
