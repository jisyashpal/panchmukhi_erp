@extends('layouts.plant')
@section('title', 'Add Category')
@section('content')
<x-admin-page-header title="Add Category" subtitle="Create a product category">
    @include('partials.catalog-create-back', ['fallback' => route('plant.categories.index'), 'portal' => 'ho'])
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('plant.categories.store') }}">
            @csrf
            @include('partials.catalog-form-return')
            <div class="row g-3">
                <x-admin-form-input label="Name" name="name" required />
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', '1') == '1')>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save Category</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
