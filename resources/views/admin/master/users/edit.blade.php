@extends('layouts.admin')
@section('title', 'Edit User')
@section('content')
<x-admin-page-header title="Edit User" subtitle="Update user account and role">
    <a href="{{ route('admin.users.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" :value="old('name', $user->name)" required />
                <x-admin-form-input label="Email" name="email" type="email" :value="old('email', $user->email)" required />
                <x-admin-form-input label="New Password (optional)" name="password" type="password" />
                <x-admin-form-input label="Confirm Password" name="password_confirmation" type="password" />
                <x-admin-form-group label="Role" name="role_id" required>
                    <select name="role_id" id="role_id" class="form-select" required>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" data-requires-site="{{ $r->requires_site ? '1' : '0' }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </x-admin-form-group>
                <x-admin-form-group label="Site / Plant" name="site_id" id="site-field">
                    <select name="site_id" id="site_id" class="form-select">
                        <option value="">—</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}" @selected(old('site_id', $user->site_id) == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </x-admin-form-group>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', $user->is_active))>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update User</button>
                </div>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
    function toggleSiteField() {
        var select = document.getElementById('role_id');
        var opt = select.options[select.selectedIndex];
        document.getElementById('site-field').style.display = opt && opt.dataset.requiresSite === '1' ? 'block' : 'none';
    }
    document.getElementById('role_id').addEventListener('change', toggleSiteField);
    toggleSiteField();
</script>
@endpush
@endsection
