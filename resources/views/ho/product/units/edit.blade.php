@extends('layouts.ho')
@section('title', 'Edit Unit')
@section('content')
<x-admin-page-header title="Edit Unit" subtitle="Update measurement unit">
    <a href="{{ route('ho.units.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('ho.units.update', $unit) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" :value="old('name', $unit->name)" required />
                <x-admin-form-input label="Abbreviation" name="abbreviation" :value="old('abbreviation', $unit->abbreviation)" required />
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Unit</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
