@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Date-wise Purchase Items')
@section('content')
<x-admin-page-header title="Date-wise Purchase Items" subtitle="Every purchase order line item listed one by one, newest first">
    <div class="d-flex gap-2 flex-wrap">
        <x-report-export-button />
        <a href="{{ route('ho.reports.purchase-items', ['export' => 'all']) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-download me-1"></i>Export All Purchases
        </a>
    </div>
</x-admin-page-header>

<x-report-filters
    :plants="$plants"
    :categories="$categories"
    :products="$products"
    :show-dates="true"
    search-placeholder="Product, SKU, PO ref, supplier..."
    :clear-route="route('ho.reports.purchase-items')"
/>

@if($totals->line_count > 0)
<div class="admin-stat-grid mb-3">
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-list-ul"></i></div>
        <div class="stat-label">Line Items</div>
        <div class="stat-value">{{ number_format($totals->line_count, 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-receipt"></i></div>
        <div class="stat-label">Purchase Orders</div>
        <div class="stat-value">{{ number_format($totals->order_count, 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Total Qty</div>
        <div class="stat-value">{{ number_format($totals->total_qty, 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-currency-rupee"></i></div>
        <div class="stat-label">Total Amount</div>
        <div class="stat-value">₹{{ number_format($totals->total_amount, 0) }}</div>
    </div>
</div>
@endif

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>PO Ref</th>
                    <th>Plant</th>
                    <th>Supplier</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Line Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td class="text-nowrap text-muted">
                            {{ $item->ordered_at ? \Carbon\Carbon::parse($item->ordered_at)->format('d M Y') : '—' }}
                        </td>
                        <td>
                            <a href="{{ route('ho.purchases.show', $item->purchase_id) }}" class="fw-semibold">
                                {{ $item->reference_no }}
                            </a>
                        </td>
                        <td>{{ $item->plant_name }}</td>
                        <td class="text-muted">{{ $item->supplier_name ?? '—' }}</td>
                        <td class="fw-semibold">{{ $item->product_name }}</td>
                        <td class="text-muted">{{ $item->sku ?: '—' }}</td>
                        <td>{{ $item->category_name }}</td>
                        <td class="text-muted">{{ $item->unit ?: '—' }}</td>
                        <td class="text-end">{{ number_format($item->quantity, 0) }}</td>
                        <td class="text-end text-muted">₹{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-end fw-semibold text-success">₹{{ number_format($item->line_total, 2) }}</td>
                        <td><x-status-badge :status="\App\Enums\PurchaseStatus::from($item->status)" /></td>
                    </tr>
                @empty
                    @include('partials.table-empty', [
                        'colspan' => 12,
                        'icon'    => 'bi-list-ul',
                        'message' => 'No purchase items found for the selected filters.',
                    ])
                @endforelse
            </tbody>
            @if($totals->line_count > 0)
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="8">Total</td>
                    <td class="text-end">{{ number_format($totals->total_qty, 0) }}</td>
                    <td></td>
                    <td class="text-end text-success">₹{{ number_format($totals->total_amount, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@if($items->hasPages())
    <div class="mt-3">{{ $items->links() }}</div>
@endif
@endsection
