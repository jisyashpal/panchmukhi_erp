@extends('layouts.ho')
@section('title', 'Product Request #'.$productRequest->id)
@section('content')
<x-admin-page-header title="Product Request #{{ $productRequest->id }}" subtitle="{{ $productRequest->plant->name }}">
    <div class="d-flex gap-2">
        <x-status-badge :status="$productRequest->status" />
        <a href="{{ route('ho.product-requests.edit', $productRequest) }}" class="btn btn-primary btn-sm">Edit billing</a>
        <a href="{{ route('ho.product-requests.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
    </div>
</x-admin-page-header>

<div class="admin-card admin-table-card mb-4">
    <div class="admin-card-header"><h2>Items</h2></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">GST %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productRequest->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->supplier?->name ?? '—' }}</td>
                    <td class="text-end">{{ $item->quantity }}</td>
                    <td class="text-end">{{ $item->unit_price }}</td>
                    <td class="text-end">{{ $item->gst_rate }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($productRequest->purchaseRequest?->purchases->isNotEmpty())
<div class="admin-card mb-4 bg-light border-success">
    <div class="admin-card-body">
        <h5 class="mb-1 text-success"><i class="bi bi-check-circle-fill me-2"></i>Purchase order created by purchaser</h5>
        <p class="mb-0 text-muted small">
            Purchase Request #{{ $productRequest->purchaseRequest->id }}
            · {{ $productRequest->purchaseRequest->purchases->count() }} PO(s)
        </p>
    </div>
</div>
@endif

@if(!in_array($productRequest->status->value, ['completed', 'cancelled']))
<div class="admin-card">
    <div class="admin-card-body d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('ho.product-requests.status', $productRequest) }}">@csrf
            <input type="hidden" name="status" value="in_progress">
            <button class="btn btn-warning btn-sm">Mark In Progress</button>
        </form>
        <form method="POST" action="{{ route('ho.product-requests.status', $productRequest) }}">@csrf
            <input type="hidden" name="status" value="completed">
            <button class="btn btn-success btn-sm">Mark Completed</button>
        </form>
        <form method="POST" action="{{ route('ho.product-requests.status', $productRequest) }}" class="d-flex gap-2">@csrf
            <input type="hidden" name="status" value="cancelled">
            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Cancel reason">
            <button class="btn btn-outline-danger btn-sm">Cancel</button>
        </form>
    </div>
</div>
@endif
@endsection
