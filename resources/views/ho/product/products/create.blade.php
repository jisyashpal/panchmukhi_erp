@extends('layouts.ho')
@section('title', 'Add Product')
@section('content')
<x-admin-page-header title="Add Product" subtitle="Add a new product to the catalog">
    <a href="{{ route('ho.products.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('ho.products.store') }}">
            @csrf
            <div class="row g-3">
                <x-admin-form-input label="Item Name" name="name" required />
                <x-admin-form-input label="Size/Part" name="size" />
                <x-admin-form-input label="SKU" name="sku" readonly required />
                @include('ho.product.products.partials.catalog-selects')
                <x-admin-form-input label="Reorder Level" name="reorder_level" type="number" value="10" required />
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', '1') == '1')>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Save Product</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
@include('partials.product-sku-script')
@endpush
@endsection
