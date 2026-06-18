@extends('layouts.site')
@section('title', 'Edit Purchase Request')
@section('content')
<x-admin-page-header title="Edit Purchase Request #{{ $purchaseRequest->id }}" />
<form method="POST" action="{{ route('site.purchase-requests.update', $purchaseRequest) }}">
    @csrf @method('PUT')
    <div class="admin-card admin-table-card mb-4">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Product</th><th>Supplier</th><th>Qty</th><th>Rate</th></tr></thead>
                <tbody>
                    @foreach($purchaseRequest->items as $i => $item)
                    <tr>
                        <td>{{ $item->product->name }}<input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}"></td>
                        <td>
                            <select name="items[{{ $i }}][supplier_id]" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" @selected($item->supplier_id==$s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="number" step="0.01" name="items[{{ $i }}][quantity]" value="{{ $item->quantity }}" class="form-control form-control-sm" required></td>
                        <td><input type="number" step="0.01" name="items[{{ $i }}][unit_price]" value="{{ $item->unit_price }}" class="form-control form-control-sm"></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <button class="btn btn-primary">Save changes</button>
</form>
@endsection
