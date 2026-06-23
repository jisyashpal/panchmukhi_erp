@extends('layouts.admin')
@section('title', 'Edit Machine')
@section('content')
<x-admin-page-header title="Edit Machine" subtitle="{{ $machine->name }}">
    <a href="{{ route('admin.machines.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.machines.update', $machine) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <x-admin-form-group label="Site / Plant" name="site_id" required>
                    <select name="site_id" id="site_id" class="form-select" required>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}" @selected(old('site_id', $machine->site_id) == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </x-admin-form-group>
                <x-admin-form-input label="Name" name="name" :value="old('name', $machine->name)" required />
                <x-admin-form-input label="Code" name="code" :value="old('code', $machine->code)" required />
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', $machine->is_active))>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Machine</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
