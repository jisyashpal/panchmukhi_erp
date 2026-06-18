@extends('layouts.ho')
@section('title', 'New Purchase')
@section('content')
<x-admin-page-header title="New Purchase Order" subtitle="Supplier, unit price &amp; GST — saved under Purchases list">
    <a href="{{ route('ho.purchases.index') }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Purchases</a>
</x-admin-page-header>

@if($purchaseRequest)
<div class="admin-card mb-4 border-primary border-2">
    <div class="admin-card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-start gap-3">
                <span class="purchase-pr-badge rounded d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="badge bg-primary mb-1">Purchase Request #{{ $purchaseRequest->id }}</span>
                    <h2 class="h6 fw-bold mb-1">{{ $purchaseRequest->site->name }}</h2>
                    <p class="small text-muted mb-0">{{ $purchaseRequest->items->count() }} line item(s) pre-filled — enter supplier, rates and GST.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@elseif($purchaseRequests->isNotEmpty())
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h2 class="h6 mb-0"><i class="bi bi-link-45deg me-2 text-primary"></i>Link to purchase request (optional)</h2>
    </div>
    <div class="admin-card-body">
        <form method="GET" action="{{ route('ho.purchases.create') }}" class="row g-3 align-items-end">
            <div class="col-lg-8">
                <label class="form-label" for="purchase_request_id">Open request without PO</label>
                <select name="purchase_request_id" id="purchase_request_id" class="form-select" onchange="this.form.submit()">
                    <option value="">— Manual purchase (no request) —</option>
                    @foreach($purchaseRequests as $pr)
                    <option value="{{ $pr->id }}" @selected(request('purchase_request_id')==$pr->id)>
                        PR #{{ $pr->id }} — {{ $pr->site->name }} ({{ $pr->items->count() }} items)
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4">
                <span class="form-text d-block mb-1">{{ $purchaseRequests->count() }} request(s) available</span>
            </div>
        </form>
    </div>
</div>
@endif

<form method="POST" action="{{ route('ho.purchases.store') }}" id="purchase-form">
    @csrf
    @if($purchaseRequest)
    <input type="hidden" name="purchase_request_id" value="{{ $purchaseRequest->id }}">
    <input type="hidden" name="site_id" value="{{ $purchaseRequest->site_id }}">
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h2 class="h6 mb-0">Order details</h2>
                </div>
                <div class="admin-card-body">
                    <div class="row g-3">
                        @if(!$purchaseRequest)
                        <x-admin-form-group label="Site / Plant" name="site_id" required col="col-md-6 col-lg-4">
                            <select name="site_id" id="site_id" class="form-select" required>
                                @foreach($sites as $s)
                                <option value="{{ $s->id }}" @selected(old('site_id')==$s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </x-admin-form-group>
                        @else
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label">Site / Plant</label>
                            <input type="text" class="form-control bg-light" value="{{ $purchaseRequest->site->name }}" disabled readonly>
                        </div>
                        @endif

                        <x-admin-form-group label="Supplier" name="supplier_id" required col="col-md-6 col-lg-4">
                            <select name="supplier_id" id="supplier_id" class="form-select" required>
                                <option value="">Select supplier</option>
                                @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" @selected(old('supplier_id')==$s->id)">
                                    {{ $s->name }}@if($s->gstin) — {{ $s->gstin }}@endif
                                </option>
                                @endforeach
                            </select>
                        </x-admin-form-group>

                        <x-admin-form-input label="PO reference (optional)" name="reference_no" col="col-md-6 col-lg-4" placeholder="Auto-generated if empty" />

                        <x-admin-form-input label="Supplier invoice no." name="supplier_invoice_no" col="col-md-6 col-lg-4" />

                        <x-admin-form-group label="Supplier invoice date" name="supplier_invoice_date" col="col-md-6 col-lg-4">
                            <input type="date" name="supplier_invoice_date" id="supplier_invoice_date" value="{{ old('supplier_invoice_date') }}" class="form-control">
                        </x-admin-form-group>

                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="is_interstate">GST type</label>
                            <div class="purchase-gst-type-box rounded border bg-light px-3 py-2 h-100 d-flex align-items-center">
                                <input type="hidden" name="is_interstate" value="0">
                                <div class="form-check mb-0">
                                    <input type="checkbox" name="is_interstate" value="1" id="is_interstate" class="form-check-input purchase-gst-input"
                                        @checked(old('is_interstate'))>
                                    <label class="form-check-label" for="is_interstate">Inter-state supply (IGST only)</label>
                                </div>
                            </div>
                        </div>

                        <x-admin-form-group label="Notes" name="notes" col="col-12">
                            <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Optional notes for this purchase order">{{ old('notes') }}</textarea>
                        </x-admin-form-group>
                    </div>
                </div>
            </div>

            <div class="admin-card mb-4">
                <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0">Line items</h2>
                    <span class="small text-muted">Unit price is GST exclusive</span>
                </div>
                <div class="admin-card-body pt-0">
                   
                    <div id="purchase-lines" class="purchase-lines">
                        @if($purchaseRequest)
                        @foreach($purchaseRequest->items as $i => $item)
                        <div class="dynamic-row row g-2 g-lg-3 mb-2 mb-lg-2 align-items-end purchase-line-card">
                            @include('partials.purchase-line-fields', [
                            'i' => $i,
                            'products' => $products,
                            'productId' => $item->product_id,
                            'quantity' => $item->quantity,
                            'unitPrice' => old('items.'.$i.'.unit_price', 0),
                            'gstRate' => old('items.'.$i.'.gst_rate', 18),
                            'showRemove' => $purchaseRequest->items->count() > 1,
                            ])
                        </div>
                        @endforeach
                        @else
                        <div class="dynamic-row row g-2 g-lg-3 mb-2 mb-lg-2 align-items-end purchase-line-card">
                            @include('partials.purchase-line-fields', ['i' => 0, 'products' => $products])
                        </div>
                        @endif
                    </div>
                    @if(!$purchaseRequest)
                    <button type="button" class="btn btn-sm btn-admin-outline mt-2" onclick="addPurchaseLine()">
                        <i class="bi bi-plus-lg me-1"></i>Add line
                    </button>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 d-xl-none mb-4">
                <button type="submit" class="btn btn-admin-primary">
                    <i class="bi bi-check2-circle me-1"></i>Create purchase order
                </button>
                <a href="{{ route('ho.purchases.index') }}" class="btn btn-admin-outline">Cancel</a>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="admin-card purchase-gst-summary-card sticky-top">
                <div class="admin-card-header">
                    <h2 class="h6 mb-0"><i class="bi bi-calculator me-2 text-primary"></i>GST summary</h2>
                </div>
                <div class="admin-card-body">
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
                                <td class="text-end fw-bold text-primary fs-5">₹<span id="gst-grand">0.00</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0 mt-3">
                        <i class="bi bi-info-circle me-1"></i>Totals update as you enter qty, price and GST on each line.
                    </p>
                </div>
                <div class="admin-card-body border-top pt-0 d-none d-xl-block">
                    <button type="submit" class="btn btn-admin-primary w-100 mb-2">
                        <i class="bi bi-check2-circle me-1"></i>Create purchase order
                    </button>
                    <a href="{{ route('ho.purchases.index') }}" class="btn btn-admin-outline w-100">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<template id="purchase-line-tpl">
    @include('partials.purchase-line-fields', ['i' => '__INDEX__', 'products' => $products])
</template>

@push('scripts')
<script>
    function addPurchaseLine() {
        addDynamicRow('purchase-lines', document.getElementById('purchase-line-tpl').innerHTML);
        const row = document.querySelector('#purchase-lines .dynamic-row:last-child');
        if (row && !row.classList.contains('purchase-line-card')) {
            row.classList.add('purchase-line-card');
        }
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
        let subtotal = 0,
            cgst = 0,
            sgst = 0,
            igst = 0,
            grand = 0;
        document.querySelectorAll('#purchase-lines .dynamic-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name*="[quantity]"]')?.value) || 0;
            const price = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value) || 0;
            const gstRate = parseFloat(row.querySelector('[name*="[gst_rate]"]')?.value) || 0;
            const line = calcPurchaseLine(qty, price, gstRate, isInterstate);
            subtotal += line.taxable;
            cgst += line.cgst;
            sgst += line.sgst;
            igst += line.igst;
            grand += line.total;
        });
        const fmt = n => n.toFixed(2);
        document.getElementById('gst-subtotal').textContent = fmt(subtotal);
        document.getElementById('gst-cgst').textContent = fmt(cgst);
        document.getElementById('gst-sgst').textContent = fmt(sgst);
        document.getElementById('gst-igst').textContent = fmt(igst);
        document.getElementById('gst-grand').textContent = fmt(grand);
    }

    function bindPurchaseGstCalc() {
        document.querySelectorAll('.purchase-line-input, #is_interstate').forEach(el => {
            el.removeEventListener('input', recalcPurchaseTotals);
            el.removeEventListener('change', recalcPurchaseTotals);
            el.addEventListener('input', recalcPurchaseTotals);
            el.addEventListener('change', recalcPurchaseTotals);
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        bindPurchaseGstCalc();
        recalcPurchaseTotals();
    });
</script>
@endpush
@endsection