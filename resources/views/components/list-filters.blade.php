@props(['sites' => null, 'products' => null, 'showStatus' => false, 'showDates' => false])

<div class="admin-filters">
    <form method="GET" class="row g-3 align-items-end">
        @if($sites)
            <div class="col-6 col-md-3">
                <label class="form-label">Site</label>
                <select name="site_id" class="form-select form-select-sm">
                    <option value="">All sites</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" @selected(request('site_id') == $site->id)>{{ $site->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($products)
            <div class="col-6 col-md-3">
                <label class="form-label">Product</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">All products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>
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
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
            </div>
        @endif
        <div class="col-auto">{{ $slot }}</div>
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
        </div>
    </form>
</div>
