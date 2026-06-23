@extends('layouts.ho')
@section('title', 'Stock In')
@section('content')
@if($selectedPurchase)
@php
    $pendingTotal = $selectedPurchase->items->sum(fn ($item) => max(0, (float) $item->quantity - (float) $item->received_quantity));
@endphp
<x-admin-page-header title="Stock In — {{ $selectedPurchase->reference_no }}" subtitle="{{ $selectedPurchase->site->name }} · {{ $selectedPurchase->supplier->name }}">
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
                    <table class="table table-sm mb-0 purchase-create-table">
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
            <table class="table table-sm mb-0 purchase-create-table">
                <thead>
                    <tr>
                        <th class="purchase-col-product">Product</th>
                        <th class="purchase-col-num purchase-col-qty">Ordered</th>
                        <th class="purchase-col-num purchase-col-qty">Received</th>
                        <th class="purchase-col-num purchase-col-rate">Receive qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($selectedPurchase->items as $i => $item)
                    @php
                        $pending = max(0, (float) $item->quantity - (float) $item->received_quantity);
                    @endphp
                    <tr>
                        <td class="purchase-col-product">
                            <span class="fw-semibold d-block text-truncate" title="{{ $item->product->name }}">{{ $item->product->name }}</span>
                            @if($item->product->sku)
                                <code class="small text-muted">{{ $item->product->sku }}</code>
                            @endif
                            <input type="hidden" name="lines[{{ $i }}][product_id]" value="{{ $item->product_id }}">
                        </td>
                        <td class="purchase-col-num purchase-col-qty">{{ number_format($item->quantity, 2) }}</td>
                        <td class="purchase-col-num purchase-col-qty">{{ number_format($item->received_quantity, 2) }}</td>
                        <td class="purchase-col-num purchase-col-rate">
                            @if($pending > 0)
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
                    <div class="form-check mb-3">
                        <input type="checkbox" name="photo_confirmed" value="1" id="photo_confirmed" class="form-check-input @error('photo_confirmed') is-invalid @enderror" @checked(old('photo_confirmed')) required>
                        <label class="form-check-label" for="photo_confirmed">
                            I confirm goods received match the purchase order (photo verified)
                        </label>
                        @error('photo_confirmed')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
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
                        <label class="form-label small" for="reason">Reason / reference</label>
                        <input type="text" name="reason" id="reason" value="{{ old('reason') }}" class="form-control form-control-sm" placeholder="e.g. Gate delivery challan #">
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
@endpush

@else
<x-admin-page-header title="Stock In" subtitle="Receive goods from purchase orders or record manual stock in">
    <a href="{{ route('ho.stocks.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
</x-admin-page-header>

<div class="admin-card admin-table-card mb-4">
    <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0">Receive from purchase order</h2>
        <span class="small text-muted">{{ $purchases->count() }} pending</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>PO Ref</th>
                    <th>Site</th>
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
                    <td>{{ $po->site->name }}</td>
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
                            No purchase orders pending stock in
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="admin-card-header"><h2 class="h6 mb-0">Manual stock in</h2></div>
</div>

<form method="POST" action="{{ route('ho.stocks.stock-in.store') }}" enctype="multipart/form-data" class="admin-card admin-card-body">
    @csrf
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Site</label>
            <select name="site_id" class="form-select" required>
                @foreach($sites as $s)
                <option value="{{ $s->id }}" @selected(old('site_id')==$s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select" required>
                @foreach($products as $p)
                <option value="{{ $p->id }}" @selected(old('product_id')==$p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Quantity</label>
            <input type="number" step="0.01" name="quantity" value="{{ old('quantity') }}" class="form-control" required>
        </div>
        <div class="col-md-6">
            <div class="form-check mt-2">
                <input type="checkbox" name="photo_confirmed" value="1" id="photo_confirmed_manual" class="form-check-input" @checked(old('photo_confirmed'))>
                <label class="form-check-label" for="photo_confirmed_manual">Photo verified</label>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Photo upload</label>
            <input type="file" name="photo" accept="image/*" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" value="{{ old('reason') }}" class="form-control">
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>
    </div>
    <button class="btn btn-primary mt-3">Record Stock In</button>
</form>
@endif
@endsection
