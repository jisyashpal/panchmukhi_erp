@props([
    'sites',
    'categories' => null,
    'products' => null,
    'showDates' => false,
    'searchPlaceholder' => 'Search...',
    'clearRoute',
])

@php
    $filterKeys = array_filter([
        'search',
        $sites ? 'site_id' : null,
        $categories ? 'category_id' : null,
        $products ? 'product_id' : null,
        $showDates ? 'date_from' : null,
        $showDates ? 'date_to' : null,
    ]);
@endphp

<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm"
                placeholder="{{ $searchPlaceholder }}">
        </div>
        @if($sites)
            <div class="col-6 col-md-2">
                <label class="form-label small">Site</label>
                <select name="site_id" class="form-select form-select-sm">
                    <option value="">All sites</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" @selected(request('site_id') == $site->id)>{{ $site->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($categories)
            <div class="col-6 col-md-2">
                <label class="form-label small">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($products)
            <div class="col-6 col-md-2">
                <label class="form-label small">Product</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">All products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($showDates)
            <div class="col-6 col-md-2">
                <label class="form-label small">From date</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">To date</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
            </div>
        @endif
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm">
                <i class="bi bi-search me-1"></i>Search
            </button>
            @if(request()->anyFilled($filterKeys))
                <a href="{{ $clearRoute }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
        </div>
    </form>
</div>
