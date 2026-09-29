@php
    $isStockOut = ($type ?? 'in') === 'out';
@endphp
<div class="admin-card admin-table-card mb-4">
    <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0">
            <i class="bi {{ $isStockOut ? 'bi-box-arrow-up text-warning' : 'bi-box-arrow-in-down text-success' }} me-2"></i>
            {{ $isStockOut ? 'Stock Out records' : 'Stock In records' }}
        </h2>
        <span class="small text-muted">{{ $movements->total() }} record(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Plant</th>
                    <th>Product</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Rate (₹)</th>
                    @if($isStockOut)
                        <th>Vehicle / Machine</th>
                        <th>Reason</th>
                    @else
                        <th>Source</th>
                        <th>Supplier</th>
                    @endif
                    <th>By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $m)
                    @php
                        $unit = $m->product?->unit?->abbreviation ?? $m->product?->unit?->name ?? '';
                    @endphp
                    <tr>
                        <td class="text-nowrap text-muted small">{{ $m->moved_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td>{{ $m->plant?->name ?? '—' }}</td>
                        <td>
                            <span class="fw-semibold d-block">{{ $m->product?->name ?? '—' }}</span>
                            @if($m->product?->sku)
                                <code class="small text-muted">{{ $m->product->sku }}</code>
                            @endif
                        </td>
                        <td class="text-end fw-semibold {{ $isStockOut ? 'text-danger' : 'text-success' }}">
                            {{ $isStockOut ? '-' : '+' }}{{ number_format((float) $m->quantity, 2) }}
                            @if($unit)
                                <span class="text-muted fw-normal">{{ $unit }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($m->unit_price !== null)
                                ₹{{ number_format((float) $m->unit_price, 2) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        @if($isStockOut)
                            <td>{{ $m->vehicle?->displayLabel() ?? '—' }}</td>
                            <td class="small text-muted">{{ $m->reason ?: '—' }}</td>
                        @else
                            <td>
                                @if($m->reference_type === \App\Enums\StockReferenceType::Purchase && $m->reference_id)
                                    @php $poRef = $purchaseReferences[$m->reference_id] ?? null; @endphp
                                    @if($poRef)
                                        <a href="{{ route('ho.purchases.show', $m->reference_id) }}" class="fw-semibold">{{ $poRef }}</a>
                                    @else
                                        PO #{{ $m->reference_id }}
                                    @endif
                                @else
                                    {{ $m->reference_type?->label() ?? 'Stock In' }}
                                @endif
                            </td>
                            <td class="text-muted">{{ $m->supplier?->name ?? '—' }}</td>
                        @endif
                        <td class="small text-muted">{{ $m->performer?->name ?? '—' }}</td>
                        <td class="small text-muted">{{ $m->notes ?: '—' }}</td>
                    </tr>
                @empty
                    @include('partials.table-empty', [
                        'colspan' => 9,
                        'icon' => $isStockOut ? 'bi-box-arrow-up' : 'bi-box-arrow-in-down',
                        'message' => $isStockOut
                            ? 'No stock out records for the selected filters.'
                            : 'No stock in records for the selected filters.',
                    ])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if($movements->hasPages())
    <div class="mb-4">{{ $movements->links() }}</div>
@endif
