@php
    $isExisting = isset($item) && $item;
    $minQty = $isExisting ? max(0.01, (float) $item->received_quantity) : 0.01;
    $canRemove = $isExisting ? (float) $item->received_quantity <= 0 : true;
@endphp
<tr class="dynamic-row stock-in-po-line" data-received="{{ $isExisting ? (float) $item->received_quantity : 0 }}">
    <td class="purchase-col-product">
        @if($isExisting)
            <span class="fw-semibold d-block text-truncate" title="{{ $item->product->name }}">{{ $item->product->name }}</span>
            @if($item->product->sku)
                <code class="small text-muted">{{ $item->product->sku }}</code>
            @endif
            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
        @else
            <select name="items[{{ $i }}][product_id]" required class="form-select form-select-sm stock-in-po-input">
                <option value="">— Select product —</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(old("items.$i.product_id") == $p->id)>
                        {{ $p->name }}@if($p->sku) ({{ $p->sku }})@endif
                    </option>
                @endforeach
            </select>
        @endif
    </td>
    <td class="purchase-col-num purchase-col-qty">
        <input type="number" step="0.01" min="{{ $minQty }}" name="items[{{ $i }}][quantity]"
            value="{{ old("items.$i.quantity", $isExisting ? $item->quantity : 1) }}"
            class="form-control form-control-sm stock-in-po-input purchase-num-input" required>
    </td>
    <td class="purchase-col-num purchase-col-rate">
        <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]"
            value="{{ old("items.$i.unit_price", $isExisting ? $item->unit_price : 0) }}"
            class="form-control form-control-sm stock-in-po-input purchase-num-input" required>
    </td>
    <td class="purchase-col-num purchase-col-gst">
        <select name="items[{{ $i }}][gst_rate]" class="form-select form-select-sm stock-in-po-input purchase-num-input">
            @foreach([0, 5, 12, 18, 28] as $rate)
                <option value="{{ $rate }}" @selected(old("items.$i.gst_rate", $isExisting ? $item->gst_rate : 18) == $rate)>{{ $rate }} %</option>
            @endforeach
        </select>
    </td>
    <td class="purchase-col-num purchase-col-qty text-muted">
        @if($isExisting)
            {{ number_format($item->received_quantity, 2) }}
        @else
            —
        @endif
    </td>
    <td class="purchase-col-num purchase-col-total">
        <span class="stock-in-line-total small fw-semibold">₹0.00</span>
    </td>
    <td class="purchase-col-action">
        @if($canRemove)
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStockInPoLine(this)" title="Remove line" aria-label="Remove line">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
        @endif
    </td>
</tr>
