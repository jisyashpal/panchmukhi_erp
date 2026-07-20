@php
    $showRemove = $showRemove ?? true;
    $showLineTotal = $showLineTotal ?? false;
    $supplierId = $supplierId ?? null;
@endphp
<tr class="dynamic-row purchase-line-row">
    <td class="purchase-col-product">
        <input type="text"
               value="{{ isset($productId) && ($selectedProduct = $products->firstWhere('id', (int) $productId)) ? $selectedProduct->name : '' }}"
               class="form-control form-control-sm purchase-line-input purchase-item-name-input"
               placeholder="Search item name or SKU..."
               required
               autocomplete="off">
        <input type="hidden"
               name="items[{{ $i }}][product_id]"
               value="{{ $productId ?? '' }}"
               class="purchase-item-product-id">
    </td>
    <td class="purchase-col-supplier">
        <select name="items[{{ $i }}][supplier_id]" required class="form-select form-select-sm purchase-line-input purchase-supplier-select">
            <option value="">— Select —</option>
            @foreach($suppliers as $s)
                <option value="{{ $s->id }}" @selected(old("items.$i.supplier_id", $supplierId) == $s->id)>
                    {{ $s->name }}
                </option>
            @endforeach
        </select>
    </td>
    <td class="purchase-col-num purchase-col-qty">
        <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" value="{{ $quantity ?? 1 }}" required
               class="form-control form-control-sm purchase-line-input purchase-num-input">
    </td>
    <td class="purchase-col-num purchase-col-rate">
        <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" value="{{ $unitPrice ?? 0 }}" required
               class="form-control form-control-sm purchase-line-input purchase-num-input" placeholder="0.00">
    </td>
    <td class="purchase-col-num purchase-col-gst">
        <select name="items[{{ $i }}][gst_rate]" class="form-select form-select-sm purchase-line-input purchase-num-input">
            @foreach([0, 5, 12, 18, 28] as $rate)
                <option value="{{ $rate }}" @selected(($gstRate ?? 18) == $rate)>{{ $rate }} %</option>
            @endforeach
        </select>
    </td>
    @if($showLineTotal)
    <td class="purchase-col-num purchase-col-total purchase-line-total-cell">
        <span class="purchase-line-total">₹0.00</span>
    </td>
    @endif
    @if($showRemove)
    <td class="purchase-col-action">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDynamicRow(this)" title="Remove line" aria-label="Remove line">
            <i class="bi bi-trash" aria-hidden="true"></i>
        </button>
    </td>
    @endif
</tr>
