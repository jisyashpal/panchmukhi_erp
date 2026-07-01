@extends('layouts.ho')
@section('title', 'Party Ledger - ' . $supplier->name)
@section('content')
<x-admin-page-header title="Party Ledger: {{ $supplier->name }}" subtitle="Detailed purchase ledger for the supplier">
    <div class="d-flex gap-2">
        <a href="{{ route('ho.reports.party-purchase') }}" class="btn btn-sm btn-admin-outline">
            <i class="bi bi-arrow-left me-1"></i>Back to Summary
        </a>
        <x-report-export-button />
    </div>
</x-admin-page-header>

<x-report-filters
    :plants="$plants"
    :show-dates="true"
    search-placeholder="PO reference or item..."
    :clear-route="route('ho.reports.party-ledger', $supplier->id)"
/>

<!-- Supplier Details Card -->
<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <span class="text-muted small d-block">Contact Person</span>
                <strong>{{ $supplier->contact_person ?? '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Phone / Email</span>
                <strong>{{ $supplier->phone ?? '—' }}</strong>
                @if($supplier->email)
                    <span class="d-block small text-muted">{{ $supplier->email }}</span>
                @endif
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">GSTIN</span>
                <strong>{{ $supplier->gstin ?? '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Address</span>
                <span class="small">{{ $supplier->address ?? '—' }}</span>
            </div>
        </div>
    </div>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>PO Ref</th>
                    <th>Plant</th>
                    <th>Items Purchased</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">GST</th>
                    <th class="text-end">Grand Total</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $p)
                    <tr>
                        <td class="small text-muted">{{ $p->ordered_at?->format('d M Y') ?? '—' }}</td>
                        <td><code class="small">{{ $p->reference_no }}</code></td>
                        <td>{{ $p->plant->name }}</td>
                        <td class="small" style="max-width: 250px;">
                            @foreach($p->items as $item)
                                <div class="text-truncate" title="{{ $item->product->name }} (Qty: {{ $item->quantity }})">
                                    • {{ $item->product->name }} <span class="text-muted">x {{ $item->quantity }}</span>
                                </div>
                            @endforeach
                        </td>
                        <td class="text-end">₹{{ number_format($p->subtotal, 2) }}</td>
                        <td class="text-end">₹{{ number_format($p->total_gst, 2) }}</td>
                        <td class="text-end fw-semibold">₹{{ number_format($p->grand_total, 2) }}</td>
                        <td><x-status-badge :status="$p->status" /></td>
                        <td class="text-end">
                            <a href="{{ route('ho.purchases.show', $p) }}" class="btn btn-sm btn-admin-outline">
                                <i class="bi bi-eye me-1"></i>View PO
                            </a>
                        </td>
                    </tr>
                @empty
                    @include('partials.table-empty', ['colspan' => 9, 'icon' => 'bi-receipt', 'message' => 'No purchase ledger entries found for the selected filters.'])
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot>
                    <tr class="table-light fw-semibold">
                        <td colspan="4">Total</td>
                        <td class="text-end">₹{{ number_format($rows->sum('subtotal'), 2) }}</td>
                        <td class="text-end">₹{{ number_format($rows->sum('total_gst'), 2) }}</td>
                        <td class="text-end">₹{{ number_format($rows->sum('grand_total'), 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
