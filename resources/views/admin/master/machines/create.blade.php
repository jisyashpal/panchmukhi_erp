@extends('layouts.admin')
@section('title', 'Add Machine')
@section('content')
<x-admin-page-header title="Add Machine" subtitle="Register equipment at a site or plant">
    <a href="{{ route('admin.machines.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.machines.store') }}">
            @csrf
            <div class="row g-3">
                <x-admin-form-group label="Site / Plant" name="site_id" required>
                    <select name="site_id" id="site_id" class="form-select" required>
                        <option value="">Select site</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}" @selected(old('site_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </x-admin-form-group>
                <x-admin-form-input label="Name" name="name" required />
                <x-admin-form-input label="Code" name="code" required placeholder="e.g. MX-A" />
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', '1') == '1')>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save Machine</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
