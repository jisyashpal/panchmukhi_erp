@extends('layouts.plant')
@section('title', $canReceive ? 'Receive Stock' : 'Purchases')
@section('content')
<x-admin-page-header
    :title="$canReceive ? 'Receive Stock (PO)' : 'Purchases'"
    :subtitle="$canReceive ? 'Confirm delivery and record stock in' : ($isPurchaser ? 'View and print purchase orders' : 'View purchase orders for this plant')"
/>

<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label class="form-label small">Search PO / Supplier / Product</label>
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
            @if(request()->anyFilled(['search', 'from_date', 'to_date']))
                <a href="{{ route('plant.purchases.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Supplier</th>
                    <th>Products</th>
                    <th>Order Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $purchase)
                @php
                    $productNames = $purchase->items->map(fn ($item) => $item->product?->name)->filter()->unique()->values();
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $purchase->reference_no }}</td>
                    <td>{{ $purchase->supplier->name }}</td>
                    <td class="small text-muted">
                        @if($productNames->isNotEmpty())
                            {{ $productNames->take(3)->implode(', ') }}
                            @if($productNames->count() > 3)
                                <span class="text-muted">+{{ $productNames->count() - 3 }} more</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="small text-muted">{{ $purchase->ordered_at?->format('d M Y') ?? '—' }}</td>
                    <td><span class="badge bg-secondary">{{ $purchase->status->label() }}</span></td>
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
                            @if(in_array($purchase->status->value, ['ordered', 'partial']))
                                <a href="{{ route('plant.purchases.edit', $purchase) }}" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                            @endif
                        @endif
                        @if($canReceive)
                            <a href="{{ route('plant.purchases.receive', $purchase) }}" class="btn btn-sm btn-success">Receive</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="admin-empty">
                            <i class="bi bi-truck d-block"></i>
                            {{ $canReceive ? 'No purchases awaiting receipt' : 'No purchases found' }}
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $purchases->links() }}</div>

@if($isPurchaser)
<div class="modal fade" id="purchasePrintModal" tabindex="-1" aria-labelledby="purchasePrintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="purchasePrintModalLabel">Print purchase order</h5>
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
        const companyName = document.getElementById('print_company_name').value.trim();
        if (!companyName || !activePdfUrl) {
            document.getElementById('print_company_name').focus();
            return;
        }

        const params = new URLSearchParams({
            company_name: companyName,
            company_address: document.getElementById('print_company_address').value,
            company_gstin: document.getElementById('print_company_gstin').value,
        });

        window.open(`${activePdfUrl}?${params.toString()}`, '_blank');
        modal.hide();
    });
})();
</script>
@endpush
@endif
@endsection
