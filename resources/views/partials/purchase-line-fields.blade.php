@php
    $showRemove = $showRemove ?? true;
@endphp
<div class="col-lg-4 col-md-6">
    <label class="form-label small mb-1">Product</label>
    <select name="items[{{ $i }}][product_id]" required class="form-select purchase-line-input">
        @foreach($products as $p)
            <option value="{{ $p->id }}" @selected(isset($productId) && $productId == $p->id)>
                {{ $p->name }}@if($p->sku) ({{ $p->sku }})@endif
            </option>
        @endforeach
    </select>
</div>
<div class="col-lg-2 col-md-3">
    <label class="form-label small mb-1">Quantity</label>
    <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" value="{{ $quantity ?? 1 }}" required
           class="form-control purchase-line-input">
</div>
<div class="col-lg-2 col-md-3">
    <label class="form-label small mb-1">Unit price (₹)</label>
    <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" value="{{ $unitPrice ?? 0 }}" required
           class="form-control purchase-line-input" placeholder="0.00">
</div>
<div class="col-lg-2 col-md-3">
    <label class="form-label small mb-1">GST %</label>
    <input type="number" step="0.01" min="0" max="100" name="items[{{ $i }}][gst_rate]" value="{{ $gstRate ?? 18 }}"
           class="form-control purchase-line-input">
</div>
@if($showRemove)
<div class="col-lg-2 col-md-3 purchase-line-remove-col">
    <label class="form-label small mb-1 d-none d-lg-block">&nbsp;</label>
    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeDynamicRow(this); recalcPurchaseTotals();" title="Remove line" aria-label="Remove line">
        <i class="bi bi-trash" aria-hidden="true"></i>
    </button>
</div>
@endif
