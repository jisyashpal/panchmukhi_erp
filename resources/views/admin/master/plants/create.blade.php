@extends('layouts.admin')
@section('title', 'Add Site')
@section('content')
<x-admin-page-header title="Add Site" subtitle="Create a new site or plant">
    <a href="{{ route('admin.sites.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.sites.store') }}">
            @csrf
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" required />
                <x-admin-form-input label="Code" name="code" required />
                <x-admin-form-group label="Type" name="type">
                    <select name="type" id="type" class="form-select">
                        <option value="site" @selected(old('type') === 'site')>Site</option>
                        <option value="plant" @selected(old('type') === 'plant')>Plant</option>
                    </select>
                </x-admin-form-group>
                <x-admin-form-group label="Address" name="address" col="col-md-12">
                    <textarea name="address" id="address" class="form-control" rows="3">{{ old('address') }}</textarea>
                </x-admin-form-group>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', '1') == '1')>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save Site</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
