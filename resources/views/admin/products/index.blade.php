@extends('layouts.admin')
@section('title', 'Products')
@section('content')
<x-admin-page-header title="Products" subtitle="Product catalog — full details">
    <a href="{{ route('admin.products.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Add Product
    </a>
</x-admin-page-header>

<div class="admin-filters mb-0">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label" for="filter_category_id">Category</label>
            <select name="category_id" id="filter_category_id" class="form-select form-select-sm">
                <option value="">All categories</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="filter_brand_id">Brand</label>
            <select name="brand_id" id="filter_brand_id" class="form-select form-select-sm">
                <option value="">All brands</option>
                @foreach($brands as $b)
                    <option value="{{ $b->id }}" @selected(request('brand_id') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="filter_is_active">Status</label>
            <select name="is_active" id="filter_is_active" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="1" @selected(request('is_active') === '1')>Active</option>
                <option value="0" @selected(request('is_active') === '0')>Inactive</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="filter_search">Search</label>
            <input type="search" name="search" id="filter_search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name or SKU...">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-admin-outline btn-sm w-100">
                <i class="bi bi-search me-1"></i>Filter
            </button>
        </div>
    </form>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Sub Category</th>
                    <th>Brand</th>
                    <th>Unit</th>
                    <th class="text-end">Reorder Level</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                <tr>
                    <td><code class="small">{{ $p->sku }}</code></td>
                    <td class="fw-semibold">{{ $p->name }}</td>
                    <td>{{ $p->category->name }}</td>
                    <td>{{ $p->subCategory?->name ?? '—' }}</td>
                    <td>{{ $p->brand?->name ?? '—' }}</td>
                    <td>{{ $p->unit->name }} <span class="text-muted small">({{ $p->unit->abbreviation }})</span></td>
                    <td class="text-end">{{ number_format($p->reorder_level) }}</td>
                    <td>
                        @if($p->is_active)
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <x-admin-action-edit :href="route('admin.products.edit', $p)" />
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <div class="admin-empty"><i class="bi bi-box-seam d-block"></i>No products found</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $products->links() }}</div>
@endsection
