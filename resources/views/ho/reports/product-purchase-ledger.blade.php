@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Purchase Ledger — ' . $product->name)
@section('content')
<x-admin-page-header
    title="Purchase Ledger: {{ $product->name }}"
    subtitle="All purchased line items for this product{{ $product->sku ? ' (SKU: '.$product->sku.')' : '' }}"
>
    <div class="d-flex gap-2">
        <a href="{{ route('ho.reports.product-purchase', $filters->toQueryArray()) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <x-report-export-button />
    </div>
</x-admin-page-header>

<x-report-filters
    :plants="$plants"
    :show-dates="true"
    search-placeholder="PO ref, supplier or plant..."
    :clear-route="route('ho.reports.product-purchase.ledger', $product)"
/>

@if($items->isNotEmpty())
<div class="admin-stat-grid mb-3">
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-list-ul"></i></div>
        <div class="stat-label">Line Items</div>
        <div class="stat-value">{{ $items->count() }}</div>
    </div>
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Total Qty</div>
        <div class="stat-value">{{ number_format($items->sum('quantity'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-currency-rupee"></i></div>
        <div class="stat-label">Total Amount</div>
        <div class="stat-value">₹{{ number_format($items->sum('line_total'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-building"></i></div>
        <div class="stat-label">Suppliers</div>
        <div class="stat-value">{{ $items->pluck('supplier_name')->filter()->unique()->count() }}</div>
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
                        <td class="text-end">{{ number_format($item->quantity, 0) }}</td>
                        <td class="text-end text-muted">₹{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-end fw-semibold text-success">₹{{ number_format($item->line_total, 2) }}</td>
                        <td><x-status-badge :status="\App\Enums\PurchaseStatus::from($item->status)" /></td>
                    </tr>
                @empty
                    @include('partials.table-empty', [
                        'colspan' => 8,
                        'icon'    => 'bi-journal-text',
                        'message' => 'No purchase items found for this product.',
                    ])
                @endforelse
            </tbody>
            @if($items->isNotEmpty())
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="4">Total</td>
                    <td class="text-end">{{ number_format($items->sum('quantity'), 0) }}</td>
                    <td></td>
                    <td class="text-end text-success">₹{{ number_format($items->sum('line_total'), 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
