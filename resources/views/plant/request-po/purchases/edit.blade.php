@extends('layouts.plant')
@section('title', 'Edit Purchase Details')
@section('content')
<x-admin-page-header title="Edit {{ $purchase->isQuotation() ? 'Quotation' : 'Purchase' }} Details" subtitle="{{ $purchase->isQuotation() ? 'Quotation' : 'PO' }}: {{ $purchase->reference_no }}">
    <a href="{{ route('plant.purchases.index') }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</x-admin-page-header>

<form method="POST" action="{{ route('plant.purchases.update', $purchase) }}">
    @csrf
    @method('PUT')
    
    <div class="row">
        <div class="col-xl-8">
            <div class="admin-card mb-4">
                <div class="admin-card-header">
                    <h2 class="h6 mb-0">Purchase Information</h2>
                </div>
                <div class="admin-card-body">
                    <div class="row g-3">
                        <x-admin-form-group label="Supplier" name="supplier_id" required col="col-md-6">
                            <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                                <option value="">Select supplier</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected(old('supplier_id', $purchase->supplier_id) == $s->id)>
                                        {{ $s->name }}@if($s->gstin) — {{ $s->gstin }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </x-admin-form-group>

                        <x-admin-form-input label="Supplier invoice no." name="supplier_invoice_no" value="{{ old('supplier_invoice_no', $purchase->supplier_invoice_no) }}" col="col-md-6" />

                        <x-admin-form-group label="Supplier invoice date" name="supplier_invoice_date" col="col-md-6">
                            <input type="date" name="supplier_invoice_date" id="supplier_invoice_date" 
                                   value="{{ old('supplier_invoice_date', $purchase->supplier_invoice_date ? $purchase->supplier_invoice_date->format('Y-m-d') : '') }}" 
                                   class="form-control @error('supplier_invoice_date') is-invalid @enderror">
                        </x-admin-form-group>

                        <div class="col-md-6">
                            <label class="form-label" for="is_interstate">GST Type</label>
                            <div class="purchase-gst-type-box rounded border bg-light px-3 py-2 h-100 d-flex align-items-center" style="min-height: 38px;">
                                <input type="hidden" name="is_interstate" value="0">
                                <div class="form-check mb-0">
                                    <input type="checkbox" name="is_interstate" value="1" id="is_interstate" class="form-check-input"
                                        @checked(old('is_interstate', $purchase->is_interstate))>
                                    <label class="form-check-label" for="is_interstate">Inter-state supply (IGST only)</label>
                                </div>
                            </div>
                        </div>

                        <x-admin-form-group label="Notes" name="notes" col="col-12">
                            <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="2" placeholder="Optional notes">{{ old('notes', $purchase->notes) }}</textarea>
                        </x-admin-form-group>
                    </div>
                </div>
            </div>

            <div class="admin-card mb-4">
                <div class="admin-card-header d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Line Items</h2>
                    <span class="small text-muted">Prices exclude GST</span>
                </div>
                <div class="admin-card-body pt-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle" id="purchase-lines">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end" style="width: 100px;">Qty</th>
                                    <th class="text-end" style="width: 130px;">Unit Price (₹)</th>
                                    <th class="text-end" style="width: 90px;">Disc %</th>
                                    <th class="text-end" style="width: 80px;">GST %</th>
                                    <th class="text-end" style="width: 110px;">Total (₹)</th>
                                    <th class="text-center" style="width: 45px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchase->items as $i => $item)
                                    <tr data-gstrate="{{ $item->gst_rate }}">
                                        <td class="position-relative">
                                            <input type="text"
                                                   value="{{ old('items.'.$i.'.item_name', $item->product?->name) }}"
                                                   class="form-control form-control-sm purchase-line-input purchase-item-name-input @error('items.'.$i.'.product_id') is-invalid @enderror"
                                                   placeholder="Search item name or SKU..."
                                                   required
                                                   autocomplete="off">
                                            <input type="hidden"
                                                   name="items[{{ $i }}][product_id]"
                                                   value="{{ old('items.'.$i.'.product_id', $item->product_id) }}"
                                                   class="purchase-item-product-id">
                                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                        </td>
                                        <td class="text-end">
                                            <input type="number" step="1" min="1" name="items[{{ $i }}][quantity]"
                                                   value="{{ old('items.'.$i.'.quantity', (int) $item->quantity) }}"
                                                   required class="form-control form-control-sm text-end purchase-line-input @error('items.'.$i.'.quantity') is-invalid @enderror" style="max-width: 90px; display: inline-block;">
                                        </td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm justify-content-end">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]"
                                                       value="{{ old('items.'.$i.'.unit_price', number_format((float)$item->unit_price, 2, '.', '')) }}"
                                                       required class="form-control text-end purchase-line-input @error('items.'.$i.'.unit_price') is-invalid @enderror" style="max-width: 110px;">
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <input type="number" step="0.01" min="0" max="100" name="items[{{ $i }}][discount_percent]"
                                                   value="{{ old('items.'.$i.'.discount_percent', number_format((float)($item->discount_percent ?? 0), 2, '.', '')) }}"
                                                   class="form-control form-control-sm text-end purchase-line-input purchase-discount-input" style="max-width: 80px;">
                                        </td>
                                        <td class="text-end text-muted">{{ $item->gst_rate }}%</td>
                                        <td class="text-end fw-semibold">₹<span class="purchase-line-total">0.00</span></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-line" title="Delete product item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-card-footer bg-light p-2 text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-line">
                        <i class="bi bi-plus-lg me-1"></i>Add Product
                    </button>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 d-xl-none mb-4">
                <button type="submit" class="btn btn-admin-primary">
                    <i class="bi bi-check2-circle me-1"></i>Save Details
                </button>
                <a href="{{ route('plant.purchases.index') }}" class="btn btn-admin-outline">Cancel</a>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="admin-card sticky-top" style="top: 20px; z-index: 10;">
                <div class="admin-card-header">
                    <h2 class="h6 mb-0"><i class="bi bi-calculator me-2 text-primary"></i>GST Summary</h2>
                </div>
                <div class="admin-card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Taxable Subtotal</td>
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
                                <td class="fw-bold pt-2">Grand Total</td>
                                <td class="text-end fw-bold text-primary fs-5 pt-2">₹<span id="gst-grand">0.00</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0 mt-3">
                        <i class="bi bi-info-circle me-1"></i>Totals automatically update when you modify quantity, unit prices or toggle GST type.
                    </p>
                </div>
                <div class="admin-card-body border-top pt-0 d-none d-xl-block">
                    <button type="submit" class="btn btn-admin-primary w-100 mb-2">
                        <i class="bi bi-check2-circle me-1"></i>Save Details
                    </button>
                    <a href="{{ route('plant.purchases.index') }}" class="btn btn-admin-outline w-100">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

@php
    $purchaseProductCatalog = isset($products) ? $products->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '',
        ];
    })->values() : collect();
@endphp

@push('scripts')
<style>
#purchase-edit-autocomplete-portal {
    position: fixed;
    max-height: 250px;
    overflow-y: auto;
    z-index: 2000;
    display: none;
}
#purchase-edit-autocomplete-portal.is-open {
    display: block;
}
#purchase-edit-autocomplete-portal .suggestion-item {
    cursor: pointer;
    transition: background 0.15s ease-in-out;
}
#purchase-edit-autocomplete-portal .suggestion-item:hover {
    background-color: #f8f9fa;
}
</style>
@include('partials.infinite-suggestions')
<script type="application/json" id="purchase-edit-catalog-data">@json($purchaseProductCatalog)</script>
<script>
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
        let subtotal = 0,
            cgst = 0,
            sgst = 0,
            igst = 0,
            grand = 0;
        document.querySelectorAll('#purchase-lines tbody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('[name*="[quantity]"]')?.value) || 0;
            const price = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value) || 0;
            const discount = parseFloat(row.querySelector('[name*="[discount_percent]"]')?.value) || 0;
            const gstRate = parseFloat(row.dataset.gstrate) || 0;
            const line = calcPurchaseLine(qty, price, gstRate, isInterstate, discount);
            const lineTotalEl = row.querySelector('.purchase-line-total');
            if (lineTotalEl) {
                lineTotalEl.textContent = line.total.toFixed(2);
            }
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

    function initPurchaseItemAutocomplete() {
        const catalogEl = document.getElementById('purchase-edit-catalog-data');
        if (!catalogEl) return;
        const products = JSON.parse(catalogEl.textContent);
        let activeInput = null;
        let activeRow = null;

        const portal = document.createElement('div');
        portal.id = 'purchase-edit-autocomplete-portal';
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
                    + (product.sku || 'No SKU')
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
            hideSuggestions();
        }

        function showSuggestions(input) {
            const row = input.closest('tr');
            if (!row) return;
            const val = input.value.trim().toLowerCase();
            activeInput = input;
            activeRow = row;

            const filtered = products.filter(function (product) {
                if (!val) return true;
                return product.name.toLowerCase().includes(val)
                    || (product.sku && product.sku.toLowerCase().includes(val));
            });

            if (filtered.length === 0) {
                hideSuggestions();
                return;
            }

            infinite.setResults(filtered);
            positionPortal(input);
            portal.classList.add('is-open');
        }

        document.addEventListener('input', function (event) {
            if (event.target.matches('.purchase-item-name-input')) {
                const row = event.target.closest('tr');
                const productIdInput = row?.querySelector('.purchase-item-product-id');
                if (productIdInput) {
                    productIdInput.value = '';
                }
                showSuggestions(event.target);
            }
        });

        document.addEventListener('focusin', function (event) {
            if (event.target.matches('.purchase-item-name-input')) {
                showSuggestions(event.target);
            }
        });

        document.addEventListener('click', function (event) {
            if (event.target.matches('.purchase-item-name-input')) {
                showSuggestions(event.target);
                return;
            }
            if (activeInput && (activeInput.contains(event.target) || portal.contains(event.target))) {
                return;
            }
            hideSuggestions();
        });

        window.addEventListener('scroll', function (event) {
            if (!activeInput || !portal.classList.contains('is-open')) return;
            if (portal.contains(event.target)) return;
            positionPortal(activeInput);
        }, true);

        window.addEventListener('resize', function () {
            if (activeInput && portal.classList.contains('is-open')) {
                positionPortal(activeInput);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindPurchaseGstCalc();
        recalcPurchaseTotals();
        initPurchaseItemAutocomplete();

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-delete-line');
            if (btn) {
                const rows = document.querySelectorAll('#purchase-lines tbody tr');
                if (rows.length <= 1) {
                    alert('At least one product item is required in the purchase.');
                    return;
                }
                const row = btn.closest('tr');
                if (row) {
                    row.remove();
                    bindPurchaseGstCalc();
                    recalcPurchaseTotals();
                }
            }
        });

        const btnAdd = document.getElementById('btn-add-line');
        if (btnAdd) {
            btnAdd.addEventListener('click', function () {
                const tbody = document.querySelector('#purchase-lines tbody');
                const i = Date.now();
                const tr = document.createElement('tr');
                tr.setAttribute('data-gstrate', '18');
                tr.innerHTML = `
                    <td class="position-relative">
                        <input type="text" name="items[${i}][item_name]" class="form-control form-control-sm purchase-line-input purchase-item-name-input" placeholder="Search item name or SKU..." required autocomplete="off">
                        <input type="hidden" name="items[${i}][product_id]" class="purchase-item-product-id">
                    </td>
                    <td class="text-end">
                        <input type="number" step="1" min="1" name="items[${i}][quantity]" value="1" required class="form-control form-control-sm text-end purchase-line-input" style="max-width: 90px; display: inline-block;">
                    </td>
                    <td class="text-end">
                        <div class="input-group input-group-sm justify-content-end">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="0" name="items[${i}][unit_price]" value="0.00" required class="form-control text-end purchase-line-input" style="max-width: 110px;">
                        </div>
                    </td>
                    <td class="text-end">
                        <input type="number" step="0.01" min="0" max="100" name="items[${i}][discount_percent]" value="0.00" class="form-control form-control-sm text-end purchase-line-input purchase-discount-input" style="max-width: 80px;">
                    </td>
                    <td class="text-end text-muted">18%</td>
                    <td class="text-end fw-semibold">₹<span class="purchase-line-total">0.00</span></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-line" title="Delete product item">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
                bindPurchaseGstCalc();
                recalcPurchaseTotals();
            });
        }
    });
</script>
@endpush
@endsection
