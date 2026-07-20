@extends('layouts.ho')
@section('title', 'Edit Product Request #' . $productRequest->id)
@section('content')
    <x-admin-page-header title="Edit Product Request #{{ $productRequest->id }}"
        subtitle="Rate, quantity, supplier & billing">
        <a href="{{ route('ho.product-requests.show', $productRequest) }}" class="btn btn-admin-outline btn-sm">Back</a>
    </x-admin-page-header>

    <form method="POST" action="{{ route('ho.product-requests.update', $productRequest) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control"
                    rows="2">{{ old('remarks', $productRequest->remarks) }}</textarea>
            </div>
        </div>
        <div class="admin-card admin-table-card mb-4">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Item Type</th>
                            <th>Item Name</th>
                            <th>Size</th>
                            <th>Supplier</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>GST %</th>
                            <th>Billing Notes</th>
                            <th>Item Remarks</th>
                            <th>Image</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productRequest->items as $i => $item)
                            <tr>
                                <td>{{ $item->subCategory?->name ?? $item->product?->subCategory?->name ?? '—' }}</td>
                                <td>
                                    <span class="fw-semibold">{{ $item->displayName() }}</span>
                                    @if($item->product?->sku)
                                        <span class="d-block small text-muted text-uppercase">SKU: {{ $item->product->sku }}</span>
                                    @elseif(!$item->product_id)
                                        <span class="d-block small text-muted">Custom item</span>
                                    @endif
                                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                </td>
                                <td>{{ $item->size ?? $item->product?->size ?? '—' }}</td>
                                <td>
                                    <select name="items[{{ $i }}][supplier_id]" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach($suppliers as $s)
                                            <option value="{{ $s->id }}" @selected(old("items.$i.supplier_id", $item->supplier_id) == $s->id)>{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" name="items[{{ $i }}][quantity]"
                                        value="{{ old("items.$i.quantity", $item->quantity) }}"
                                        class="form-control form-control-sm" required></td>
                                <td><input type="number" step="0.01" name="items[{{ $i }}][unit_price]"
                                        value="{{ old("items.$i.unit_price", $item->unit_price) }}"
                                        class="form-control form-control-sm"></td>
                                <td>
                                    <select name="items[{{ $i }}][gst_rate]" class="form-select form-select-sm">
                                        @foreach([0, 5, 12, 18, 28] as $rate)
                                            <option value="{{ $rate }}" @selected(old("items.$i.gst_rate", $item->gst_rate) == $rate)>
                                                {{ $rate }} %</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="items[{{ $i }}][billing_notes]"
                                        value="{{ old("items.$i.billing_notes", $item->billing_notes) }}"
                                        class="form-control form-control-sm"></td>
                                <td><input type="text" name="items[{{ $i }}][remarks]"
                                        value="{{ old("items.$i.remarks", $item->remarks) }}"
                                        class="form-control form-control-sm" placeholder="Optional remark"></td>
                                <td>
                                    @if($item->image_path)
                                        <div class="mb-1">
                                            <a href="{{ $item->image_url }}" target="_blank" class="d-inline-block">
                                                <img src="{{ $item->image_url }}" alt="Preview" class="img-thumbnail"
                                                    style="max-height: 40px;">
                                            </a>
                                        </div>
                                    @endif
                                    <input type="file" name="items[{{ $i }}][image]" class="form-control form-control-sm"
                                        accept="image/*">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <button class="btn btn-primary">Save billing details</button>
    </form>
@endsection