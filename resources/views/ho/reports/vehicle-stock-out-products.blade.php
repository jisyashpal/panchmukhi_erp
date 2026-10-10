@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Stock Out Products — '.$vehicleLabel)
@section('content')
    <x-admin-page-header title="{{ $vehicleLabel }}" subtitle="Products used on this vehicle and the amount spent on each one">
        <div class="d-flex gap-2">
            <a href="{{ route('ho.reports.usage-by-category', $filters->toQueryArray()) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
            <x-report-export-button />
        </div>
    </x-admin-page-header>

    <x-report-filters :plants="$plants" :categories="$categories" :show-dates="true"
        search-placeholder="Category name..."
        :clear-route="route('ho.reports.usage-by-category.vehicle', $vehicleKey)" />

    @if ($rows->isNotEmpty())
        <div class="admin-stat-grid mb-3">
            <div class="admin-stat-card stat-accent-blue">
                <div class="stat-icon stat-icon-blue"><i class="bi bi-box-seam"></i></div>
                <div class="stat-label">Products</div>
                <div class="stat-value">{{ number_format($rows->count(), 0) }}</div>
            </div>
            <div class="admin-stat-card stat-accent-amber">
                <div class="stat-icon stat-icon-amber"><i class="bi bi-arrow-repeat"></i></div>
                <div class="stat-label">Usage Entries</div>
                <div class="stat-value">{{ number_format($rows->sum('usage_count'), 0) }}</div>
            </div>
            <div class="admin-stat-card stat-accent-violet">
                <div class="stat-icon stat-icon-violet"><i class="bi bi-boxes"></i></div>
                <div class="stat-label">Total Qty</div>
                <div class="stat-value">{{ number_format($rows->sum('total_qty'), 0) }}</div>
            </div>
            <div class="admin-stat-card stat-accent-emerald">
                <div class="stat-icon stat-icon-emerald"><i class="bi bi-currency-rupee"></i></div>
                <div class="stat-label">Amount</div>
                <div class="stat-value">₹{{ number_format($rows->sum('total_amount'), 0) }}</div>
            </div>
        </div>
    @endif

    <div class="admin-card admin-table-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-end">Usage Entries</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $i => $product)
                        <tr>
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td class="fw-semibold">{{ $product->product_name }}</td>
                            <td class="text-muted">{{ $product->category_name }}</td>
                            <td class="text-end">{{ number_format($product->usage_count, 0) }}</td>
                            <td class="text-end">{{ number_format($product->total_qty, 0) }}</td>
                            <td class="text-end fw-semibold text-success">₹{{ number_format($product->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        @include('partials.table-empty', [
                            'colspan' => 6,
                            'icon' => 'bi-box-seam',
                            'message' => 'No products found for this vehicle.',
                        ])
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3">Total</td>
                            <td class="text-end">{{ number_format($rows->sum('usage_count'), 0) }}</td>
                            <td class="text-end">{{ number_format($rows->sum('total_qty'), 0) }}</td>
                            <td class="text-end text-success">₹{{ number_format($rows->sum('total_amount'), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
