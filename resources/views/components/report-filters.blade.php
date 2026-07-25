@props([
    'plants',
    'categories' => null,
    'products' => null,
    'vehicles' => null,
    'showDates' => false,
    'searchPlaceholder' => 'Search...',
    'clearRoute',
])

@php
    $dateFrom = request('date_from', $financialYear->dateFrom ?? null);
    $dateTo = request('date_to', $financialYear->dateTo ?? null);
    $filterKeys = array_filter([
        'search',
        'fy',
        $plants ? 'plant_id' : null,
        $categories ? 'category_id' : null,
        $products ? 'product_id' : null,
        $vehicles ? 'vehicle_id' : null,
        $showDates ? 'date_from' : null,
        $showDates ? 'date_to' : null,
    ]);
@endphp

<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        @if(isset($financialYear))
            <div class="col-6 col-md-2">
                <label class="form-label small">Financial Year</label>
                <x-financial-year-select compact :selected="request('fy', $financialYear->label)" />
            </div>
        @endif
        <div class="col-12 col-md-3">
            <label class="form-label small">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm"
                placeholder="{{ $searchPlaceholder }}">
        </div>
        @if($plants)
            <div class="col-6 col-md-2">
                <label class="form-label small">Plant</label>
                <select name="plant_id" class="form-select form-select-sm">
                    <option value="">All plants</option>
                    @foreach($plants as $plant)
                        <option value="{{ $plant->id }}" @selected(request('plant_id') == $plant->id)>{{ $plant->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($vehicles)
            <div class="col-6 col-md-2">
                <label class="form-label small">Vehicle</label>
                <select name="vehicle_id" class="form-select form-select-sm">
                    <option value="">All vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected(request('vehicle_id') == $vehicle->id)>
                            {{ method_exists($vehicle, 'displayLabel') ? $vehicle->displayLabel() : $vehicle->name }}
                        </option>
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
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">To date</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
            </div>
        @endif
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm">
                <i class="bi bi-search me-1"></i>Search
            </button>
            @if(request()->anyFilled($filterKeys))
                <a href="{{ $clearRoute }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>
