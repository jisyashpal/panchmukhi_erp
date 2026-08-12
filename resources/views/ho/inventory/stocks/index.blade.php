@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Stocks')
@section('content')
<x-admin-page-header title="Stocks" subtitle="Current balances and {{ $financialYearRange->displayLabel() }} movement summary">
    <a href="{{ route('ho.stocks.stock-in') }}" class="btn btn-success btn-sm me-1">
        <i class="bi bi-box-arrow-in-down me-1"></i>Stock In
    </a>
    <a href="{{ route('ho.stocks.stock-out') }}" class="btn btn-warning btn-sm">
        <i class="bi bi-box-arrow-up me-1"></i>Stock Out
    </a>
</x-admin-page-header>

<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-2">
            <label class="form-label small">Plant</label>
            <select name="plant_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All plants</option>
                @foreach($plants as $s)
                    <option value="{{ $s->id }}" @selected(request('plant_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small">Category</label>
            <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3 position-relative">
            <label class="form-label small">Search Item Name / SKU</label>
            <input type="text" name="search" id="product-search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Enter item name or SKU..." autocomplete="off">
            <div id="autocomplete-suggestions" class="autocomplete-suggestions d-none shadow border rounded-bottom bg-white"></div>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small">Filter by Item Name</label>
            <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All items</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
            @if(request()->anyFilled(['plant_id', 'product_id', 'search', 'category_id']))
                <a href="{{ route('ho.stocks.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Plant</th>
                    <th>Category</th>
                    <th>Item Name</th>
                    <th class="text-end">Current Qty</th>
                    <th class="text-end">FY Stock In</th>
                    <th class="text-end">FY Stock Out</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($balances as $b)
                <tr>
                    <td>{{ $b->plant->name }}</td>
                    <td>{{ $b->product->category?->name ?? '—' }}</td>
                    <td class="fw-semibold">{{ $b->product->name }}</td>
                    <td class="text-end">{{ $b->quantity }} {{ $b->product->unit->abbreviation ?? '' }}</td>
                    <td class="text-end text-success">{{ $b->fy_stock_in }} {{ $b->product->unit->abbreviation ?? '' }}</td>
                    <td class="text-end text-danger">{{ $b->fy_stock_out }} {{ $b->product->unit->abbreviation ?? '' }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('ho.stocks.show', ['plant_id' => $b->plant_id, 'product_id' => $b->product_id]) }}"
                            class="btn btn-sm btn-admin-outline" title="View movements">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="admin-empty"><i class="bi bi-boxes d-block"></i>No stock activity found for {{ $financialYearRange->displayLabel() }}</div>
                    </td>
                </tr>
                @endforelse
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
@include('partials.infinite-suggestions')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('product-search');
    const suggestionsBox = document.getElementById('autocomplete-suggestions');
    const products = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]));

    if (!searchInput || !suggestionsBox) return;

    const infinite = window.PanchmukhiInfiniteSuggestions.create(suggestionsBox, {
        pageSize: 25,
        renderItem: function (p) {
            const div = document.createElement('div');
            div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
            div.innerHTML = `<span class="fw-semibold text-dark d-block text-truncate" style="max-width:100%">${p.name}</span> <span class="text-muted small">${p.sku}</span>`;
            div.addEventListener('click', function() {
                searchInput.value = p.name;
                infinite.reset();
                suggestionsBox.classList.add('d-none');
                searchInput.form.submit();
            });
            return div;
        },
    });

    searchInput.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();

        if (!val) {
            infinite.reset();
            suggestionsBox.classList.add('d-none');
            return;
        }

        const filtered = products.filter(p =>
            p.name.toLowerCase().includes(val) ||
            (p.sku && p.sku.toLowerCase().includes(val))
        );

        if (!infinite.setResults(filtered)) {
            suggestionsBox.classList.add('d-none');
            return;
        }

        suggestionsBox.classList.remove('d-none');
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
            infinite.reset();
            suggestionsBox.classList.add('d-none');
        }
    });
});
</script>
@endpush
@endsection
