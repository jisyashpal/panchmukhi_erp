@extends('layouts.site')
@section('title', 'Purchase Request #'.$purchaseRequest->id)
@section('content')
<x-admin-page-header title="Purchase Request #{{ $purchaseRequest->id }}" subtitle="Track request status">
    <div class="d-flex gap-2">
        <x-status-badge :status="$purchaseRequest->status" />
        @if($canEdit)
        <a href="{{ route('site.purchase-requests.edit', $purchaseRequest) }}" class="btn btn-primary btn-sm">Edit / Modify</a>
        @endif
        <a href="{{ route('site.purchase-requests.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
    </div>
</x-admin-page-header>

<div class="admin-card admin-table-card mb-4">
    <div class="admin-card-header"><h2>Items</h2></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Product</th><th>Supplier</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Stock</th></tr></thead>
            <tbody>
                @foreach($purchaseRequest->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->supplier?->name ?? '—' }}</td>
                    <td class="text-end">{{ $item->quantity }}</td>
                    <td class="text-end">{{ $item->unit_price }}</td>
                    <td class="text-end">
                        <span class="stock-check" data-url="{{ route('site.api.stock.check', ['site' => $purchaseRequest->site_id, 'product' => $item->product_id]) }}">…</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($canEdit && !in_array($purchaseRequest->status->value, ['completed', 'cancelled']))
<div class="admin-card">
    <div class="admin-card-body d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('site.purchase-requests.status', $purchaseRequest) }}">@csrf
            <input type="hidden" name="status" value="in_progress"><button class="btn btn-warning btn-sm">Confirm In Progress</button>
        </form>
        <form method="POST" action="{{ route('site.purchase-requests.status', $purchaseRequest) }}">@csrf
            <input type="hidden" name="status" value="completed"><button class="btn btn-success btn-sm">Mark Completed</button>
        </form>
        <form method="POST" action="{{ route('site.purchase-requests.status', $purchaseRequest) }}" class="d-flex gap-2">@csrf
            <input type="hidden" name="status" value="cancelled">
            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Cancel reason">
            <button class="btn btn-outline-danger btn-sm">Cancel</button>
        </form>
    </div>
</div>
@endif

@if($purchaseRequest->purchases->isNotEmpty())
    <div class="mt-3 p-3 bg-light border rounded">
        <span class="text-muted d-block mb-2">Associated Purchase Order(s):</span>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            @foreach($purchaseRequest->purchases as $po)
                <div class="d-flex align-items-center gap-2 border rounded px-2 py-1 bg-white">
                    <strong class="text-dark">{{ $po->reference_no }}</strong>
                    <span class="badge bg-secondary">{{ $po->status->label() }}</span>
                    <small class="text-muted">{{ $po->supplier->name }}</small>
                    @if(auth()->user()->isPurchaser() && in_array($po->status->value, ['ordered', 'partial']))
                        <a href="{{ route('site.purchases.edit', $po) }}" class="btn btn-sm btn-success text-white">
                            <i class="bi bi-pencil-square me-1"></i>Edit PO
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
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
