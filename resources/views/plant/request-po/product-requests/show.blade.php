@extends('layouts.plant')
@section('title', 'Product Request #' . $productRequest->id)
@section('content')
    <x-admin-page-header title="Product Request #{{ $productRequest->id }}"
        subtitle="{{ $productRequest->plant->name }} · {{ $productRequest->displayRequesterName() }}">
        <div class="d-flex align-items-center gap-2">
            <x-status-badge :status="$productRequest->status" />
            @if(auth()->user()->isPurchaser() && in_array($productRequest->status->value, ['submitted', 'in_progress']))
                <a href="{{ route('plant.product-requests.edit', $productRequest) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
            @endif
            <a href="{{ route('plant.product-requests.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
        </div>
    </x-admin-page-header>

    @if($productRequest->remarks)
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <p class="mb-0 small"><strong>Remarks:</strong> {{ $productRequest->remarks }}</p>
            </div>
        </div>
    @endif

    <div class="admin-card admin-table-card mb-4">
        <div class="admin-card-header">
            <h2>Requested Items</h2>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Item Type</th>
                        <th>Item Name</th>
                        <th>Size</th>
                        @if(auth()->user()->isPurchaser())
                            <th>Supplier</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">GST %</th>
                        @endif
                        <th class="text-end">Quantity</th>
                        <th>Status</th>
                        <th>Vehicle No / Machine No</th>
                        <th>Image</th>
                        <th class="text-end">In Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productRequest->items as $item)
                        <tr>
                            <td>{{ $item->subCategory?->name ?? $item->product?->subCategory?->name ?? '—' }}</td>
                            <td>
                                {{ $item->displayName() }}
                                @if($item->product?->sku)
                                    <span class="d-block small text-muted text-uppercase">SKU: {{ $item->product->sku }}</span>
                                @elseif(!$item->product_id)
                                    <span class="d-block small text-muted">Not in catalog</span>
                                    @if(auth()->user()->canManagePlantProducts())
                                        <a href="{{ route('plant.products.create', [
                                            'name' => $item->displayName(),
                                            'size' => $item->size,
                                            'category_id' => $item->subCategory?->category_id ?? $item->product?->category_id,
                                            'sub_category_id' => $item->sub_category_id,
                                            'product_request_id' => $productRequest->id,
                                            'product_request_item_id' => $item->id,
                                        ]) }}"
                                            class="d-inline-flex align-items-center gap-1 small text-primary mt-1">
                                            <i class="bi bi-plus-circle"></i>Add Product
                                        </a>
                                    @endif
                                @endif
                            </td>
                            <td>{{ $item->size ?? $item->product?->size ?? '—' }}</td>
                            @if(auth()->user()->isPurchaser())
                                <td>{{ $item->supplier?->name ?? '—' }}</td>
                                <td class="text-end">{{ $item->unit_price }}</td>
                                <td class="text-end">{{ $item->gst_rate }}%</td>
                            @endif
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td><x-status-badge :status="$item->workflowStatus()" /></td>
                            <td>{{ $item->remarks ?? '—' }}</td>
                            <td>
                                @if($item->image_path)
                                    <a href="{{ $item->image_url }}" target="_blank"
                                        class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 0.75rem;">
                                        <i class="bi bi-image me-1"></i>View
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                @if($item->product_id)
                                    <span class="stock-check"
                                        data-url="{{ route('plant.api.stock.check', ['plant' => $productRequest->plant_id, 'product' => $item->product_id]) }}">…</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if(auth()->user()->isPurchaser())
        @php
            $remainingItems = $productRequest->items->filter(fn ($item) => $item->canSelectForPo());
            $hasRemaining = $remainingItems->isNotEmpty();
        @endphp
        @if($hasRemaining && $productRequest->status !== \App\Enums\RequestStatus::Cancelled)
            <div class="admin-card mb-4 bg-light">
                <div class="admin-card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">Create Quotation</h5>
                            <p class="mb-0 text-muted small">
                                {{ $remainingItems->count() }} product(s) still need quotation. Export selected products from the list, or convert remaining items here.
                            </p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('plant.product-requests.export-supplier', $productRequest) }}" class="btn btn-admin-outline btn-sm">
                                <i class="bi bi-file-earmark-excel me-1"></i>Export remaining
                            </a>
                            <form method="POST" action="{{ route('plant.product-requests.convert', $productRequest) }}">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="bi bi-file-earmark-text me-1"></i>Create Quotation (remaining)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($productRequest->purchaseRequest)
            @php
                $linkedDocs = $productRequest->purchaseRequest->purchases;
                $linkedQuotations = $linkedDocs->filter(fn ($doc) => $doc->isQuotation());
                $linkedOrders = $linkedDocs->filter(fn ($doc) => $doc->isPurchaseOrder());
            @endphp
            <div class="admin-card mb-4 bg-light border-success">
                <div class="admin-card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1 text-success"><i class="bi bi-check-circle-fill me-2"></i>Quotation / PO started</h5>
                            <p class="mb-0 text-muted small">
                                Continue from Purchases — create quotation or PO for remaining / converted items.
                            </p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @if($linkedOrders->isNotEmpty())
                                @if($linkedOrders->count() === 1)
                                    @php $po = $linkedOrders->first(); @endphp
                                    <a href="{{ route('plant.purchases.show', $po) }}" class="btn btn-admin-outline btn-sm">
                                        View PO: <code>{{ $po->reference_no }}</code>
                                    </a>
                                @else
                                    <a href="{{ route('plant.purchases.index', ['purchase_request_id' => $productRequest->purchaseRequest->id]) }}"
                                        class="btn btn-admin-outline btn-sm">
                                        View {{ $linkedOrders->count() }} POs
                                    </a>
                                @endif
                            @elseif($linkedQuotations->isNotEmpty())
                                @php $quote = $linkedQuotations->first(); @endphp
                                <a href="{{ route('plant.purchases.create', ['quotation_id' => $quote->id]) }}" class="btn btn-warning btn-sm">
                                    <i class="bi bi-cart-plus me-1"></i>Create PO
                                </a>
                                <a href="{{ route('plant.purchases.show', $quote) }}" class="btn btn-admin-outline btn-sm">
                                    View Quotation
                                </a>
                            @else
                                <a href="{{ route('plant.purchases.create', ['purchase_request_id' => $productRequest->purchaseRequest->id]) }}"
                                    class="btn btn-warning btn-sm">
                                    <i class="bi bi-file-earmark-text me-1"></i>Create Quotation
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @elseif($productRequest->purchaseRequest)
        <a href="{{ route('plant.purchases.create', ['purchase_request_id' => $productRequest->purchaseRequest->id]) }}"
            class="btn btn-admin-outline btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>Continue Quotation / PO
        </a>
    @endif

    @push('scripts')
        <script>
            document.querySelectorAll('.stock-check').forEach(el => {
                fetch(el.dataset.url, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json()).then(d => el.textContent = d.quantity ?? '0').catch(() => el.textContent = '—');
            });
        </script>
    @endpush
@endsection