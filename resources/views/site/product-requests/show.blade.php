@extends('layouts.site')
@section('title', 'Product Request #'.$productRequest->id)
@section('content')
<x-admin-page-header title="Product Request #{{ $productRequest->id }}" subtitle="{{ $productRequest->requester->name }}">
    <div class="d-flex align-items-center gap-2">
        <x-status-badge :status="$productRequest->status" />
        <a href="{{ route('site.product-requests.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
    </div>
</x-admin-page-header>

@if($productRequest->remarks)
<div class="admin-card mb-4"><div class="admin-card-body"><p class="mb-0 small"><strong>Remarks:</strong> {{ $productRequest->remarks }}</p></div></div>
@endif

<div class="admin-card admin-table-card mb-4">
    <div class="admin-card-header"><h2>Requested Items</h2></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Product</th><th class="text-end">Quantity</th><th class="text-end">In Stock</th></tr></thead>
            <tbody>
                @foreach($productRequest->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td class="text-end">{{ $item->quantity }}</td>
                    <td class="text-end"><span class="stock-check" data-url="{{ route('site.api.stock.check', ['site' => $productRequest->site_id, 'product' => $item->product_id]) }}">…</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($productRequest->purchaseRequest)
<a href="{{ route('site.purchase-requests.show', $productRequest->purchaseRequest) }}" class="btn btn-admin-outline btn-sm">View Purchase Request</a>
@endif

@push('scripts')
<script>
document.querySelectorAll('.stock-check').forEach(el => {
    fetch(el.dataset.url, {headers: {'Accept': 'application/json'}})
        .then(r => r.json()).then(d => el.textContent = d.quantity ?? '0').catch(() => el.textContent = '—');
});
</script>
@endpush
@endsection
