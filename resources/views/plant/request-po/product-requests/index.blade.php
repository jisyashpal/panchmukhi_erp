@extends('layouts.plant')
@section('title', 'Product Requests')
@section('content')
@php
    $activeStatus = $activeStatus ?? request('status');
@endphp
<x-admin-page-header title="Product Requests" :subtitle="$isPurchaser ? 'Product-wise list — select same plant to export or create quotation' : 'Request products for your plant'">
    @if(auth()->user()->canCreateProductRequests())
        <a href="{{ route('plant.product-requests.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Request
        </a>
    @endif
</x-admin-page-header>

<div class="admin-filters mb-3">
    @include('partials.status-tabs', [
        'route' => 'plant.product-requests.index',
        'param' => 'status',
        'tabs' => [
            ['label' => 'All', 'key' => 'all'],
            ['label' => 'Send Quotation', 'key' => 'send_quotation'],
            ['label' => 'Create PO', 'key' => 'create_po'],
            ['label' => 'Dispatch', 'key' => 'dispatch'],
            ['label' => 'PENDING', 'key' => 'pending'],
        ],
        'active' => $activeStatus === '' ? 'all' : $activeStatus,
    ])
    <form method="GET" class="row g-3 mb-3 align-items-end">
        @if($activeStatus !== null && $activeStatus !== '')
            <input type="hidden" name="status" value="{{ $activeStatus }}">
        @elseif(request('status') === 'all')
            <input type="hidden" name="status" value="all">
        @endif
        @if($isPurchaser)
        <div class="col-md-3">
            <label class="form-label small">Plant / Site</label>
            <select name="plant_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All plants</option>
                @foreach($plants as $plant)
                    <option value="{{ $plant->id }}" @selected(request('plant_id') == $plant->id)>{{ $plant->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-md-3">
            <label class="form-label small">Product</label>
            <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All products</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">From Date</label>
            <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm" onchange="this.form.submit()">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">To Date</label>
            <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm" onchange="this.form.submit()">
        </div>
        @if(request()->anyFilled(['plant_id', 'product_id', 'status', 'from_date', 'to_date']))
        <div class="col-auto">
            <a href="{{ route('plant.product-requests.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
        </div>
        @endif
    </form>
</div>

@if($isPurchaser)
<form method="POST" action="{{ route('plant.product-requests.bulk-convert') }}" id="bulk-quotation-form">
    @csrf
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <button type="submit"
                class="btn btn-danger btn-sm"
                id="bulk-export-btn"
                formaction="{{ route('plant.product-requests.bulk-export-supplier') }}"
                disabled>
            <i class="bi bi-file-earmark-excel me-1"></i>Export for Supplier
        </button>
        <button type="submit" class="btn btn-warning btn-sm" id="bulk-generate-btn" disabled>
            <i class="bi bi-file-earmark-text me-1"></i>Create Quotation (selected)
        </button>
        <span class="small text-muted" id="bulk-selection-hint">Select products. Export for supplier first, then create quotation. Same plant only.</span>
    </div>
@endif

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    @if($isPurchaser)
                        <th style="width: 2.5rem">
                            <input type="checkbox" class="form-check-input" id="select-all-requests" title="Select all eligible products">
                        </th>
                    @endif
                    <th>#</th>
                    @if($isPurchaser)
                        <th>Plant</th>
                    @endif
                    <th>Product</th>
                    <th>Size</th>
                    <th class="text-end">Qty</th>
                    <th>Unit</th>
                    <th>Requested By</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                @php
                    $pr = $item->productRequest;
                    $workflow = $item->workflowStatus();
                    $canSelect = $isPurchaser && $item->canSelectForPo();
                    $quotations = $item->relatedQuotations();
                    $purchaseOrders = $item->relatedPurchaseOrders();
                    $primaryQuotation = $quotations->first();
                    $primaryPurchase = $purchaseOrders->first();
                    $pendingDispatch = $purchaseOrders->first(fn ($purchase) => $purchase->dispatched_at === null) ?? $primaryPurchase;
                @endphp
                <tr>
                    @if($isPurchaser)
                        <td>
                            @if($canSelect)
                                <input
                                    type="checkbox"
                                    class="form-check-input request-select"
                                    name="product_request_item_ids[]"
                                    value="{{ $item->id }}"
                                    data-plant-id="{{ $pr->plant_id }}"
                                    data-plant-name="{{ $pr->plant->name }}"
                                >
                            @endif
                        </td>
                    @endif
                    <td>#{{ $pr->id }}</td>
                    @if($isPurchaser)
                        <td class="fw-semibold">{{ $pr->plant->name }}</td>
                    @endif
                    <td>
                        <span class="fw-semibold">{{ $item->displayName() }}</span>
                        @if($item->subCategory?->name || $item->product?->subCategory?->name)
                            <span class="d-block small text-muted">{{ $item->subCategory?->name ?? $item->product?->subCategory?->name }}</span>
                        @endif
                    </td>
                    <td>{{ $item->size ?? $item->product?->size ?? '—' }}</td>
                    <td class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td>{{ $item->displayUnit() }}</td>
                    <td>{{ $pr->displayRequesterName() }}</td>
                    <td><x-status-badge :status="$workflow" /></td>
                    <td>{{ $pr->created_at->format('d M Y') }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2 align-items-center justify-content-end flex-wrap">
                            @if($isPurchaser)
                                @if($workflow === \App\Enums\ProductRequestItemWorkflowStatus::SendQuotation)
                                    <button type="submit"
                                            class="btn btn-sm btn-admin-outline js-single-product"
                                            formaction="{{ route('plant.product-requests.bulk-export-supplier') }}"
                                            data-item-id="{{ $item->id }}"
                                            title="Export this product for supplier">
                                        <i class="bi bi-file-earmark-excel me-1"></i>Export
                                    </button>
                                    <button type="submit"
                                            class="btn btn-sm btn-warning js-single-product"
                                            data-item-id="{{ $item->id }}">
                                        <i class="bi bi-file-earmark-text me-1"></i>Create Quotation
                                    </button>
                                @elseif($workflow === \App\Enums\ProductRequestItemWorkflowStatus::CreatePo && $primaryQuotation)
                                    <a href="{{ route('plant.purchases.create', ['quotation_id' => $primaryQuotation->id]) }}" class="btn btn-sm btn-warning">
                                        <i class="bi bi-cart-plus me-1"></i>Create PO
                                    </a>
                                    @if($primaryQuotation->canBeEdited())
                                        <a href="{{ route('plant.purchases.edit', $primaryQuotation) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                    @endif
                                @elseif($primaryPurchase)
                                    @if($purchaseOrders->count() === 1)
                                        <a href="{{ route('plant.purchases.show', $primaryPurchase) }}" class="btn btn-sm btn-admin-outline" title="View PO">
                                            <i class="bi bi-receipt me-1"></i>PO
                                        </a>
                                    @else
                                        <a href="{{ route('plant.purchases.index', ['purchase_request_id' => $item->purchaseRequestItem?->purchase_request_id]) }}" class="btn btn-sm btn-admin-outline" title="View purchase orders">
                                            <i class="bi bi-receipt me-1"></i>{{ $purchaseOrders->count() }} POs
                                        </a>
                                    @endif
                                    @if($primaryPurchase->canBeEdited())
                                        <a href="{{ route('plant.purchases.edit', $primaryPurchase) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                    @endif
                                    @if(auth()->user()->canDispatchOrders() && $pendingDispatch)
                                        <a href="{{ route('plant.dispatch.show', $pendingDispatch) }}" class="btn btn-sm {{ $workflow === \App\Enums\ProductRequestItemWorkflowStatus::Dispatch ? 'btn-admin-outline' : 'btn-success' }}">
                                            <i class="bi bi-truck me-1"></i>{{ $workflow === \App\Enums\ProductRequestItemWorkflowStatus::Dispatch ? 'View' : 'Dispatch' }}
                                        </a>
                                    @endif
                                @endif
                                @if(in_array($pr->status->value, ['submitted', 'in_progress']) && $canSelect)
                                    <a href="{{ route('plant.product-requests.edit', $pr) }}" class="btn btn-sm btn-primary">
                                        Edit
                                    </a>
                                @endif
                            @endif

                            <a href="{{ route('plant.product-requests.show', $pr) }}" class="btn btn-sm btn-admin-outline" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                    @include('partials.table-empty', ['colspan' => $isPurchaser ? 11 : 9, 'icon' => 'bi-clipboard-check', 'message' => 'No product requests'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if($isPurchaser)
</form>
@endif
<div class="mt-3">{{ $items->links() }}</div>
@endsection

@if($isPurchaser)
@push('scripts')
<script>
(() => {
    const form = document.getElementById('bulk-quotation-form');
    if (!form) return;

    const selectAll = document.getElementById('select-all-requests');
    const boxes = () => Array.from(document.querySelectorAll('.request-select'));
    const btn = document.getElementById('bulk-generate-btn');
    const exportBtn = document.getElementById('bulk-export-btn');
    const hint = document.getElementById('bulk-selection-hint');

    function sync() {
        const checked = boxes().filter((el) => el.checked);
        const plantIds = [...new Set(checked.map((el) => el.dataset.plantId))];
        const samePlant = plantIds.length <= 1;
        const disabled = checked.length === 0 || !samePlant;
        btn.disabled = disabled;
        if (exportBtn) exportBtn.disabled = disabled;

        if (checked.length === 0) {
            hint.textContent = 'Select products. Export for supplier first, then create quotation. Same plant only.';
            hint.classList.remove('text-danger');
        } else if (!samePlant) {
            hint.textContent = 'Selected products must be from the same plant/site.';
            hint.classList.add('text-danger');
        } else {
            hint.classList.remove('text-danger');
            const name = checked[0].dataset.plantName || 'site';
            const label = checked.length === 1 ? '1 product' : `${checked.length} products`;
            hint.textContent = `${label} selected from ${name} — export for supplier, then Create Quotation`;
        }

        if (selectAll) {
            const enabled = boxes();
            selectAll.checked = enabled.length > 0 && enabled.every((el) => el.checked);
            selectAll.indeterminate = checked.length > 0 && checked.length < enabled.length;
        }
    }

    selectAll?.addEventListener('change', () => {
        const enabled = boxes();
        if (selectAll.checked && enabled.length) {
            const firstPlant = enabled[0].dataset.plantId;
            enabled.forEach((el) => {
                el.checked = el.dataset.plantId === firstPlant;
            });
        } else {
            enabled.forEach((el) => { el.checked = false; });
        }
        sync();
    });

    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('request-select')) sync();
    });

    form.addEventListener('click', (e) => {
        const single = e.target.closest('.js-single-product');
        if (!single || !form.contains(single)) return;
        const id = String(single.dataset.itemId || '');
        boxes().forEach((el) => { el.checked = el.value === id; });
        sync();
    });

    form.addEventListener('submit', (e) => {
        const checked = boxes().filter((el) => el.checked);
        const plantIds = [...new Set(checked.map((el) => el.dataset.plantId))];
        if (checked.length === 0) {
            e.preventDefault();
            return;
        }
        if (plantIds.length > 1) {
            e.preventDefault();
            alert('Select products from one plant/site only.');
            return;
        }

        const action = (e.submitter && e.submitter.getAttribute('formaction')) || form.action;
        const isExport = action.includes('export-supplier');
        if (isExport) {
            return;
        }

        const label = checked.length === 1 ? '1 product' : `${checked.length} products`;
        if (!confirm(`Create quotation for ${label}?`)) {
            e.preventDefault();
        }
    });

    sync();
})();
</script>
@endpush
@endif
