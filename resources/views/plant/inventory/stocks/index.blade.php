@extends('layouts.site')
@section('title', 'Stocks')
@section('content')
<x-admin-page-header title="Stock Balances" subtitle="Current inventory in this plant">
</x-admin-page-header>

<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-4 position-relative">
            <label class="form-label small">Search Product / SKU</label>
            <input type="text" name="search" id="product-search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Enter product name or SKU..." autocomplete="off">
            <div id="autocomplete-suggestions" class="autocomplete-suggestions d-none shadow border rounded-bottom bg-white"></div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small">Filter by Product</label>
            <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All products</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
            @if(request()->anyFilled(['product_id', 'search']))
                <a href="{{ route('site.stocks.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="text-end">Reorder Level</th>
                    <th class="text-end">Quantity</th>
                </tr>
            </thead>
            <tbody>
                @if($balances->count() > 0)
                @foreach($balances as $b)
                @php
                    $isLow = $b->quantity <= ($b->product->reorder_level ?? 0);
                @endphp
                <tr @if($isLow) class="table-warning" @endif>
                    <td class="fw-semibold">
                        {{ $b->product->name }}
                        @if($isLow)
                            <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">Low Stock</span>
                        @endif
                    </td>
                    <td><code class="small text-muted">{{ $b->product->sku }}</code></td>
                    <td class="text-end text-muted">{{ $b->product->reorder_level }} {{ $b->product->unit->abbreviation ?? '' }}</td>
                    <td class="text-end fw-bold {{ $isLow ? 'text-danger' : 'text-success' }}">
                        {{ $b->quantity }} {{ $b->product->unit->abbreviation ?? '' }}
                    </td>
                </tr>
                @endforeach
                @elseif($selectedProduct)
                <tr>
                    <td class="fw-semibold">{{ $selectedProduct->name }}</td>
                    <td><code class="small text-muted">{{ $selectedProduct->sku }}</code></td>
                    <td class="text-end text-muted">{{ $selectedProduct->reorder_level }} {{ $selectedProduct->unit->abbreviation ?? '' }}</td>
                    <td class="text-end fw-bold text-muted">0 {{ $selectedProduct->unit->abbreviation ?? '' }}</td>
                </tr>
                @else
                <tr>
                    <td colspan="4">
                        <div class="admin-empty"><i class="bi bi-boxes d-block"></i>No stock records found</div>
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $balances->links() }}</div>

@push('scripts')
<style>
.autocomplete-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 250px;
    overflow-y: auto;
    z-index: 1050;
    margin-top: 2px;
}
.suggestion-item {
    cursor: pointer;
    transition: background 0.15s ease-in-out;
}
.suggestion-item:hover {
    background-color: #f8f9fa;
}
.suggestion-item:last-child {
    border-bottom: none !important;
}
</style>
@php
    $stockProductSearch = $products->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'sku' => $p->sku ?? '',
    ])->values();
@endphp
<script type="application/json" id="stock-products-data">@json($stockProductSearch)</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('product-search');
    const suggestionsBox = document.getElementById('autocomplete-suggestions');
    const productsDataEl = document.getElementById('stock-products-data');
    if (!searchInput || !suggestionsBox || !productsDataEl) {
        return;
    }

    const products = JSON.parse(productsDataEl.textContent);

    searchInput.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        suggestionsBox.innerHTML = '';
        
        if (!val) {
            suggestionsBox.classList.add('d-none');
            return;
        }
        
        const filtered = products.filter(function(p) {
            return p.name.toLowerCase().includes(val) ||
                (p.sku && p.sku.toLowerCase().includes(val));
        }).slice(0, 10);
        
        if (filtered.length === 0) {
            suggestionsBox.classList.add('d-none');
            return;
        }
        
        filtered.forEach(function(p) {
            const div = document.createElement('div');
            div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
            div.innerHTML = `<span class="fw-semibold text-dark d-block text-truncate" style="max-width:100%">${p.name}</span> <span class="text-muted small">${p.sku}</span>`;
            div.addEventListener('click', function() {
                searchInput.value = p.name;
                suggestionsBox.classList.add('d-none');
                searchInput.form.submit();
            });
            suggestionsBox.appendChild(div);
        });
        
        suggestionsBox.classList.remove('d-none');
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
            suggestionsBox.classList.add('d-none');
        }
    });
});
</script>
@endpush
@endsection
