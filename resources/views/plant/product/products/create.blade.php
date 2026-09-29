@extends('layouts.plant')
@section('title', 'Add Products')
@php
    $catalogReturn = array_filter([
        'redirect' => 'products.create',
        'product_request_id' => request('product_request_id'),
        'product_request_item_id' => request('product_request_item_id'),
        'pc_name' => request('name'),
        'pc_size' => request('size'),
        'pc_sku_code' => request('sku_code'),
        'pc_category_id' => request('category_id'),
        'pc_sub_category_id' => request('sub_category_id'),
        'pc_brand_id' => request('brand_id'),
        'pc_unit_id' => request('unit_id'),
        'pc_reorder_level' => request('reorder_level'),
        'category_id' => request('category_id'),
    ], fn ($value) => $value !== null && $value !== '');
@endphp
@section('content')
    <x-admin-page-header title="Add Products" subtitle="Add new products to the catalog">
        @if(request('product_request_id'))
            <a href="{{ route('plant.product-requests.show', request('product_request_id')) }}" class="btn btn-admin-outline btn-sm"><i
                    class="bi bi-arrow-left me-1"></i>Back to Request</a>
        @else
            <a href="{{ route('plant.products.index') }}" class="btn btn-admin-outline btn-sm"><i
                    class="bi bi-arrow-left me-1"></i>Back</a>
        @endif
    </x-admin-page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(request('product_request_item_id'))
                <div class="alert alert-info py-2 small mb-3">
                    Creating a catalog product for an unlisted request item. After save it will be linked automatically.
                </div>
            @endif

            <form method="POST" action="{{ route('plant.products.store') }}">
                @csrf
                @if(request('product_request_id'))
                    <input type="hidden" name="product_request_id" value="{{ request('product_request_id') }}">
                @endif
                @if(request('product_request_item_id'))
                    <input type="hidden" name="product_request_item_id" value="{{ request('product_request_item_id') }}">
                @endif
                <div id="products-container" class="mb-3">
                    <!-- Rows injected via javascript -->
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button type="button" class="btn btn-primary btn-sm" id="add-row-btn">
                        <i class="bi bi-plus-lg me-1"></i>Add Product
                    </button>
                    <button type="submit" class="btn btn-admin-primary">Save Products</button>
                </div>
            </form>
        </div>
    </div>

    <template id="product-row-template">
        <div class="product-row-group border rounded p-3 mb-3 bg-light position-relative">
            <button type="button" class="btn btn-outline-danger btn-sm position-absolute top-0 end-0 m-2 remove-row-btn"
                title="Remove Product">
                <i class="bi bi-trash"></i>
            </button>

            <!-- Row 1: SKU Code, Name, Size, SKU, Active -->
            <div class="row g-3 mb-3 align-items-end pe-5">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">SKU Code</label>
                    <input type="text" name="products[__INDEX__][sku_code]" class="form-control form-control-sm product-sku-code"
                        maxlength="20" placeholder="SKU only">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary">Item Name <span
                            class="text-danger">*</span></label>
                    <input type="text" name="products[__INDEX__][name]" class="form-control form-control-sm product-name"
                        required placeholder="Item Name">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Size/Part</label>
                    <input type="text" name="products[__INDEX__][size]" class="form-control form-control-sm product-size"
                        placeholder="Size/Part">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary">SKU Preview</label>
                    <input type="text" class="form-control form-control-sm product-sku" readonly placeholder="Auto SKU"
                        disabled>
                </div>
                <div class="col-md-2 d-flex align-items-center" style="height: 38px;">
                    <div class="form-check">
                        <input type="hidden" name="products[__INDEX__][is_active]" value="0">
                        <input type="checkbox" name="products[__INDEX__][is_active]" value="1" checked
                            class="form-check-input" id="active___INDEX__">
                        <label class="form-check-label small fw-semibold text-secondary"
                            for="active___INDEX__">Active</label>
                    </div>
                </div>
            </div>

            <!-- Row 2: Category, Item Type, Brand, Unit, Reorder Level -->
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0">Category <span
                                class="text-danger">*</span></label>
                        <a href="{{ route('plant.categories.create', $catalogReturn) }}"
                            class="text-primary small js-create-catalog" data-catalog="category" style="text-decoration: none;">
                            <i class="bi bi-plus-circle me-1"></i>Create
                        </a>
                    </div>
                    <select name="products[__INDEX__][category_id]" class="form-select form-select-sm product-category"
                        required>
                        <option value="">Select category</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0">Item Type</label>
                        <a href="{{ route('plant.sub-categories.create', $catalogReturn) }}"
                            class="text-primary small js-create-catalog" data-catalog="sub-category" style="text-decoration: none;">
                            <i class="bi bi-plus-circle me-1"></i>Create
                        </a>
                    </div>
                    <select name="products[__INDEX__][sub_category_id]"
                        class="form-select form-select-sm product-subcategory">
                        <option value="">Select Item Type</option>
                        @foreach ($subCategories as $sc)
                            <option value="{{ $sc->id }}" data-category-id="{{ $sc->category_id }}">{{ $sc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0">Brand</label>
                        <a href="{{ route('plant.brands.create', $catalogReturn) }}"
                            class="text-primary small js-create-catalog" data-catalog="brand" style="text-decoration: none;">
                            <i class="bi bi-plus-circle me-1"></i>Create
                        </a>
                    </div>
                    <select name="products[__INDEX__][brand_id]" class="form-select form-select-sm product-brand">
                        <option value="">—</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0">Unit <span
                                class="text-danger">*</span></label>
                        <a href="{{ route('plant.units.create', $catalogReturn) }}"
                            class="text-primary small js-create-catalog" data-catalog="unit" style="text-decoration: none;">
                            <i class="bi bi-plus-circle me-1"></i>Create
                        </a>
                    </div>
                    <select name="products[__INDEX__][unit_id]" class="form-select form-select-sm product-unit" required>
                        @foreach ($units as $u)
                            <option value="{{ $u->id }}" @selected(strtolower((string) $u->abbreviation) === 'nos')>{{ $u->name }} ({{ $u->abbreviation }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Reorder Level <span
                            class="text-danger">*</span></label>
                    <input type="number" name="products[__INDEX__][reorder_level]"
                        class="form-control form-control-sm product-reorder" value="10" min="0" required>
                </div>
            </div>
        </div>
    </template>

    @push('scripts')
        @include('partials.product-create-form-script')
    @endpush
@endsection