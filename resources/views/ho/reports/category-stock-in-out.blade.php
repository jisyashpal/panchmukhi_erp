@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Product Stock In / Out')
@section('content')
<x-admin-page-header
    title="Product-wise Stock In / Out"
    subtitle="Stock received (from POs) and issued, listed by product"
>
    <x-report-export-button />
</x-admin-page-header>

<x-report-filters
    :plants="$plants"
    :categories="$categories"
    :show-dates="true"
    search-placeholder="Product, SKU, or category..."
    :clear-route="route('ho.reports.category-stock-in-out')"
/>

{{-- Summary stat cards --}}
<div class="admin-stat-grid mb-3">
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-box-arrow-in-down"></i></div>
        <div class="stat-label">Total Stock In — Qty</div>
        <div class="stat-value">{{ number_format($totalInQty, 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-currency-rupee"></i></div>
        <div class="stat-label">Total Stock In — Amount</div>
        <div class="stat-value">₹{{ number_format($totalInAmt, 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-box-arrow-up"></i></div>
        <div class="stat-label">Total Stock Out — Qty</div>
        <div class="stat-value">{{ number_format($totalOutQty, 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="stat-label">Net (In − Out)</div>
        <div class="stat-value {{ $totalInQty - $totalOutQty >= 0 ? 'text-success' : 'text-danger' }}">
            {{ number_format($totalInQty - $totalOutQty, 0) }}
        </div>
    </div>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th class="text-end">Stock In Qty</th>
                    <th class="text-end">Stock In Amount (₹)</th>
                    <th class="text-end">Stock Out Qty</th>
                    <th class="text-end">Net (In − Out)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $i => $row)
                    @php $net = $row->in_qty - $row->out_qty; @endphp
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="fw-semibold">{{ $row->product }}</td>
                        <td class="text-muted">{{ $row->sku ?: '—' }}</td>
                        <td>{{ $row->category }}</td>
                        <td class="text-muted">{{ $row->unit ?: '—' }}</td>
                        <td class="text-end text-success fw-semibold">{{ number_format($row->in_qty, 0) }}</td>
                        <td class="text-end">₹{{ number_format($row->in_amount, 2) }}</td>
                        <td class="text-end text-danger fw-semibold">{{ number_format($row->out_qty, 0) }}</td>
                        <td class="text-end fw-semibold {{ $net >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($net, 0) }}
                        </td>
                    </tr>
                @empty
                    @include('partials.table-empty', [
                        'colspan' => 9,
                        'icon'    => 'bi-bar-chart',
                        'message' => 'No stock movements found for the selected filters.',
                    ])
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="5">Total</td>
                    <td class="text-end text-success">{{ number_format($totalInQty, 0) }}</td>
                    <td class="text-end">₹{{ number_format($totalInAmt, 2) }}</td>
                    <td class="text-end text-danger">{{ number_format($totalOutQty, 0) }}</td>
                    <td class="text-end {{ $totalInQty - $totalOutQty >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($totalInQty - $totalOutQty, 0) }}
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
