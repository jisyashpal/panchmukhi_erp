@extends('layouts.admin')
@section('title', 'Add User')
@section('content')
<x-admin-page-header title="Add User" subtitle="Create a user and assign role (plant only when required)">
    <a href="{{ route('admin.users.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" required />
                <x-admin-form-input label="Email" name="email" type="email" required />
                <x-admin-form-input label="Password" name="password" type="password" required />
                <x-admin-form-input label="Confirm Password" name="password_confirmation" type="password" required />
                <x-admin-form-group label="Role" name="role_id" required>
                    <select name="role_id" id="role_id" class="form-select" required>
                        @foreach($roles as $r)
                            <option
                                value="{{ $r->id }}"
                                data-requires-plant="{{ $r->requires_plant ? '1' : '0' }}"
                                data-role-slug="{{ $r->slug }}"
                                @selected(old('role_id') == $r->id)
                            >{{ $r->name }}</option>
                        @endforeach
                    </select>
                </x-admin-form-group>
                <x-admin-form-group label="Plant" name="plant_id" id="plant-field">
                    <select name="plant_id" id="plant_id" class="form-select">
                        <option value="">—</option>
                        @foreach($plants as $s)
                            <option value="{{ $s->id }}" @selected(old('plant_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </x-admin-form-group>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', '1') == '1')>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save User</button>
                </div>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
    function togglePlantField() {
        var select = document.getElementById('role_id');
        var opt = select.options[select.selectedIndex];
        var requiresPlant = opt && opt.dataset.requiresPlant === '1' && opt.dataset.roleSlug !== 'purchaser';
        document.getElementById('plant-field').style.display = requiresPlant ? 'block' : 'none';
        if (!requiresPlant) {
            document.getElementById('plant_id').value = '';
        }
    }
    document.getElementById('role_id').addEventListener('change', togglePlantField);
    togglePlantField();
</script>
@endpush
@endsection
