@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Product Request #' . $productRequest->id)
@section('content')
    <x-admin-page-header title="Product Request #{{ $productRequest->id }}"
        subtitle="{{ $productRequest->plant->name }} · {{ $productRequest->displayRequesterName() }}">
        <div class="d-flex gap-2">
            <x-status-badge :status="$productRequest->status" />
            <a href="{{ route('ho.product-requests.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
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
                        <th>Supplier</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">GST %</th>
                        <th>Remark</th>
                        <th>Image</th>
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
                                    <span class="d-block small text-muted">Custom item</span>
                                @endif
                            </td>
                            <td>{{ $item->size ?? $item->product?->size ?? '—' }}</td>
                            <td>{{ $item->supplier?->name ?? '—' }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">{{ $item->unit_price }}</td>
                            <td class="text-end">{{ $item->gst_rate }}%</td>
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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($productRequest->purchaseRequest?->purchases->isNotEmpty())
        <div class="admin-card mb-4 bg-light border-success">
            <div class="admin-card-body">
                <h5 class="mb-1 text-success"><i class="bi bi-check-circle-fill me-2"></i>Purchase order created by purchaser
                </h5>
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
                <form method="POST" action="{{ route('ho.product-requests.status', $productRequest) }}" class="d-flex gap-2">
                    @csrf
                    <input type="hidden" name="status" value="cancelled">
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Cancel reason">
                    <button class="btn btn-outline-danger btn-sm">Cancel</button>
                </form>
            </div>
        </div>
    @endif
@endsection