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
                                    <th class="text-end" style="width: 120px;">Qty</th>
                                    <th class="text-end" style="width: 150px;">Unit Price (₹)</th>
                                    <th class="text-end" style="width: 110px;">Disc %</th>
                                    <th class="text-end" style="width: 100px;">GST %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchase->items as $i => $item)
                                    <tr data-gstrate="{{ $item->gst_rate }}">
                                        <td>
                                            <span class="fw-semibold text-dark">{{ $item->product->name }}</span>
                                            @if($item->product->sku)
                                                <span class="d-block small text-muted text-uppercase">SKU: {{ $item->product->sku }}</span>
                                            @endif
                                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                        </td>
                                        <td class="text-end">
                                            <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]"
                                                   value="{{ old('items.'.$i.'.quantity', $item->quantity) }}"
                                                   required class="form-control form-control-sm text-end purchase-line-input @error('items.'.$i.'.quantity') is-invalid @enderror" style="max-width: 100px; display: inline-block;">
                                        </td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm justify-content-end">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]"
                                                       value="{{ old('items.'.$i.'.unit_price', $item->unit_price) }}"
                                                       required class="form-control text-end purchase-line-input @error('items.'.$i.'.unit_price') is-invalid @enderror" style="max-width: 120px;">
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <input type="number" step="0.01" min="0" max="100" name="items[{{ $i }}][discount_percent]"
                                                   value="{{ old('items.'.$i.'.discount_percent', $item->discount_percent ?? 0) }}"
                                                   class="form-control form-control-sm text-end purchase-line-input purchase-discount-input" style="max-width: 90px;">
                                        </td>
                                        <td class="text-end text-muted">{{ $item->gst_rate }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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

@push('scripts')
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
