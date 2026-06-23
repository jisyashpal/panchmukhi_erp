@extends('layouts.site')
@section('title', 'New Purchase')
@section('content')
@php
    $pageTitle = $purchaseRequest
        ? 'Create Purchase Order'
        : 'New Purchase Order';
    $pageSubtitle = $purchaseRequest
        ? 'Purchase Request #'.$purchaseRequest->id.' · '.$purchaseRequest->site->name
        : 'Enter supplier, rate, quantity & GST per product';
    $supplierNames = $suppliers->pluck('name', 'id');
@endphp
<x-admin-page-header :title="$pageTitle" :subtitle="$pageSubtitle">
    <a href="{{ route('site.purchases.index') }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</x-admin-page-header>

@if(!$purchaseRequest && $purchaseRequests->isNotEmpty())
<div class="admin-card mb-4">
    <div class="admin-card-body py-3">
        <form method="GET" action="{{ route('site.purchases.create') }}" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label small mb-1" for="purchase_request_id">Link to purchase request</label>
                <select name="purchase_request_id" id="purchase_request_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">— Manual purchase —</option>
                    @foreach($purchaseRequests as $pr)
                    <option value="{{ $pr->id }}" @selected(request('purchase_request_id')==$pr->id)>
                        PR #{{ $pr->id }} — {{ $pr->site->name }} ({{ $pr->items->count() }} items)
                    </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>
@endif

@if($purchaseRequest)
<div class="purchase-create-pr-strip mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="purchase-create-pr-icon"><i class="bi bi-file-earmark-text"></i></span>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge bg-primary">PR #{{ $purchaseRequest->id }}</span>
                @if($purchaseRequest->product_request_id)
                    <span class="badge bg-light text-dark border">From product request</span>
                @endif
            </div>
            <p class="mb-0 small text-muted">
                {{ $purchaseRequest->items->count() }} product(s) · set supplier &amp; billing per line
            </p>
        </div>
        <div class="purchase-create-po-hint text-end" id="po-split-hint">
            <span class="d-block small text-muted">Purchase orders</span>
            <strong class="text-primary" id="po-split-count">—</strong>
        </div>
    </div>
</div>
@endif

<form method="POST" action="{{ route('site.purchases.store') }}" id="purchase-form">
    @csrf
    @if($purchaseRequest)
    <input type="hidden" name="purchase_request_id" value="{{ $purchaseRequest->id }}">
    <input type="hidden" name="site_id" value="{{ $purchaseRequest->site_id }}">
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="admin-card admin-table-card mb-0">
                <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0">Line items</h2>
                    <span class="small text-muted">Unit price is GST exclusive</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 purchase-create-table">
                        <thead>
                            <tr>
                                <th class="purchase-col-product">Product</th>
                                <th class="purchase-col-supplier">Supplier</th>
                                <th class="purchase-col-num purchase-col-qty">Qty</th>
                                <th class="purchase-col-num purchase-col-rate">Rate (₹)</th>
                                <th class="purchase-col-num purchase-col-gst">GST%</th>
                                <th class="purchase-col-num purchase-col-total">Line total</th>
                                @if(!$purchaseRequest)
                                <th style="width: 2.5rem"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody id="purchase-lines">
                            @if($purchaseRequest)
                            @foreach($purchaseRequest->items as $i => $item)
                            @php
                                $prItem = $purchaseRequest->productRequest
                                    ? $purchaseRequest->productRequest->items->firstWhere('product_id', $item->product_id)
                                    : null;
                                $defaultGstRate = $prItem ? $prItem->gst_rate : 18;
                                $defaultUnitPrice = $item->unit_price > 0 ? $item->unit_price : ($prItem ? $prItem->unit_price : 0);
                                $defaultSupplierId = $item->supplier_id ?? ($prItem?->supplier_id);
                            @endphp
                            <tr class="purchase-line-row">
                                <td class="purchase-col-product">
                                    <span class="fw-semibold d-block text-truncate" title="{{ $item->product->name }}">{{ $item->product->name }}</span>
                                    @if($item->product->sku)
                                        <code class="small text-muted">{{ $item->product->sku }}</code>
                                    @endif
                                    <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $item->product_id }}">
                                </td>
                                <td class="purchase-col-supplier">
                                    <select name="items[{{ $i }}][supplier_id]" class="form-select form-select-sm purchase-line-input purchase-supplier-select" required>
                                        <option value="">— Select —</option>
                                        @foreach($suppliers as $s)
                                        <option value="{{ $s->id }}" @selected(old("items.$i.supplier_id", $defaultSupplierId)==$s->id)>{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="purchase-col-num purchase-col-qty">
                                    <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", $item->quantity) }}" class="form-control form-control-sm purchase-line-input purchase-num-input" required>
                                </td>
                                <td class="purchase-col-num purchase-col-rate">
                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" value="{{ old("items.$i.unit_price", $defaultUnitPrice) }}" class="form-control form-control-sm purchase-line-input purchase-num-input" required>
                                </td>
                                <td class="purchase-col-num purchase-col-gst">
                                    <input type="number" step="0.01" min="0" max="100" name="items[{{ $i }}][gst_rate]" value="{{ old("items.$i.gst_rate", $defaultGstRate) }}" class="form-control form-control-sm purchase-line-input purchase-num-input">
                                </td>
                                <td class="purchase-col-num purchase-col-total purchase-line-total-cell">
                                    <span class="purchase-line-total">₹0.00</span>
                                </td>
                            </tr>
                            @endforeach
                            @else
                            @include('partials.purchase-line-fields', [
                                'i' => 0,
                                'products' => $products,
                                'suppliers' => $suppliers,
                                'showRemove' => true,
                                'showLineTotal' => true,
                            ])
                            @endif
                        </tbody>
                    </table>
                </div>
                @if(!$purchaseRequest)
                <div class="admin-card-body border-top py-2">
                    <button type="button" class="btn btn-sm btn-admin-outline" onclick="addPurchaseLine()">
                        <i class="bi bi-plus-lg me-1"></i>Add line
                    </button>
                </div>
                @endif
            </div>

            @if($purchaseRequest)
            <p class="small text-muted mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Different suppliers on separate lines will create separate purchase orders automatically.
            </p>
            @endif

            <div class="d-flex flex-wrap gap-2 mt-4 d-lg-none">
                <button type="submit" class="btn btn-admin-primary">
                    <i class="bi bi-check2-circle me-1"></i>Create purchase order
                </button>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-card mb-4">
                <div class="admin-card-header"><h2 class="h6 mb-0">Order details</h2></div>
                <div class="admin-card-body">
                    <div class="vstack gap-3">
                        @if(!$purchaseRequest)
                        <div>
                            <label class="form-label small" for="site_id">Site / Plant</label>
                            <select name="site_id" id="site_id" class="form-select form-select-sm" required>
                                @foreach($sites as $s)
                                <option value="{{ $s->id }}" @selected(old('site_id')==$s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <div>
                            <span class="form-label small text-muted d-block">Site / Plant</span>
                            <span class="fw-semibold">{{ $purchaseRequest->site->name }}</span>
                        </div>
                        @endif

                        <div>
                            <label class="form-label small" for="reference_no">PO reference</label>
                            <input type="text" name="reference_no" id="reference_no" value="{{ old('reference_no') }}" class="form-control form-control-sm" placeholder="Auto if empty">
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small" for="supplier_invoice_no">Quotation no.</label>
                                <input type="text" name="supplier_invoice_no" id="supplier_invoice_no" value="{{ old('supplier_invoice_no') }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <label class="form-label small" for="supplier_invoice_date">Invoice date</label>
                                <input type="date" name="supplier_invoice_date" id="supplier_invoice_date" value="{{ old('supplier_invoice_date') }}" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="purchase-gst-type-box rounded border bg-light px-3 py-2">
                            <input type="hidden" name="is_interstate" value="0">
                            <div class="form-check mb-0">
                                <input type="checkbox" name="is_interstate" value="1" id="is_interstate" class="form-check-input purchase-line-input" @checked(old('is_interstate'))>
                                <label class="form-check-label small" for="is_interstate">Inter-state supply (IGST only)</label>
                            </div>
                        </div>

                        <div>
                            <label class="form-label small" for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control form-control-sm" rows="2" placeholder="Optional">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-card purchase-gst-summary-card mb-4">
                <div class="admin-card-header">
                    <h2 class="h6 mb-0"><i class="bi bi-calculator me-2 text-primary"></i>Amount summary</h2>
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
                            <tr class="purchase-gst-grand">
                                <td class="fw-bold">Grand total</td>
                                <td class="text-end fw-bold text-primary">₹<span id="gst-grand">0.00</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="admin-card-body border-top pt-0 d-none d-lg-block">
                    <button type="submit" class="btn btn-admin-primary w-100 mb-2">
                        <i class="bi bi-check2-circle me-1"></i>Create purchase order
                    </button>
                    <a href="{{ route('site.purchases.index') }}" class="btn btn-admin-outline w-100 btn-sm">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<template id="purchase-line-tpl">
    @include('partials.purchase-line-fields', [
        'i' => '__INDEX__',
        'products' => $products,
        'suppliers' => $suppliers,
        'showLineTotal' => true,
    ])
</template>

@push('scripts')
<script>
    const supplierNames = @json($supplierNames);

    function addPurchaseLine() {
        const container = document.getElementById('purchase-lines');
        if (!container) return;
        const index = container.querySelectorAll('.purchase-line-row, .dynamic-row').length;
        const tpl = document.getElementById('purchase-line-tpl').innerHTML.replace(/__INDEX__/g, index);
        container.insertAdjacentHTML('beforeend', tpl);
        bindPurchaseGstCalc();
        recalcPurchaseTotals();
    }

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

    function recalcPurchaseTotals() {
        const isInterstate = document.getElementById('is_interstate')?.checked ?? false;
        let subtotal = 0, cgst = 0, sgst = 0, igst = 0, grand = 0;
        const supplierIds = new Set();

        document.querySelectorAll('#purchase-lines tr').forEach(row => {
            if (!row.querySelector('[name*="[quantity]"]')) return;
            const qty = parseFloat(row.querySelector('[name*="[quantity]"]')?.value) || 0;
            const price = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value) || 0;
            const gstRate = parseFloat(row.querySelector('[name*="[gst_rate]"]')?.value) || 0;
            const supplierId = row.querySelector('[name*="[supplier_id]"]')?.value;
            if (supplierId) supplierIds.add(supplierId);

            const line = calcPurchaseLine(qty, price, gstRate, isInterstate);
            subtotal += line.taxable;
            cgst += line.cgst;
            sgst += line.sgst;
            igst += line.igst;
            grand += line.total;

            const totalCell = row.querySelector('.purchase-line-total');
            if (totalCell) totalCell.textContent = '₹' + line.total.toFixed(2);
        });

        const fmt = n => n.toFixed(2);
        document.getElementById('gst-subtotal').textContent = fmt(subtotal);
        document.getElementById('gst-cgst').textContent = fmt(cgst);
        document.getElementById('gst-sgst').textContent = fmt(sgst);
        document.getElementById('gst-igst').textContent = fmt(igst);
        document.getElementById('gst-grand').textContent = fmt(grand);

        const poHint = document.getElementById('po-split-count');
        if (poHint) {
            const count = supplierIds.size;
            if (count === 0) {
                poHint.textContent = '—';
            } else if (count === 1) {
                poHint.textContent = '1 PO';
            } else {
                poHint.textContent = count + ' POs';
            }
        }
    }

    function bindPurchaseGstCalc() {
        document.querySelectorAll('.purchase-line-input, #is_interstate').forEach(el => {
            el.removeEventListener('input', recalcPurchaseTotals);
            el.removeEventListener('change', recalcPurchaseTotals);
            el.addEventListener('input', recalcPurchaseTotals);
            el.addEventListener('change', recalcPurchaseTotals);
        });
    }

    function removeDynamicRow(button) {
        const row = button.closest('tr');
        const container = row?.parentElement;
        const rows = container?.querySelectorAll('.purchase-line-row, .dynamic-row') ?? [];
        if (row && container && rows.length > 1) {
            row.remove();
            container.querySelectorAll('.purchase-line-row, .dynamic-row').forEach((el, i) => {
                el.querySelectorAll('[name]').forEach(input => {
                    input.name = input.name.replace(/\[\d+\]/, '[' + i + ']');
                });
            });
            recalcPurchaseTotals();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindPurchaseGstCalc();
        recalcPurchaseTotals();
    });
</script>
@endpush
@endsection
