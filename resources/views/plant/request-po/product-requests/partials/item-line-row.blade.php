@php
    $index = $index ?? 0;
    $oldItem = old("items.{$index}", []);
@endphp
<div class="col-md-1">
    <label class="form-label">Category</label>
    <select name="items[{{ $index }}][category_id]" class="form-select form-select-sm item-category-select">
        <option value="">Select category</option>
        @foreach($categories as $c)
            <option value="{{ $c->id }}" @selected(($oldItem['category_id'] ?? '') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-1">
    <label class="form-label">Item Type</label>
    <select name="items[{{ $index }}][sub_category_id]" class="form-select form-select-sm item-subcategory-select">
        <option value="">Select Item Type</option>
        @foreach($subCategories as $sc)
            <option value="{{ $sc->id }}" data-category-id="{{ $sc->category_id }}" @selected(($oldItem['sub_category_id'] ?? '') == $sc->id)>{{ $sc->name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2 position-relative">
    <label class="form-label">Search Item Name / SKU</label>
    <input type="text" name="items[{{ $index }}][item_name]" value="{{ $oldItem['item_name'] ?? '' }}"
        class="form-control form-control-sm item-name-input" placeholder="Enter item name or SKU..." required
        autocomplete="off">
    <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $oldItem['product_id'] ?? '' }}"
        class="item-product-id">
</div>
<div class="col-md-1">
    <label class="form-label">Size</label>
    <input type="text" name="items[{{ $index }}][size]" value="{{ $oldItem['size'] ?? '' }}"
        class="form-control form-control-sm item-size-input" placeholder="Size">
</div>
<div class="col-md-1">
    <label class="form-label">Qty</label>
    <input type="number" step="1" min="1" name="items[{{ $index }}][quantity]" value="{{ $oldItem['quantity'] ?? 1 }}"
        required class="form-control form-control-sm">
</div>
<div class="col-md-1">
    <label class="form-label">Unit</label>
    <input type="text" name="items[{{ $index }}][unit]" value="{{ $oldItem['unit'] ?? '' }}"
        class="form-control form-control-sm item-unit-input" placeholder="Unit" maxlength="100">
</div>
<div class="col-md-1">
    <label class="form-label">Priority</label>
    <select name="items[{{ $index }}][priority]" class="form-select form-select-sm" required>
        @foreach(\App\Enums\PriorityLevel::cases() as $priority)
            <option value="{{ $priority->value }}" @selected(($oldItem['priority'] ?? 'medium') === $priority->value)>
                {{ $priority->label() }}
            </option>
        @endforeach
    </select>
</div>
<div class="col-md-1">
    <label class="form-label">Vehicle No / Machine No</label>
    <input type="text" name="items[{{ $index }}][remarks]" value="{{ $oldItem['remarks'] ?? '' }}"
        class="form-control form-control-sm" placeholder="Vehicle / machine no">
</div>
<div class="col-md-2">
    <label class="form-label">Image</label>
    <input type="file" name="items[{{ $index }}][image]" class="form-control form-control-sm" accept="image/*">
</div>
<div class="col-md-1 text-end">
    <label class="form-label d-md-none">&nbsp;</label>
    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeDynamicRow(this)">Remove</button>
</div>