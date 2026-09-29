@extends('layouts.plant')
@section('title', 'New Purchase')
@section('content')
@php
    $documentType = $documentType ?? \App\Enums\PurchaseDocumentType::PurchaseOrder;
    $sourceQuotation = $sourceQuotation ?? null;
    $isQuotationForm = $documentType === \App\Enums\PurchaseDocumentType::Quotation;
    $isPoFromQuotation = $sourceQuotation !== null;
    $pageTitle = $isQuotationForm
        ? 'Create Quotation'
        : ($isPoFromQuotation ? 'Create Purchase Order from Quotation' : 'New Purchase Order');
    $prIdsList = $purchaseRequest
        ? $purchaseRequest->items
            ->map(fn($item) => $item->productRequestItem?->product_request_id ?? $item->product_request_id)
            ->filter()
            ->unique()
            ->map(fn($id) => '#'.$id)
            ->values()
            ->implode(', ')
        : '';
    if (! $prIdsList && $purchaseRequest?->product_request_id) {
        $prIdsList = '#'.$purchaseRequest->product_request_id;
    }

    $pageSubtitle = $isPoFromQuotation
        ? 'Quotation '.$sourceQuotation->reference_no.' · '.$sourceQuotation->plant->name
        : ($purchaseRequest
            ? 'From product request'.($prIdsList ? ' '.$prIdsList : '').' · '.$purchaseRequest->plant->name
            : 'Enter supplier, rate, quantity & GST per product');
    $supplierNames = $suppliers->pluck('name', 'id');
    $purchaseProductCatalog = $products->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '',
            'category' => $p->category?->name ?? '',
            'item_type' => $p->subCategory?->name ?? '',
        ];
    })->values();
    $lineSource = $isPoFromQuotation ? $sourceQuotation->items : ($purchaseRequest?->items ?? collect());
    $showLinkedLines = $lineSource->isNotEmpty();
@endphp
<x-admin-page-header :title="$pageTitle" :subtitle="$pageSubtitle">
    <a href="{{ route('plant.purchases.index') }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</x-admin-page-header>

@if($showLinkedLines)
<div class="purchase-create-pr-strip mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="purchase-create-pr-icon"><i class="bi bi-file-earmark-text"></i></span>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                @if($isPoFromQuotation)
                    <span class="badge bg-info text-dark">Quotation {{ $sourceQuotation->reference_no }}</span>
                @elseif($purchaseRequest)
                    <span class="badge bg-primary">From Product Request {{ $prIdsList }}</span>
                @endif
            </div>
            <p class="mb-0 small text-muted">
                {{ $lineSource->count() }} product(s) · set supplier, discount &amp; billing per line
            </p>
        </div>
        <div class="purchase-create-po-hint text-end" id="po-split-hint">
            <span class="d-block small text-muted">{{ $isQuotationForm ? 'Quotations' : 'Purchase orders' }}</span>
            <strong class="text-primary" id="po-split-count">—</strong>
        </div>
    </div>
</div>
@endif

<form method="POST" action="{{ route('plant.purchases.store') }}" id="purchase-form">
    @csrf
    <input type="hidden" name="document_type" value="{{ $documentType->value }}">
    @if($sourceQuotation)
    <input type="hidden" name="quotation_id" value="{{ $sourceQuotation->id }}">
    <input type="hidden" name="purchase_request_id" value="{{ $sourceQuotation->purchase_request_id }}">
    <input type="hidden" name="plant_id" value="{{ $sourceQuotation->plant_id }}">
    @elseif($purchaseRequest)
    <input type="hidden" name="purchase_request_id" value="{{ $purchaseRequest->id }}">
    <input type="hidden" name="plant_id" value="{{ $purchaseRequest->plant_id }}">
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="admin-card admin-table-card mb-0">
                <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0">Line items</h2>
                    <span class="small text-muted">Unit price is GST exclusive · discount % before GST</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 purchase-create-table">
                        <thead>
                            <tr>
                                <th class="purchase-col-product">{{ $showLinkedLines ? 'Item Name' : 'Product' }}</th>
                                @if($showLinkedLines && $purchaseRequest && ! $isPoFromQuotation)
                                <th class="purchase-col-requested">Requested item</th>
                                @endif
                                <th class="purchase-col-supplier">Supplier</th>
                                <th class="purchase-col-num purchase-col-qty">Qty</th>
                                <th class="purchase-col-num purchase-col-rate">Rate (₹)</th>
                                <th class="purchase-col-num" style="min-width: 5rem;">Disc%</th>
                                <th class="purchase-col-num purchase-col-gst">GST%</th>
                                <th class="purchase-col-num purchase-col-total">Line total</th>
                                <th style="width: 2.5rem"></th>
                            </tr>
                        </thead>
                        <tbody id="purchase-lines">
                            @if($isPoFromQuotation)
                            @foreach($sourceQuotation->items as $i => $item)
                            <tr class="purchase-line-row">
                                <td class="purchase-col-product">
                                    <input type="text" value="{{ $item->product?->name }}" class="form-control form-control-sm purchase-line-input purchase-item-name-input" placeholder="Search item name or SKU..." required autocomplete="off">
                                    <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $item->product_id }}" class="purchase-item-product-id">
                                </td>
                                <td class="purchase-col-supplier">
                                    <select name="items[{{ $i }}][supplier_id]" class="form-select form-select-sm purchase-line-input purchase-supplier-select" required>
                                        <option value="">— Select —</option>
                                        @foreach($suppliers as $s)
                                        <option value="{{ $s->id }}" @selected(old("items.$i.supplier_id", $sourceQuotation->supplier_id)==$s->id)>{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="purchase-col-num purchase-col-qty">
                                    <input type="number" step="1" min="1" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", (int)$item->quantity) }}" class="form-control form-control-sm purchase-line-input purchase-num-input" required>
                                </td>
                                <td class="purchase-col-num purchase-col-rate">
                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" value="{{ old("items.$i.unit_price", $item->unit_price) }}" class="form-control form-control-sm purchase-line-input purchase-num-input" required>
                                </td>
                                <td class="purchase-col-num">
                                    <input type="number" step="0.01" min="0" max="100" name="items[{{ $i }}][discount_percent]" value="{{ old("items.$i.discount_percent", $item->discount_percent ?? 0) }}" class="form-control form-control-sm purchase-line-input purchase-num-input" placeholder="0">
                                </td>
                                <td class="purchase-col-num purchase-col-gst">
                                    <select name="items[{{ $i }}][gst_rate]" class="form-select form-select-sm purchase-line-input purchase-num-input">
                                        @foreach([0, 5, 12, 18, 28] as $rate)
                                            <option value="{{ $rate }}" @selected(old("items.$i.gst_rate", $item->gst_rate) == $rate)>{{ $rate }} %</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="purchase-col-num purchase-col-total purchase-line-total-cell">
                                    <span class="purchase-line-total">₹0.00</span>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-link text-danger p-0 align-middle" onclick="removeDynamicRow(this)" title="Remove item">
                                        <i class="bi bi-trash fs-6"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                            @elseif($purchaseRequest)
                            @foreach($purchaseRequest->items as $i => $item)
                            @php
                                $prItem = $item->productRequestItem
                                    ?? ($purchaseRequest->productRequest
                                        ? $purchaseRequest->productRequest->items->firstWhere('id', $item->product_request_item_id)
                                        : null);
                                if (! $prItem && $item->product_id && $purchaseRequest->productRequest) {
                                    $prItem = $purchaseRequest->productRequest->items->firstWhere('product_id', $item->product_id);
                                }
                                $defaultGstRate = $prItem ? $prItem->gst_rate : 18;
                                $defaultUnitPrice = $item->unit_price > 0 ? $item->unit_price : ($prItem ? $prItem->unit_price : 0);
                                $defaultSupplierId = $item->supplier_id ?? $prItem?->supplier_id ?? request()->integer('supplier_id');
                                $requestedLabel = $item->displayName();
                                $requestedSize = $item->size ?? $prItem?->size ?? $item->product?->size;
                                $selectedProductId = old("items.$i.product_id", $item->product_id ?: $prItem?->product_id);
                                $selectedProduct = $selectedProductId ? $products->firstWhere('id', (int) $selectedProductId) : null;
                                $itemNameValue = $selectedProduct?->name ?? $prItem?->product?->name ?? '';
                            @endphp
                            <tr class="purchase-line-row">
                                <td class="purchase-col-product">
                                    <input type="text"
                                           value="{{ $itemNameValue }}"
                                           class="form-control form-control-sm purchase-line-input purchase-item-name-input"
                                           placeholder="Search item name or SKU..."
                                           required
                                           autocomplete="off">
                                    <input type="hidden"
                                           name="items[{{ $i }}][product_id]"
                                           value="{{ $selectedProductId }}"
                                           class="purchase-item-product-id">
                                </td>
                                <td class="purchase-col-requested">
                                    <span class="fw-semibold d-block">{{ $requestedLabel }}</span>
                                    @if($requestedSize)
                                        <span class="small text-muted">Size: {{ $requestedSize }}</span>
                                    @endif
                                    @if($item->subCategory?->name ?? $prItem?->subCategory?->name)
                                        <span class="d-block small text-muted">{{ $item->subCategory?->name ?? $prItem?->subCategory?->name }}</span>
                                    @endif
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
                                    <input type="number" step="1" min="1" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", (int)$item->quantity) }}" class="form-control form-control-sm purchase-line-input purchase-num-input" required>
                                </td>
                                <td class="purchase-col-num purchase-col-rate">
                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" value="{{ old("items.$i.unit_price", $defaultUnitPrice) }}" class="form-control form-control-sm purchase-line-input purchase-num-input" required>
                                </td>
                                <td class="purchase-col-num">
                                    <input type="number" step="0.01" min="0" max="100" name="items[{{ $i }}][discount_percent]" value="{{ old("items.$i.discount_percent", 0) }}" class="form-control form-control-sm purchase-line-input purchase-num-input" placeholder="0">
                                </td>
                                <td class="purchase-col-num purchase-col-gst">
                                    <select name="items[{{ $i }}][gst_rate]" class="form-select form-select-sm purchase-line-input purchase-num-input">
                                        @foreach([0, 5, 12, 18, 28] as $rate)
                                            <option value="{{ $rate }}" @selected(old("items.$i.gst_rate", $defaultGstRate) == $rate)>{{ $rate }} %</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="purchase-col-num purchase-col-total purchase-line-total-cell">
                                    <span class="purchase-line-total">₹0.00</span>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-link text-danger p-0 align-middle" onclick="removeDynamicRow(this)" title="Remove item">
                                        <i class="bi bi-trash fs-6"></i>
                                    </button>
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
                @if(!$showLinkedLines)
                <div class="admin-card-body border-top py-2">
                    <button type="button" class="btn btn-sm btn-admin-outline" onclick="addPurchaseLine()">
                        <i class="bi bi-plus-lg me-1"></i>Add line
                    </button>
                </div>
                @endif
            </div>

            @if($showLinkedLines)
            <p class="small text-muted mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Different suppliers on separate lines will create separate {{ $isQuotationForm ? 'quotations' : 'purchase orders' }} automatically.
            </p>
            @endif

            <div class="d-flex flex-wrap gap-2 mt-4 d-lg-none">
                <button type="submit" class="btn btn-admin-primary">
                    <i class="bi bi-check2-circle me-1"></i>{{ $isQuotationForm ? 'Create quotation' : 'Create purchase order' }}
                </button>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-card mb-4">
                <div class="admin-card-header"><h2 class="h6 mb-0">Order details</h2></div>
                <div class="admin-card-body">
                    <div class="vstack gap-3">
                        @if(!$purchaseRequest && !$sourceQuotation)
                        <div>
                            <label class="form-label small" for="plant_id">Plant</label>
                            <select name="plant_id" id="plant_id" class="form-select form-select-sm" required>
                                @foreach($plants as $s)
                                <option value="{{ $s->id }}" @selected(old('plant_id')==$s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <div>
                            <span class="form-label small text-muted d-block">Plant</span>
                            <span class="fw-semibold">{{ $sourceQuotation?->plant->name ?? $purchaseRequest->plant->name }}</span>
                        </div>
                        @endif

                        <div>
                            <label class="form-label small" for="reference_no">{{ $isQuotationForm ? 'Quotation' : 'PO' }} no.</label>
                            <input type="text" name="reference_no" id="reference_no" value="{{ old('reference_no', $nextQuotationNo ?? '') }}" class="form-control form-control-sm" placeholder="{{ $nextQuotationNo ?? 'Auto plant-wise (e.g. piska-01)' }}">
                            <div class="form-text">Uses plant name + serial (e.g. piska-01, piska-02).</div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small" for="supplier_invoice_no">Supplier quotation no.</label>
                                <input type="text" name="supplier_invoice_no" id="supplier_invoice_no" value="{{ old('supplier_invoice_no', $sourceQuotation?->supplier_invoice_no) }}" class="form-control form-control-sm" placeholder="Optional — defaults to doc no.">
                            </div>
                            <div class="col-6">
                                <label class="form-label small" for="supplier_invoice_date">Quotation date</label>
                                <input type="date" name="supplier_invoice_date" id="supplier_invoice_date" value="{{ old('supplier_invoice_date', $sourceQuotation?->supplier_invoice_date?->format('Y-m-d') ?? now()->toDateString()) }}" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="purchase-gst-type-box rounded border bg-light px-3 py-2">
                            <input type="hidden" name="is_interstate" value="0">
                            <div class="form-check mb-0">
                                <input type="checkbox" name="is_interstate" value="1" id="is_interstate" class="form-check-input purchase-line-input" @checked(old('is_interstate', $sourceQuotation?->is_interstate))>
                                <label class="form-check-label small" for="is_interstate">Inter-state supply (IGST only)</label>
                            </div>
                        </div>

                        <div>
                            <label class="form-label small" for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control form-control-sm" rows="2" placeholder="Optional">{{ old('notes', $sourceQuotation?->notes) }}</textarea>
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
                        <i class="bi bi-check2-circle me-1"></i>{{ $isQuotationForm ? 'Create quotation' : 'Create purchase order' }}
                    </button>
                    <a href="{{ route('plant.purchases.index') }}" class="btn btn-admin-outline w-100 btn-sm">Cancel</a>
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
<style>
#purchase-item-autocomplete-portal {
    position: fixed;
    max-height: 250px;
    overflow-y: auto;
    z-index: 2000;
    display: none;
}
#purchase-item-autocomplete-portal.is-open {
    display: block;
}
#purchase-item-autocomplete-portal .suggestion-item {
    cursor: pointer;
    transition: background 0.15s ease-in-out;
}
#purchase-item-autocomplete-portal .suggestion-item:hover {
    background-color: #f8f9fa;
}
</style>
@include('partials.infinite-suggestions')
<script type="application/json" id="purchase-product-catalog-data">@json($purchaseProductCatalog)</script>
<script>
    const supplierNames = @json($supplierNames);
    const docLabel = @json($isQuotationForm ? 'quotation' : 'PO');

    function addPurchaseLine() {
        const container = document.getElementById('purchase-lines');
        if (!container) return;
        const index = container.querySelectorAll('.purchase-line-row, .dynamic-row').length;
        const tpl = document.getElementById('purchase-line-tpl').innerHTML.replace(/__INDEX__/g, index);
        container.insertAdjacentHTML('beforeend', tpl);
        bindPurchaseGstCalc();
        recalcPurchaseTotals();
    }

    function calcPurchaseLine(qty, price, gstRate, isInterstate, discountPercent) {
        const discount = Math.max(0, Math.min(100, discountPercent || 0));
        const gross = Math.round(qty * price * 100) / 100;
        const taxable = Math.round(gross * (1 - discount / 100) * 100) / 100;
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
            const discount = parseFloat(row.querySelector('[name*="[discount_percent]"]')?.value) || 0;
            const supplierId = row.querySelector('[name*="[supplier_id]"]')?.value;
            if (supplierId) supplierIds.add(supplierId);

            const line = calcPurchaseLine(qty, price, gstRate, isInterstate, discount);
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
                poHint.textContent = '1 ' + docLabel;
            } else {
                poHint.textContent = count + ' ' + docLabel + 's';
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
        initPurchaseItemAutocomplete();
    });

    function initPurchaseItemAutocomplete() {
        const catalogEl = document.getElementById('purchase-product-catalog-data');
        const form = document.getElementById('purchase-form');
        if (!catalogEl || !form) {
            return;
        }

        const products = JSON.parse(catalogEl.textContent);
        let activeInput = null;
        let activeRow = null;

        const portal = document.createElement('div');
        portal.id = 'purchase-item-autocomplete-portal';
        portal.className = 'autocomplete-suggestions shadow border rounded-bottom bg-white';
        document.body.appendChild(portal);

        const infinite = window.PanchmukhiInfiniteSuggestions.create(portal, {
            pageSize: 25,
            renderItem: function (product) {
                const div = document.createElement('div');
                div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                div.innerHTML = '<span class="fw-semibold text-dark d-block text-truncate">'
                    + product.name
                    + '</span> <span class="text-muted small">'
                    + ((product.category || 'No category') + ' / ' + (product.item_type || 'No item type') + ' · ' + (product.sku || 'No SKU'))
                    + '</span>';
                div.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    if (activeRow) {
                        applyProductSelection(activeRow, product);
                    }
                });
                return div;
            },
        });

        function hideSuggestions() {
            infinite.reset();
            portal.classList.remove('is-open');
            activeInput = null;
            activeRow = null;
        }

        function positionPortal(input) {
            const rect = input.getBoundingClientRect();
            portal.style.top = (rect.bottom + 2) + 'px';
            portal.style.left = rect.left + 'px';
            portal.style.width = rect.width + 'px';
        }

        function applyProductSelection(row, product) {
            const input = row.querySelector('.purchase-item-name-input');
            const productIdInput = row.querySelector('.purchase-item-product-id');

            input.value = product.name;
            productIdInput.value = product.id;
            input.classList.remove('is-invalid');
            hideSuggestions();
        }

        function showSuggestions(input) {
            const row = input.closest('.purchase-line-row');
            if (!row) {
                return;
            }

            const productIdInput = row.querySelector('.purchase-item-product-id');
            const val = input.value.trim().toLowerCase();

            activeInput = input;
            activeRow = row;
            productIdInput.value = '';

            if (!val) {
                hideSuggestions();
                return;
            }

            const filtered = products.filter(function (product) {
                return product.name.toLowerCase().includes(val)
                    || (product.sku && product.sku.toLowerCase().includes(val));
            });

            if (!infinite.setResults(filtered)) {
                hideSuggestions();
                return;
            }

            positionPortal(input);
            portal.classList.add('is-open');
        }

        document.addEventListener('input', function (event) {
            if (event.target.matches('.purchase-item-name-input')) {
                showSuggestions(event.target);
            }
        });

        document.addEventListener('focusin', function (event) {
            if (event.target.matches('.purchase-item-name-input') && event.target.value.trim()) {
                showSuggestions(event.target);
            }
        });

        document.addEventListener('click', function (event) {
            if (activeInput && (activeInput.contains(event.target) || portal.contains(event.target))) {
                return;
            }
            hideSuggestions();
        });

        window.addEventListener('scroll', function () {
            if (activeInput && portal.classList.contains('is-open')) {
                positionPortal(activeInput);
            }
        }, true);

        window.addEventListener('resize', function () {
            if (activeInput && portal.classList.contains('is-open')) {
                positionPortal(activeInput);
            }
        });

        form.addEventListener('submit', function (event) {
            let hasError = false;

            document.querySelectorAll('#purchase-lines .purchase-line-row').forEach(function (row) {
                const nameInput = row.querySelector('.purchase-item-name-input');
                const productIdInput = row.querySelector('.purchase-item-product-id');

                if (!nameInput || !productIdInput) {
                    return;
                }

                if (!productIdInput.value) {
                    hasError = true;
                    nameInput.classList.add('is-invalid');
                } else {
                    nameInput.classList.remove('is-invalid');
                }
            });

            if (hasError) {
                event.preventDefault();
                alert('Please search and select an item from the catalog for each line.');
            }
        });
    }
</script>
@endpush
@endsection
