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

@if($purchaseRequest->purchase)
<p class="mt-3"><span class="badge bg-success">PO {{ $purchaseRequest->purchase->reference_no }}</span></p>
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
