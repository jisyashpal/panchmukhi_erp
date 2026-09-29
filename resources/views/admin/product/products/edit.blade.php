@extends('layouts.admin')
@section('title', 'Edit Product')
@section('content')
<x-admin-page-header title="Edit Product" subtitle="Update product catalog entry">
    <a href="{{ route('admin.products.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <form method="POST" action="{{ route('admin.products.update', $product) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <x-admin-form-input label="SKU Code" name="sku_code" :value="old('sku_code', $skuCode ?? '')" maxlength="20" placeholder="SKU only" col="col-md-3" />
                <x-admin-form-input label="Item Name" name="name" :value="old('name', $product->name)" required col="col-md-3" />
                <x-admin-form-input label="Size/Part" name="size" :value="old('size', $product->size)" col="col-md-3" />
                <x-admin-form-input label="SKU" name="sku" :value="old('sku', $product->sku)" readonly required col="col-md-3" />
                @include('admin.product.products.partials.catalog-selects', ['product' => $product])
                <x-admin-form-input label="Reorder Level" name="reorder_level" type="number" :value="old('reorder_level', $product->reorder_level)" required />
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" @checked(old('is_active', $product->is_active))>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary">Update Product</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
@include('partials.product-sku-script')
@endpush
@endsection

