@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Stock In')
@section('content')
@if($selectedPurchase)
@php
    $pendingTotal = $selectedPurchase->items
        ->filter(fn ($item) => $item->isReceivableForStockIn())
        ->sum(fn ($item) => $item->receivableQuantity());
@endphp
<x-admin-page-header title="Stock In — {{ $selectedPurchase->reference_no }}" subtitle="{{ $selectedPurchase->plant->name }} · {{ $selectedPurchase->supplier->name }}">
    <a href="{{ route('ho.stocks.stock-in') }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</x-admin-page-header>

<div class="purchase-create-pr-strip mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="purchase-create-pr-icon"><i class="bi bi-receipt"></i></span>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge bg-primary">{{ $selectedPurchase->reference_no }}</span>
                <x-status-badge :status="$selectedPurchase->status" />
                <span class="badge bg-success-subtle text-success border">
                    Dispatched {{ $selectedPurchase->dispatched_at?->format('d M Y') }}
                </span>
            </div>
            <p class="mb-0 small text-muted">
                {{ $selectedPurchase->items->count() }} product(s) · {{ number_format($pendingTotal, 2) }} pending qty to receive
                · Bill total ₹{{ number_format($selectedPurchase->grand_total, 2) }}
            </p>
        </div>
    </div>
</div>

@if($canEditPurchase ?? false)
<form method="POST" action="{{ route('ho.stocks.stock-in.update-purchase', $selectedPurchase) }}" id="stock-in-po-form" class="mb-4">
    @csrf
    @method('PUT')

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="admin-card admin-table-card mb-0">
                <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit PO &amp; bill</h2>
                    <span class="small text-muted">Add products or update rates before receiving</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 purchase-create-table stock-in-lines">
                        <thead>
                            <tr>
                                <th class="purchase-col-product">Product</th>
                                <th class="purchase-col-num purchase-col-qty">Ordered</th>
                                <th class="purchase-col-num purchase-col-rate">Rate (₹)</th>
                                <th class="purchase-col-num purchase-col-gst">GST%</th>
                                <th class="purchase-col-num purchase-col-qty">Received</th>
                                <th class="purchase-col-num purchase-col-total">Line total</th>
                                <th style="width: 2.5rem"></th>
                            </tr>
                        </thead>
                        <tbody id="stock-in-po-lines">
                            @foreach($selectedPurchase->items as $i => $item)
                                @include('partials.stock-in-po-line-fields', [
                                    'i' => $i,
                                    'item' => $item,
                                    'products' => $products,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-card-body border-top py-2">
                    <button type="button" class="btn btn-sm btn-admin-outline" onclick="addStockInPoLine()">
                        <i class="bi bi-plus-lg me-1"></i>Add product
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-card mb-4">
                <div class="admin-card-header"><h2 class="h6 mb-0">Bill details</h2></div>
                <div class="admin-card-body">
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label small" for="supplier_id">Supplier</label>
                            <select name="supplier_id" id="supplier_id" class="form-select form-select-sm @error('supplier_id') is-invalid @enderror">
                                <option value="">Select Supplier</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}" @selected(old('supplier_id', $selectedPurchase->supplier_id) == $sup->id)>{{ $sup->name }}</option>
                                @endforeach
                            </select>
                            @error('supplier_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label small" for="supplier_invoice_no">Supplier invoice no.</label>
                            <input type="text" name="supplier_invoice_no" id="supplier_invoice_no"
                                value="{{ old('supplier_invoice_no', $selectedPurchase->supplier_invoice_no) }}"
                                class="form-control form-control-sm @error('supplier_invoice_no') is-invalid @enderror"
                                placeholder="Bill / challan number">
                            @error('supplier_invoice_no')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label small" for="supplier_invoice_date">Supplier invoice date</label>
                            <input type="date" name="supplier_invoice_date" id="supplier_invoice_date"
                                value="{{ old('supplier_invoice_date', $selectedPurchase->supplier_invoice_date?->format('Y-m-d')) }}"
                                class="form-control form-control-sm @error('supplier_invoice_date') is-invalid @enderror">
                            @error('supplier_invoice_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="purchase-gst-type-box rounded border bg-light px-3 py-2">
                            <input type="hidden" name="is_interstate" value="0">
                            <div class="form-check mb-0">
                                <input type="checkbox" name="is_interstate" value="1" id="is_interstate" class="form-check-input stock-in-po-input"
                                    @checked(old('is_interstate', $selectedPurchase->is_interstate))>
                                <label class="form-check-label small" for="is_interstate">Inter-state supply (IGST only)</label>
                            </div>
                        </div>
                        <div>
                            <label class="form-label small" for="po_notes">Notes</label>
                            <textarea name="notes" id="po_notes" class="form-control form-control-sm" rows="2" placeholder="Optional">{{ old('notes', $selectedPurchase->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-card purchase-gst-summary-card mb-4">
                <div class="admin-card-header">
                    <h2 class="h6 mb-0"><i class="bi bi-calculator me-2 text-primary"></i>Bill summary</h2>
                </div>
                <div class="admin-card-body pt-2">
                    <table class="table table-sm table-borderless mb-0 purchase-gst-table">
                        <tbody>
                            <tr>
                                <td class="text-muted">Taxable subtotal</td>
                                <td class="text-end fw-semibold">₹<span id="gst-subtotal">0.00</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">CGST</td>
                                <td class="text-end">₹<span id="gst-cgst">0.00</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">SGST</td>
                                <td class="text-end">₹<span id="gst-sgst">0.00</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">IGST</td>
                                <td class="text-end">₹<span id="gst-igst">0.00</span></td>
                            </tr>
                            <tr class="border-top">
                                <td class="fw-bold pt-2">Grand total</td>
                                <td class="text-end fw-bold text-primary fs-5 pt-2">₹<span id="gst-grand">0.00</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="admin-card-body border-top pt-0">
                    <button type="submit" class="btn btn-admin-primary w-100">
                        <i class="bi bi-check2-circle me-1"></i>Save PO &amp; bill
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<template id="stock-in-po-line-tpl">
    @include('partials.stock-in-po-line-fields', [
        'i' => '__INDEX__',
        'item' => null,
        'products' => $products,
    ])
</template>
@endif

<form method="POST" action="{{ route('ho.stocks.stock-in.store') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="purchase_id" value="{{ $selectedPurchase->id }}">

    <div class="admin-card admin-table-card mb-4">
        <div class="admin-card-header">
            <h2 class="h6 mb-0"><i class="bi bi-box-arrow-in-down me-2 text-success"></i>Receive stock</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 purchase-create-table stock-in-lines">
                <thead>
                    <tr>
                        <th class="purchase-col-product">Product</th>
                        <th>Dispatch</th>
                        <th class="purchase-col-num purchase-col-qty">Ordered</th>
                        <th class="purchase-col-num purchase-col-qty">Dispatched</th>
                        <th class="purchase-col-num purchase-col-qty">Received</th>
                        <th class="purchase-col-num purchase-col-rate">Receive qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($selectedPurchase->items as $i => $item)
                    @php
                        $pending = $item->receivableQuantity();
                        $isReceivable = $item->isReceivableForStockIn();
                    @endphp
                    <tr class="{{ $isReceivable ? '' : 'table-light' }}">
                        <td class="purchase-col-product">
                            <span class="fw-semibold d-block text-truncate" title="{{ $item->product->name }}">{{ $item->product->name }}</span>
                            @if($item->product->sku)
                                <code class="small text-muted">{{ $item->product->sku }}</code>
                            @endif
                            @if($item->dispatch_status === \App\Enums\PurchaseItemDispatchStatus::Alternative && $item->originalProduct)
                                <div class="small text-muted">Alt for {{ $item->originalProduct->name }}</div>
                            @endif
                            <input type="hidden" name="lines[{{ $i }}][product_id]" value="{{ $item->product_id }}">
                        </td>
                        <td>
                            @if($item->dispatch_status)
                                <span class="badge {{ $isReceivable ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                    {{ $item->dispatch_status->label() }}
                                </span>
                                @if($item->dispatched_at)
                                    <div class="small text-muted">{{ $item->dispatched_at->format('d M Y') }}</div>
                                @endif
                                @if($item->dispatch_remark)
                                    <div class="small text-muted">{{ $item->dispatch_remark }}</div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="purchase-col-num purchase-col-qty">{{ number_format($item->quantity, 2) }}</td>
                        <td class="purchase-col-num purchase-col-qty">
                            {{ $item->dispatched_quantity !== null ? number_format($item->dispatched_quantity, 2) : '—' }}
                        </td>
                        <td class="purchase-col-num purchase-col-qty">{{ number_format($item->received_quantity, 2) }}</td>
                        <td class="purchase-col-num purchase-col-rate">
                            @if(! $isReceivable)
                                <span class="badge {{ $item->dispatch_status ? 'bg-secondary-subtle text-secondary' : 'bg-warning-subtle text-warning' }}">
                                    {{ $item->dispatch_status ? 'Not receivable' : 'Dispatch pending' }}
                                </span>
                                <input type="hidden" name="lines[{{ $i }}][quantity]" value="0">
                            @elseif($pending > 0)
                                <input type="number" step="0.01" min="0" max="{{ $pending }}" name="lines[{{ $i }}][quantity]"
                                    value="{{ old('lines.'.$i.'.quantity', $pending) }}"
                                    class="form-control form-control-sm purchase-num-input" required>
                            @else
                                <span class="badge bg-success-subtle text-success">Done</span>
                                <input type="hidden" name="lines[{{ $i }}][quantity]" value="0">
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h2 class="h6 mb-0"><i class="bi bi-camera me-2 text-primary"></i>Photo confirmation</h2>
                </div>
                <div class="admin-card-body">
                    <div class="mb-3">
                        <label class="form-label small d-block mb-2">Goods received match PO (photo verified)?</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input type="radio" name="photo_confirmed" value="1" id="photo_confirmed_yes"
                                    class="form-check-input @error('photo_confirmed') is-invalid @enderror"
                                    @checked(old('photo_confirmed', '1') == '1') required>
                                <label class="form-check-label" for="photo_confirmed_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="photo_confirmed" value="0" id="photo_confirmed_no"
                                    class="form-check-input @error('photo_confirmed') is-invalid @enderror"
                                    @checked(old('photo_confirmed') === '0')>
                                <label class="form-check-label" for="photo_confirmed_no">No</label>
                            </div>
                        </div>
                        @error('photo_confirmed')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div id="photo-no-reason-wrap" class="mb-3 @if(old('photo_confirmed', '1') !== '0') d-none @endif">
                        <label class="form-label small" for="reason">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" id="reason" rows="2"
                            class="form-control form-control-sm @error('reason') is-invalid @enderror"
                            placeholder="Why photo confirmation is not available">{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="form-label small" for="photo">Upload delivery photo</label>
                        <input type="file" name="photo" id="photo" accept="image/*" class="form-control form-control-sm @error('photo') is-invalid @enderror">
                        @error('photo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="admin-card mb-4">
                <div class="admin-card-header"><h2 class="h6 mb-0">Notes</h2></div>
                <div class="admin-card-body">
                    <div class="mb-3">
                        <label class="form-label small" for="receive_moved_at">Stock in date</label>
                        <input type="date" name="moved_at" id="receive_moved_at"
                            value="{{ old('moved_at', now()->toDateString()) }}"
                            class="form-control form-control-sm @error('moved_at') is-invalid @enderror" required>
                        @error('moved_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="form-label small" for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control form-control-sm" rows="3" placeholder="Optional">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-box-arrow-in-down me-1"></i>Record stock in
        </button>
        <a href="{{ route('ho.stocks.stock-in') }}" class="btn btn-admin-outline">Cancel</a>
    </div>
</form>

@push('scripts')
@if($canEditPurchase ?? false)
<script>
    function calcPurchaseLine(qty, price, gstRate, isInterstate) {
        const taxable = Math.round(qty * price * 100) / 100;
        const gstAmount = Math.round(taxable * gstRate / 100 * 100) / 100;
        if (isInterstate) {
            return {
                taxable,
                cgst: 0,
                sgst: 0,
                igst: gstAmount,
                total: Math.round((taxable + gstAmount) * 100) / 100
            };
        }
        const half = Math.round(gstAmount / 2 * 100) / 100;
        return {
            taxable,
            cgst: half,
            sgst: half,
            igst: 0,
            total: Math.round((taxable + gstAmount) * 100) / 100
        };
    }

    function recalcStockInPoTotals() {
        const isInterstate = document.getElementById('is_interstate')?.checked ?? false;
        let subtotal = 0, cgst = 0, sgst = 0, igst = 0, grand = 0;

        document.querySelectorAll('#stock-in-po-lines tr').forEach(row => {
            const qty = parseFloat(row.querySelector('[name*="[quantity]"]')?.value) || 0;
            const price = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value) || 0;
            const gstRate = parseFloat(row.querySelector('[name*="[gst_rate]"]')?.value) || 0;
            const line = calcPurchaseLine(qty, price, gstRate, isInterstate);
            subtotal += line.taxable;
            cgst += line.cgst;
            sgst += line.sgst;
            igst += line.igst;
            grand += line.total;

            const totalCell = row.querySelector('.stock-in-line-total');
            if (totalCell) totalCell.textContent = '₹' + line.total.toFixed(2);
        });

        const fmt = n => n.toFixed(2);
        document.getElementById('gst-subtotal').textContent = fmt(subtotal);
        document.getElementById('gst-cgst').textContent = fmt(cgst);
        document.getElementById('gst-sgst').textContent = fmt(sgst);
        document.getElementById('gst-igst').textContent = fmt(igst);
        document.getElementById('gst-grand').textContent = fmt(grand);
    }

    function bindStockInPoCalc() {
        document.querySelectorAll('.stock-in-po-input, #is_interstate').forEach(el => {
            el.removeEventListener('input', recalcStockInPoTotals);
            el.removeEventListener('change', recalcStockInPoTotals);
            el.addEventListener('input', recalcStockInPoTotals);
            el.addEventListener('change', recalcStockInPoTotals);
        });
    }

    function reindexStockInPoLines() {
        document.querySelectorAll('#stock-in-po-lines tr').forEach((row, i) => {
            row.querySelectorAll('[name]').forEach(input => {
                input.name = input.name.replace(/items\[\d+\]/, 'items[' + i + ']');
            });
        });
    }

    function addStockInPoLine() {
        const container = document.getElementById('stock-in-po-lines');
        if (!container) return;
        const index = container.querySelectorAll('tr').length;
        const tpl = document.getElementById('stock-in-po-line-tpl').innerHTML.replace(/__INDEX__/g, index);
        container.insertAdjacentHTML('beforeend', tpl);
        bindStockInPoCalc();
        recalcStockInPoTotals();
    }

    function removeStockInPoLine(button) {
        const row = button.closest('tr');
        const container = document.getElementById('stock-in-po-lines');
        if (!row || !container) return;
        const received = parseFloat(row.dataset.received) || 0;
        if (received > 0) return;
        row.remove();
        reindexStockInPoLines();
        bindStockInPoCalc();
        recalcStockInPoTotals();
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindStockInPoCalc();
        recalcStockInPoTotals();
    });
</script>
@endif
<script>
    (function () {
        const yes = document.getElementById('photo_confirmed_yes');
        const no = document.getElementById('photo_confirmed_no');
        const wrap = document.getElementById('photo-no-reason-wrap');
        const reason = document.getElementById('reason');
        if (!yes || !no || !wrap || !reason) return;

        function syncPhotoReason() {
            const needsReason = no.checked;
            wrap.classList.toggle('d-none', !needsReason);
            reason.required = needsReason;
        }

        yes.addEventListener('change', syncPhotoReason);
        no.addEventListener('change', syncPhotoReason);
        syncPhotoReason();
    })();
</script>
@endpush

@else
<x-admin-page-header title="Stock In" subtitle="Receive from existing POs, or create a PO with bill and record stock in">
    <div class="d-flex gap-2 flex-wrap">
        <x-report-export-button />
        <a href="{{ route('ho.stocks.stock-in.bulk-upload') }}" class="btn btn-admin-outline btn-sm">
            <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Upload
        </a>
        <a href="{{ route('ho.stocks.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
    </div>
</x-admin-page-header>

<form method="POST" action="{{ route('ho.stocks.stock-in.store') }}" enctype="multipart/form-data" id="manual-stock-in-form">
    @csrf
    <input type="hidden" name="manual" value="1">

    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h2 class="h6 mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Manual stock in — PO &amp; bill</h2>
        </div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small" for="manual_plant_id">Plant</label>
                    <select name="plant_id" id="manual_plant_id" class="form-select form-select-sm @error('plant_id') is-invalid @enderror" required>
                        <option value="">Select plant</option>
                        @foreach($plants as $s)
                            <option value="{{ $s->id }}" @selected(old('plant_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @error('plant_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small" for="manual_supplier_id">Supplier</label>
                    <select name="supplier_id" id="manual_supplier_id" class="form-select form-select-sm @error('supplier_id') is-invalid @enderror" required>
                        <option value="">Select supplier</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" @selected(old('supplier_id') == $sup->id)>{{ $sup->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="admin-card admin-table-card mb-4">
                <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0"><i class="bi bi-list-ul me-2 text-primary"></i>PO line items</h2>
                    <span class="small text-muted">Unit price is GST exclusive</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 purchase-create-table stock-in-lines">
                        <thead>
                            <tr>
                                <th class="purchase-col-product">Product</th>
                                <th class="purchase-col-num purchase-col-qty">Qty</th>
                                <th class="purchase-col-num purchase-col-rate">Rate (₹)</th>
                                <th class="purchase-col-num purchase-col-gst">GST%</th>
                                <th class="purchase-col-num purchase-col-qty">Received</th>
                                <th class="purchase-col-num purchase-col-total">Line total</th>
                                <th style="width: 2.5rem"></th>
                            </tr>
                        </thead>
                        <tbody id="manual-stock-in-lines">
                            @php
                                $oldItems = old('items', [['product_id' => '', 'quantity' => 1, 'unit_price' => 0, 'gst_rate' => 18]]);
                            @endphp
                            @foreach($oldItems as $i => $oldItem)
                                @include('partials.stock-in-po-line-fields', [
                                    'i' => $i,
                                    'item' => null,
                                    'products' => $products,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-card-body border-top py-2">
                    <button type="button" class="btn btn-sm btn-admin-outline" onclick="addManualStockInLine()">
                        <i class="bi bi-plus-lg me-1"></i>Add product
                    </button>
                </div>
            </div>

            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h2 class="h6 mb-0"><i class="bi bi-camera me-2 text-primary"></i>Photo confirmation</h2>
                </div>
                <div class="admin-card-body">
                    <div class="mb-3">
                        <label class="form-label small d-block mb-2">Goods received match this bill (photo verified)?</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input type="radio" name="photo_confirmed" value="1" id="photo_confirmed_manual_yes"
                                    class="form-check-input @error('photo_confirmed') is-invalid @enderror"
                                    @checked(old('photo_confirmed', '1') == '1') required>
                                <label class="form-check-label" for="photo_confirmed_manual_yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="photo_confirmed" value="0" id="photo_confirmed_manual_no"
                                    class="form-check-input @error('photo_confirmed') is-invalid @enderror"
                                    @checked(old('photo_confirmed') === '0')>
                                <label class="form-check-label" for="photo_confirmed_manual_no">No</label>
                            </div>
                        </div>
                        @error('photo_confirmed')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div id="manual-photo-no-reason-wrap" class="mb-3 @if(old('photo_confirmed', '1') !== '0') d-none @endif">
                        <label class="form-label small" for="manual_reason">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" id="manual_reason" rows="2"
                            class="form-control form-control-sm @error('reason') is-invalid @enderror"
                            placeholder="Why photo confirmation is not available">{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="form-label small" for="manual_photo">Upload delivery photo</label>
                        <input type="file" name="photo" id="manual_photo" accept="image/*"
                            class="form-control form-control-sm @error('photo') is-invalid @enderror">
                        @error('photo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-card mb-4">
                <div class="admin-card-header"><h2 class="h6 mb-0">Bill details</h2></div>
                <div class="admin-card-body">
                    <div class="vstack gap-3">
                        <div>
                            <label class="form-label small" for="manual_supplier_invoice_no">Supplier invoice no.</label>
                            <input type="text" name="supplier_invoice_no" id="manual_supplier_invoice_no"
                                value="{{ old('supplier_invoice_no') }}"
                                class="form-control form-control-sm @error('supplier_invoice_no') is-invalid @enderror"
                                placeholder="Bill / challan number">
                            @error('supplier_invoice_no')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label small" for="manual_supplier_invoice_date">Supplier invoice date</label>
                            <input type="date" name="supplier_invoice_date" id="manual_supplier_invoice_date"
                                value="{{ old('supplier_invoice_date') }}"
                                class="form-control form-control-sm @error('supplier_invoice_date') is-invalid @enderror">
                            @error('supplier_invoice_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label small" for="manual_moved_at">Stock in date</label>
                            <input type="date" name="moved_at" id="manual_moved_at"
                                value="{{ old('moved_at', now()->toDateString()) }}"
                                class="form-control form-control-sm @error('moved_at') is-invalid @enderror" required>
                            @error('moved_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="purchase-gst-type-box rounded border bg-light px-3 py-2">
                            <input type="hidden" name="is_interstate" value="0">
                            <div class="form-check mb-0">
                                <input type="checkbox" name="is_interstate" value="1" id="manual_is_interstate"
                                    class="form-check-input stock-in-po-input" @checked(old('is_interstate'))>
                                <label class="form-check-label small" for="manual_is_interstate">Inter-state supply (IGST only)</label>
                            </div>
                        </div>
                        <div>
                            <label class="form-label small" for="manual_notes">Notes</label>
                            <textarea name="notes" id="manual_notes" class="form-control form-control-sm" rows="2"
                                placeholder="Optional">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-card purchase-gst-summary-card mb-4">
                <div class="admin-card-header">
                    <h2 class="h6 mb-0"><i class="bi bi-calculator me-2 text-primary"></i>Bill summary</h2>
                </div>
                <div class="admin-card-body pt-2">
                    <table class="table table-sm table-borderless mb-0 purchase-gst-table">
                        <tbody>
                            <tr>
                                <td class="text-muted">Taxable subtotal</td>
                                <td class="text-end fw-semibold">₹<span id="manual-gst-subtotal">0.00</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">CGST</td>
                                <td class="text-end">₹<span id="manual-gst-cgst">0.00</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">SGST</td>
                                <td class="text-end">₹<span id="manual-gst-sgst">0.00</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">IGST</td>
                                <td class="text-end">₹<span id="manual-gst-igst">0.00</span></td>
                            </tr>
                            <tr class="border-top">
                                <td class="fw-bold pt-2">Grand total</td>
                                <td class="text-end fw-bold text-primary fs-5 pt-2">₹<span id="manual-gst-grand">0.00</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="admin-card-body border-top pt-0">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-box-arrow-in-down me-1"></i>Create PO &amp; record stock in
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<template id="manual-stock-in-line-tpl">
    @include('partials.stock-in-po-line-fields', [
        'i' => '__INDEX__',
        'item' => null,
        'products' => $products,
    ])
</template>

<div class="admin-card admin-table-card mb-4">
    <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0">Receive from purchase order</h2>
        <span class="small text-muted">{{ $purchases->count() }} dispatched &amp; pending receive</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>PO Ref</th>
                    <th>Plant</th>
                    <th>Supplier</th>
                    <th class="text-end">Items</th>
                    <th class="text-end">Pending qty</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $po)
                @php
                    $pendingQty = $po->items->sum(fn ($item) => max(0, (float) $item->quantity - (float) $item->received_quantity));
                @endphp
                <tr>
                    <td><code class="small">{{ $po->reference_no }}</code></td>
                    <td>{{ $po->plant->name }}</td>
                    <td>{{ $po->supplier->name }}</td>
                    <td class="text-end">{{ $po->items->count() }}</td>
                    <td class="text-end fw-semibold">{{ number_format($pendingQty, 2) }}</td>
                    <td><x-status-badge :status="$po->status" /></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('ho.stocks.stock-in', ['purchase_id' => $po->id]) }}" class="btn btn-sm btn-success me-1">
                            <i class="bi bi-box-arrow-in-down me-1"></i>Record in stock
                        </a>
                        <a href="{{ route('ho.purchases.show', $po) }}" class="btn btn-sm btn-admin-outline">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="admin-empty py-4">
                            <i class="bi bi-receipt d-block"></i>
                            No dispatched purchase orders pending stock in
                            <div class="small text-muted mt-1">Purchaser must submit product-wise dispatch first</div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<x-report-filters
    :plants="$plants"
    :show-dates="true"
    search-placeholder="Product, SKU, plant, supplier..."
    :clear-route="route('ho.stocks.stock-in')"
/>

@include('partials.stock-movement-list', [
    'type' => 'in',
    'movements' => $movements,
    'purchaseReferences' => $purchaseReferences,
])

@push('scripts')
<script>
    function calcManualPurchaseLine(qty, price, gstRate, isInterstate) {
        const taxable = Math.round(qty * price * 100) / 100;
        const gstAmount = Math.round(taxable * gstRate / 100 * 100) / 100;
        if (isInterstate) {
            return {
                taxable,
                cgst: 0,
                sgst: 0,
                igst: gstAmount,
                total: Math.round((taxable + gstAmount) * 100) / 100
            };
        }
        const half = Math.round(gstAmount / 2 * 100) / 100;
        return {
            taxable,
            cgst: half,
            sgst: half,
            igst: 0,
            total: Math.round((taxable + gstAmount) * 100) / 100
        };
    }

    function recalcManualStockInTotals() {
        const isInterstate = document.getElementById('manual_is_interstate')?.checked ?? false;
        let subtotal = 0, cgst = 0, sgst = 0, igst = 0, grand = 0;

        document.querySelectorAll('#manual-stock-in-lines tr').forEach(row => {
            const qty = parseFloat(row.querySelector('[name*="[quantity]"]')?.value) || 0;
            const price = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value) || 0;
            const gstRate = parseFloat(row.querySelector('[name*="[gst_rate]"]')?.value) || 0;
            const line = calcManualPurchaseLine(qty, price, gstRate, isInterstate);
            subtotal += line.taxable;
            cgst += line.cgst;
            sgst += line.sgst;
            igst += line.igst;
            grand += line.total;

            const totalCell = row.querySelector('.stock-in-line-total');
            if (totalCell) totalCell.textContent = '₹' + line.total.toFixed(2);
        });

        const fmt = n => n.toFixed(2);
        document.getElementById('manual-gst-subtotal').textContent = fmt(subtotal);
        document.getElementById('manual-gst-cgst').textContent = fmt(cgst);
        document.getElementById('manual-gst-sgst').textContent = fmt(sgst);
        document.getElementById('manual-gst-igst').textContent = fmt(igst);
        document.getElementById('manual-gst-grand').textContent = fmt(grand);
    }

    function bindManualStockInCalc() {
        document.querySelectorAll('#manual-stock-in-lines .stock-in-po-input, #manual_is_interstate').forEach(el => {
            el.removeEventListener('input', recalcManualStockInTotals);
            el.removeEventListener('change', recalcManualStockInTotals);
            el.addEventListener('input', recalcManualStockInTotals);
            el.addEventListener('change', recalcManualStockInTotals);
        });
    }

    function reindexManualStockInLines() {
        document.querySelectorAll('#manual-stock-in-lines tr').forEach((row, i) => {
            row.querySelectorAll('[name]').forEach(input => {
                input.name = input.name.replace(/items\[\d+\]/, 'items[' + i + ']');
            });
        });
    }

    function addManualStockInLine() {
        const container = document.getElementById('manual-stock-in-lines');
        if (!container) return;
        const index = container.querySelectorAll('tr').length;
        const tpl = document.getElementById('manual-stock-in-line-tpl').innerHTML.replace(/__INDEX__/g, index);
        container.insertAdjacentHTML('beforeend', tpl);
        bindManualStockInCalc();
        recalcManualStockInTotals();
    }

    function removeStockInPoLine(button) {
        const row = button.closest('tr');
        const container = row?.closest('tbody');
        if (!row || !container) return;
        if (container.id === 'manual-stock-in-lines' && container.querySelectorAll('tr').length <= 1) {
            return;
        }
        const received = parseFloat(row.dataset.received) || 0;
        if (received > 0) return;
        row.remove();
        if (container.id === 'manual-stock-in-lines') {
            reindexManualStockInLines();
            bindManualStockInCalc();
            recalcManualStockInTotals();
        } else {
            reindexStockInPoLines();
            bindStockInPoCalc();
            recalcStockInPoTotals();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindManualStockInCalc();
        recalcManualStockInTotals();

        const yes = document.getElementById('photo_confirmed_manual_yes');
        const no = document.getElementById('photo_confirmed_manual_no');
        const wrap = document.getElementById('manual-photo-no-reason-wrap');
        const reason = document.getElementById('manual_reason');
        if (yes && no && wrap && reason) {
            function syncManualPhotoReason() {
                const needsReason = no.checked;
                wrap.classList.toggle('d-none', !needsReason);
                reason.required = needsReason;
            }
            yes.addEventListener('change', syncManualPhotoReason);
            no.addEventListener('change', syncManualPhotoReason);
            syncManualPhotoReason();
        }
    });
</script>
@endpush
@endif
@endsection
