@props(['plants' => null, 'products' => null, 'vehicles' => null, 'showStatus' => false, 'showDates' => false, 'showFinancialYear' => false, 'clearRoute' => null])

@php
    $dateFrom = request('date_from', $financialYear->dateFrom ?? null);
    $dateTo = request('date_to', $financialYear->dateTo ?? null);
    $filterKeys = array_filter([
        'plant_id',
        'product_id',
        'vehicle_id',
        'status',
        'fy',
        'date_from',
        'date_to',
    ]);
@endphp

<div class="admin-filters">
    <form method="GET" class="row g-3 align-items-end">
        @if($showFinancialYear && isset($financialYear))
            <div class="col-6 col-md-2">
                <label class="form-label">Financial Year</label>
                <x-financial-year-select compact :selected="request('fy', $financialYear->label)" />
            </div>
        @endif
        @if($plants)
            <div class="col-6 col-md-3">
                <label class="form-label">Plant</label>
                <select name="plant_id" class="form-select form-select-sm">
                    <option value="">All plants</option>
                    @foreach($plants as $plant)
                        <option value="{{ $plant->id }}" @selected(request('plant_id') == $plant->id)>{{ $plant->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($vehicles)
            <div class="col-6 col-md-3">
                <label class="form-label">Vehicle</label>
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
        @if($products)
            <div class="col-6 col-md-3">
                <label class="form-label">Search Item Name / SKU</label>
                <select name="product_id" class="form-select form-select-sm searchable-item-select">
                    <option value="">All products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-sku="{{ $product->sku }}" data-category="{{ $product->category?->name }}" data-item-type="{{ $product->subCategory?->name }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($showStatus)
            <div class="col-6 col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="declined" @selected(request('status') === 'declined')>Declined</option>
                </select>
            </div>
        @endif
        @if($showDates)
            <div class="col-6 col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
            </div>
        @endif
        <div class="col-auto">{{ $slot }}</div>
        <div class="col-auto d-flex gap-1">
            <button type="submit" class="btn btn-admin-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
            @if($clearRoute && request()->anyFilled($filterKeys))
                <a href="{{ $clearRoute }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>
