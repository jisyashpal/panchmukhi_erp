@extends('layouts.site')
@section('title', 'Purchase ' . $purchase->reference_no)
@section('content')
<x-admin-page-header title="Purchase {{ $purchase->reference_no }}" subtitle="{{ $purchase->site->name }}">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <x-status-badge :status="$purchase->status" />
        @if($purchase->is_interstate)
            <span class="badge bg-info">Inter-state · IGST</span>
        @else
            <span class="badge bg-secondary">Intra-state · CGST+SGST</span>
        @endif
        <a href="{{ route('site.purchases.index') }}" class="btn btn-admin-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i>All purchases
        </a>
    </div>
</x-admin-page-header>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="admin-card mb-4">
            <div class="admin-card-header"><h2>Order details</h2></div>
            <div class="admin-card-body">
                <div class="row g-3 small">
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Supplier</span>
                        <strong>{{ $purchase->supplier->name }}</strong>
                        @if($purchase->supplier->gstin)
                            <div class="text-muted">GSTIN: {{ $purchase->supplier->gstin }}</div>
                        @endif
                        @if($purchase->supplier->phone)
                            <div class="text-muted">{{ $purchase->supplier->phone }}</div>
                        @endif
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Site / Plant</span>
                        <strong>{{ $purchase->site->name }}</strong>
                    </div>
                    @if($purchase->purchase_request_id)
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Purchase request</span>
                        <strong>#{{ $purchase->purchase_request_id }}</strong>
                    </div>
                    @endif
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Ordered on</span>
                        <strong>{{ $purchase->ordered_at?->format('d M Y H:i') ?? '—' }}</strong>
                    </div>
                    @if($purchase->supplier_invoice_no)
                    <div class="col-sm-6">
                        <span class="text-muted d-block">Supplier invoice</span>
                        <strong>{{ $purchase->supplier_invoice_no }}</strong>
                        @if($purchase->supplier_invoice_date)
                            <span class="text-muted"> · {{ $purchase->supplier_invoice_date->format('d M Y') }}</span>
                        @endif
                    </div>
                    @endif
                    @if($purchase->notes)
                    <div class="col-12">
                        <span class="text-muted d-block">Notes</span>
                        {{ $purchase->notes }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="admin-card admin-table-card">
            <div class="admin-card-header"><h2>Line items (price &amp; GST)</h2></div>
            <div class="table-responsive">
                <table class="table mb-0 table-sm">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">GST%</th>
                            <th class="text-end">Taxable</th>
                            <th class="text-end">CGST</th>
                            <th class="text-end">SGST</th>
                            <th class="text-end">IGST</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Rcvd</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->items as $item)
                        <tr>
                            <td>{{ $item->product->name }}</td>
                            <td class="text-end">{{ number_format($item->quantity, 2) }}</td>
                            <td class="text-end">₹{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-end">{{ number_format($item->gst_rate, 2) }}%</td>
                            <td class="text-end">₹{{ number_format($item->taxable_amount, 2) }}</td>
                            <td class="text-end">₹{{ number_format($item->cgst_amount, 2) }}</td>
                            <td class="text-end">₹{{ number_format($item->sgst_amount, 2) }}</td>
                            <td class="text-end">₹{{ number_format($item->igst_amount, 2) }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($item->line_total, 2) }}</td>
                            <td class="text-end">{{ number_format($item->received_quantity, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        @include('partials.purchase-pdf-form')
        <div class="admin-card mb-4 border-primary">
            <div class="admin-card-header bg-primary-subtle"><h2 class="mb-0">Amount summary</h2></div>
            <div class="admin-card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td>Taxable subtotal</td><td class="text-end">₹{{ number_format($purchase->subtotal, 2) }}</td></tr>
                    <tr><td>CGST</td><td class="text-end">₹{{ number_format($purchase->total_cgst, 2) }}</td></tr>
                    <tr><td>SGST</td><td class="text-end">₹{{ number_format($purchase->total_sgst, 2) }}</td></tr>
                    <tr><td>IGST</td><td class="text-end">₹{{ number_format($purchase->total_igst, 2) }}</td></tr>
                    <tr><td>Total GST</td><td class="text-end">₹{{ number_format($purchase->total_gst, 2) }}</td></tr>
                    <tr class="fw-bold fs-5 border-top"><td>Grand total</td><td class="text-end text-primary">₹{{ number_format($purchase->grand_total, 2) }}</td></tr>
                </table>
            </div>
        </div>
        @if($purchase->received_at)
        <div class="admin-card">
            <div class="admin-card-header"><h2>Site receipt</h2></div>
            <div class="admin-card-body small">
                <p class="mb-1"><strong>Received by:</strong> {{ $purchase->receiver?->name ?? '—' }}</p>
                <p class="mb-0"><strong>Received at:</strong> {{ $purchase->received_at->format('d M Y H:i') }}</p>
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
