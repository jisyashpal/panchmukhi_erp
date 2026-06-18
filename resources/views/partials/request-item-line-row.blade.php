@php
    $index = $index ?? 0;
    $isTemplate = ($index === '__INDEX__');
@endphp
<div class="col-md-5">
    <label class="form-label">Product</label>
    <select name="items[{{ $index }}][product_id]" required class="form-select form-select-sm">
        <option value="">Select product</option>
        @foreach($products as $p)
            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
        @endforeach
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">Quantity</label>
    <input type="number" step="0.01" name="items[{{ $index }}][quantity]" value="1" required class="form-control form-control-sm">
</div>
<div class="col-md-3">
    <label class="form-label d-md-none">&nbsp;</label>
    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeDynamicRow(this)">Remove</button>
</div>
