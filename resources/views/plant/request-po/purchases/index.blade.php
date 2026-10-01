@extends('layouts.plant')
@section('title', 'Purchases')
@section('content')
@php
    $quotations = $quotations ?? collect();
    $purchaseOrders = $purchaseOrders ?? $purchases;
@endphp
<x-admin-page-header
    title="Purchases"
    :subtitle="$isPurchaser ? 'Quotations and purchase orders' : 'View purchase orders for this plant'"
>
    <a href="{{ route('plant.purchases.bulk-upload') }}" class="btn btn-admin-outline btn-sm me-2">
        <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Upload
    </a>
    <a href="{{ route('plant.purchases.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Create Purchase
    </a>
</x-admin-page-header>

<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label small">Search Quotation / PO / Supplier / Product</label>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm"
                placeholder="Reference, supplier, or product name...">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small">From Date</label>
            <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small">To Date</label>
            <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
            @if(request()->anyFilled(['search', 'from_date', 'to_date', 'purchase_request_id']))
                <a href="{{ route('plant.purchases.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

@if($isPurchaser)
<div class="admin-card admin-table-card mb-4">
    <div class="admin-card-header"><h2 class="h6 mb-0">Quotation Section</h2></div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Quotation No.</th>
                    <th>Supplier Name</th>
                    <th>Date of Request</th>
                    <th>Site (Plant)</th>
                    <th class="text-end">Grand Total</th>
                    <th>Create Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotations as $purchase)
                @php
                    $requestDate = $purchase->purchaseRequest?->productRequest?->created_at
                        ?? $purchase->purchaseRequest?->created_at;
                    $productNames = $purchase->items->map(fn ($item) => $item->product?->name)->filter()->unique()->values();
                @endphp
                <tr>
                    <td>
                        <span class="fw-semibold text-dark">{{ $purchase->reference_no }}</span>
                        @if($productNames->isNotEmpty())
                            <span class="d-block small text-muted">{{ $productNames->take(3)->implode(', ') }}</span>
                        @endif
                    </td>
                    <td>{{ $purchase->supplier->name }}</td>
                    <td class="small text-muted">{{ $requestDate?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $purchase->plant->name }}</td>
                    <td class="text-end fw-semibold">₹{{ number_format((float) $purchase->grand_total, 2) }}</td>
                    <td class="small text-muted">{{ $purchase->ordered_at?->format('d M Y') ?? $purchase->created_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-end">
                        <a href="{{ route('plant.purchases.show', $purchase) }}" class="btn btn-sm btn-admin-outline me-1">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                        <button type="button"
                            class="btn btn-sm btn-outline-secondary me-1 btn-print-purchase"
                            data-pdf-url="{{ route('plant.purchases.pdf', $purchase) }}"
                            data-reference="{{ $purchase->reference_no }}"
                            data-default-name="{{ $pdfDefaults['company_name'] ?: $purchase->plant->name }}"
                            data-default-address="{{ $pdfDefaults['company_address'] ?: ($purchase->plant->address ?? '') }}"
                            data-default-gstin="{{ $pdfDefaults['company_gstin'] }}">
                            <i class="bi bi-printer me-1"></i>Print
                        </button>
                        @if(! $purchase->hasConvertedPurchaseOrder())
                            <a href="{{ route('plant.purchases.create', ['quotation_id' => $purchase->id]) }}" class="btn btn-sm btn-warning me-1">
                                <i class="bi bi-cart-plus me-1"></i>Create PO
                            </a>
                        @endif
                        @if($purchase->canBeEdited())
                            <a href="{{ route('plant.purchases.edit', $purchase) }}" class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="admin-empty">
                            <i class="bi bi-file-earmark-text d-block"></i>
                            No quotations found
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($quotations, 'links'))
        <div class="admin-card-body border-top py-2">{{ $quotations->links() }}</div>
    @endif
</div>
@endif

<div class="admin-card admin-table-card">
    <div class="admin-card-header"><h2 class="h6 mb-0">{{ $isPurchaser ? 'PO Section (Purchase Orders)' : 'Purchase Orders' }}</h2></div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>PO No.</th>
                    <th>Supplier Name</th>
                    <th>Date of Request</th>
                    <th>Site (Plant)</th>
                    <th class="text-end">Grand Total</th>
                    <th>Status</th>
                    <th>PO Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseOrders as $purchase)
                @php
                    $requestDate = $purchase->purchaseRequest?->productRequest?->created_at
                        ?? $purchase->purchaseRequest?->created_at;
                    $productNames = $purchase->items->map(fn ($item) => $item->product?->name)->filter()->unique()->values();
                @endphp
                <tr>
                    <td>
                        <span class="fw-semibold text-dark">{{ $purchase->reference_no }}</span>
                        @if($productNames->isNotEmpty())
                            <span class="d-block small text-muted">{{ $productNames->take(3)->implode(', ') }}</span>
                        @endif
                    </td>
                    <td>{{ $purchase->supplier->name }}</td>
                    <td class="small text-muted">{{ $requestDate?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $purchase->plant->name }}</td>
                    <td class="text-end fw-semibold">₹{{ number_format((float) $purchase->grand_total, 2) }}</td>
                    <td><x-status-badge :status="$purchase->status" /></td>
                    <td class="small text-muted">{{ $purchase->ordered_at?->format('d M Y') ?? $purchase->created_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-end">
                        <a href="{{ route('plant.purchases.show', $purchase) }}" class="btn btn-sm btn-admin-outline me-1">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                        @if($isPurchaser)
                            <button type="button"
                                class="btn btn-sm btn-outline-secondary me-1 btn-print-purchase"
                                data-pdf-url="{{ route('plant.purchases.pdf', $purchase) }}"
                                data-reference="{{ $purchase->reference_no }}"
                                data-default-name="{{ $pdfDefaults['company_name'] ?: $purchase->plant->name }}"
                                data-default-address="{{ $pdfDefaults['company_address'] ?: ($purchase->plant->address ?? '') }}"
                                data-default-gstin="{{ $pdfDefaults['company_gstin'] }}">
                                <i class="bi bi-printer me-1"></i>Print
                            </button>
                            @if($purchase->canBeEdited())
                                <a href="{{ route('plant.purchases.edit', $purchase) }}" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                            @endif
                        @endif
                        @if(auth()->user()->canManagePlantStocks() && $purchase->isDispatched() && ($purchase->status !== \App\Enums\PurchaseStatus::Delivered || $purchase->hasPendingReceipt()))
                            <a href="{{ route('plant.purchases.receive', $purchase) }}" class="btn btn-sm btn-success me-1">
                                <i class="bi bi-box-arrow-in-down me-1"></i>Receive
                            </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="admin-empty">
                            <i class="bi bi-truck d-block"></i>
                            No purchase orders found
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if(method_exists($purchaseOrders, 'links'))
    <div class="mt-3">{{ $purchaseOrders->links() }}</div>
@endif

@if($isPurchaser)
<div class="modal fade" id="purchasePrintModal" tabindex="-1" aria-labelledby="purchasePrintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="purchasePrintModalLabel">Print document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Enter letterhead details for <strong id="print_modal_reference">—</strong> PDF.</p>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="print_company_name">Company name</label>
                    <input type="text" id="print_company_name" class="form-control form-control-sm" required maxlength="200">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="print_company_address">Address</label>
                    <textarea id="print_company_address" class="form-control form-control-sm" rows="3" maxlength="1000"></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label small mb-1" for="print_company_gstin">GSTIN</label>
                    <input type="text" id="print_company_gstin" class="form-control form-control-sm" maxlength="20" placeholder="e.g. 22AAAAA0000A1Z5">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-admin-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn_confirm_print">
                    <i class="bi bi-printer me-1"></i>Print PDF
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('purchasePrintModal');
    if (!modalEl) return;

    const modal = new bootstrap.Modal(modalEl);
    let activePdfUrl = '';

    document.querySelectorAll('.btn-print-purchase').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activePdfUrl = btn.dataset.pdfUrl || '';
            document.getElementById('print_modal_reference').textContent = btn.dataset.reference || '—';
            document.getElementById('print_company_name').value = btn.dataset.defaultName || '';
            document.getElementById('print_company_address').value = btn.dataset.defaultAddress || '';
            document.getElementById('print_company_gstin').value = btn.dataset.defaultGstin || '';
            modal.show();
        });
    });

    document.getElementById('btn_confirm_print')?.addEventListener('click', function () {
        const name = document.getElementById('print_company_name').value.trim();
        if (!name || !activePdfUrl) return;
        const params = new URLSearchParams({
            company_name: name,
            company_address: document.getElementById('print_company_address').value.trim(),
            company_gstin: document.getElementById('print_company_gstin').value.trim(),
        });
        window.open(activePdfUrl + (activePdfUrl.includes('?') ? '&' : '?') + params.toString(), '_blank');
        modal.hide();
    });
})();
</script>
@endpush
@endif
@endsection
