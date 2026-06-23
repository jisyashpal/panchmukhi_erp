@extends('layouts.site')
@section('title', 'Product Request #'.$productRequest->id)
@section('content')
<x-admin-page-header title="Product Request #{{ $productRequest->id }}" subtitle="{{ $productRequest->site->name }} · {{ $productRequest->requester?->name ?? 'N/A' }}">
    <div class="d-flex align-items-center gap-2">
        <x-status-badge :status="$productRequest->status" />
        @if(auth()->user()->isPurchaser() && in_array($productRequest->status->value, ['submitted', 'in_progress']))
            <a href="{{ route('site.product-requests.edit', $productRequest) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        @endif
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
            <thead>
                <tr>
                    <th>Product</th>
                    @if(auth()->user()->isPurchaser())
                        <th>Supplier</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">GST %</th>
                    @endif
                    <th class="text-end">Quantity</th>
                    <th class="text-end">In Stock</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productRequest->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    @if(auth()->user()->isPurchaser())
                        <td>{{ $item->supplier?->name ?? '—' }}</td>
                        <td class="text-end">{{ $item->unit_price }}</td>
                        <td class="text-end">{{ $item->gst_rate }}%</td>
                    @endif
                    <td class="text-end">{{ $item->quantity }}</td>
                    <td class="text-end"><span class="stock-check" data-url="{{ route('site.api.stock.check', ['site' => $productRequest->site_id, 'product' => $item->product_id]) }}">…</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if(auth()->user()->isPurchaser())
    @if($productRequest->purchaseRequest)
    <div class="admin-card mb-4 bg-light border-success">
        <div class="admin-card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-1 text-success"><i class="bi bi-check-circle-fill me-2"></i>Converted to Purchase Request</h5>
                    <p class="mb-0 text-muted small">Purchase Request: <strong>#{{ $productRequest->purchaseRequest->id }}</strong> ({{ $productRequest->purchaseRequest->status->label() }})</p>
                </div>
                <div>
                    @if($productRequest->purchaseRequest->purchases->isNotEmpty())
                        @if($productRequest->purchaseRequest->purchases->count() === 1)
                            @php $po = $productRequest->purchaseRequest->purchases->first(); @endphp
                            <a href="{{ route('site.purchases.show', $po) }}" class="btn btn-admin-outline btn-sm">
                                View PO: <code>{{ $po->reference_no }}</code>
                            </a>
                        @else
                            <a href="{{ route('site.purchases.index', ['purchase_request_id' => $productRequest->purchaseRequest->id]) }}" class="btn btn-admin-outline btn-sm">
                                View {{ $productRequest->purchaseRequest->purchases->count() }} POs
                            </a>
                        @endif
                    @else
                        <a href="{{ route('site.purchases.create', ['purchase_request_id' => $productRequest->purchaseRequest->id]) }}" class="btn btn-warning btn-sm">
                            <i class="bi bi-cart-plus me-1"></i>Create Purchase Order
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @elseif(!in_array($productRequest->status->value, ['cancelled']))
    <div class="admin-card mb-4 bg-light">
        <div class="admin-card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Create Purchase Order</h5>
                    <p class="mb-0 text-muted small">Convert this product request and generate a purchase order.</p>
                </div>
                <form method="POST" action="{{ route('site.product-requests.convert', $productRequest) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="bi bi-cart-plus me-1"></i>Create PO
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif
@elseif($productRequest->purchaseRequest)
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
