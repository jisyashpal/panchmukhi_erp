@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Edit Brand')
@section('content')
<x-admin-page-header title="Edit Brand" subtitle="Update product brand">
    <a href="{{ route('ho.brands.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('ho.brands.update', $brand) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" :value="old('name', $brand->name)" required />
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Brand</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
