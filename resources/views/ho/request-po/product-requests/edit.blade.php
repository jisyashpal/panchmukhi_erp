@extends('layouts.ho')
@section('title', 'Edit Product Request #'.$productRequest->id)
@section('content')
<x-admin-page-header title="Edit Product Request #{{ $productRequest->id }}" subtitle="Rate, quantity, supplier & billing">
    <a href="{{ route('ho.product-requests.show', $productRequest) }}" class="btn btn-admin-outline btn-sm">Back</a>
</x-admin-page-header>

<form method="POST" action="{{ route('ho.product-requests.update', $productRequest) }}">
    @csrf @method('PUT')
    <div class="admin-card mb-4">
        <div class="admin-card-body">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $productRequest->remarks) }}</textarea>
        </div>
    </div>
    <div class="admin-card admin-table-card mb-4">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Supplier</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>GST %</th>
                        <th>Billing Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productRequest->items as $i => $item)
                    <tr>
                        <td>{{ $item->product->name }}<input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}"></td>
                        <td>
                            <select name="items[{{ $i }}][supplier_id]" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" @selected(old("items.$i.supplier_id", $item->supplier_id)==$s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="number" step="0.01" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", $item->quantity) }}" class="form-control form-control-sm" required></td>
                        <td><input type="number" step="0.01" name="items[{{ $i }}][unit_price]" value="{{ old("items.$i.unit_price", $item->unit_price) }}" class="form-control form-control-sm"></td>
                        <td><input type="number" step="0.01" name="items[{{ $i }}][gst_rate]" value="{{ old("items.$i.gst_rate", $item->gst_rate) }}" class="form-control form-control-sm"></td>
                        <td><input type="text" name="items[{{ $i }}][billing_notes]" value="{{ old("items.$i.billing_notes", $item->billing_notes) }}" class="form-control form-control-sm"></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <button class="btn btn-primary">Save billing details</button>
</form>
@endsection
