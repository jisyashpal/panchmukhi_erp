@extends('layouts.plant')
@section('title', 'Product Requests')
@section('content')
<x-admin-page-header title="Product Requests" :subtitle="$isPurchaser ? 'Single or multi-select (same plant) to generate quotation' : 'Request products for your plant'">
    @if(auth()->user()->canCreateProductRequests())
        <a href="{{ route('plant.product-requests.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Request
        </a>
    @endif
</x-admin-page-header>

<div class="admin-filters mb-0">
    @include('partials.status-tabs', [
        'route' => 'plant.product-requests.index',
        'param' => 'status',
        'tabs' => [
            ['label' => 'All', 'key' => null],
            ['label' => 'Submitted', 'key' => 'submitted'],
            ['label' => 'In Progress', 'key' => 'in_progress'],
            ['label' => 'Completed', 'key' => 'completed'],
            ['label' => 'Cancelled', 'key' => 'cancelled'],
        ],
    ])
    <form method="GET" class="row g-3 mb-3 align-items-end">
        @if(request('status'))
            <input type="hidden" name="status" value="{{ request('status') }}">
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
        <div class="col-md-4">
            <label class="form-label small">Product</label>
            <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All products</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        @if(request()->anyFilled(['plant_id', 'product_id', 'status']))
        <div class="col-auto">
            <a href="{{ route('plant.product-requests.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
        </div>
        @endif
    </form>
</div>

@if($isPurchaser)
{{-- Bulk form stays outside the table so per-row single convert forms work --}}
<form method="POST" action="{{ route('plant.product-requests.bulk-convert') }}" id="bulk-quotation-form" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    @csrf
    <button type="submit" class="btn btn-warning btn-sm" id="bulk-generate-btn" disabled>
        <i class="bi bi-file-earmark-ruled me-1"></i>Generate Quotation (selected)
    </button>
    <span class="small text-muted" id="bulk-selection-hint">Tick one or more requests from the same plant, or use Create Quotation on a single row</span>
</form>
@endif

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    @if($isPurchaser)
                        <th style="width: 2.5rem">
                            <input type="checkbox" class="form-check-input" id="select-all-requests" title="Select all eligible">
                        </th>
                    @endif
                    <th>#</th>
                    @if($isPurchaser)
                        <th>Plant</th>
                    @endif
                    <th>Requested By</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productRequests as $pr)
                @php
                    $hasPurchases = $pr->purchaseRequest?->purchases->isNotEmpty();
                    $canSelect = $isPurchaser
                        && ! $hasPurchases
                        && $pr->status !== \App\Enums\RequestStatus::Cancelled
                        && ! $pr->purchaseRequest;
                @endphp
                <tr>
                    @if($isPurchaser)
                        <td>
                            @if($canSelect)
                                <input
                                    type="checkbox"
                                    class="form-check-input request-select"
                                    form="bulk-quotation-form"
                                    name="product_request_ids[]"
                                    value="{{ $pr->id }}"
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
                    <td>{{ $pr->displayRequesterName() }}</td>
                    <td>
                        <span class="fw-semibold">{{ $pr->items_count }} items</span>
                        <span class="d-block small text-muted">Qty {{ number_format((float) ($pr->items_sum_quantity ?? 0), 2) }}</span>
                    </td>
                    <td><x-status-badge :status="$pr->status" /></td>
                    <td>{{ $pr->created_at->format('d M Y') }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2 align-items-center justify-content-end flex-wrap">
                            @if($pr->status === \App\Enums\RequestStatus::Submitted)
                                <span class="badge bg-danger" style="font-size: 0.65rem;">New</span>
                            @endif

                            @if($isPurchaser)
                                @if($hasPurchases)
                                    @if($pr->purchaseRequest->purchases->count() === 1)
                                        <a href="{{ route('plant.purchases.show', $pr->purchaseRequest->purchases->first()) }}" class="btn btn-sm btn-admin-outline" title="View quotation / PO">
                                            <i class="bi bi-receipt me-1"></i>PO
                                        </a>
                                    @else
                                        <a href="{{ route('plant.purchases.index', ['purchase_request_id' => $pr->purchaseRequest->id]) }}" class="btn btn-sm btn-admin-outline" title="View purchase orders">
                                            <i class="bi bi-receipt me-1"></i>{{ $pr->purchaseRequest->purchases->count() }} POs
                                        </a>
                                    @endif
                                @elseif($pr->purchaseRequest)
                                    <a href="{{ route('plant.purchases.create', ['purchase_request_id' => $pr->purchaseRequest->id]) }}" class="btn btn-sm btn-warning">
                                        <i class="bi bi-file-earmark-ruled me-1"></i>Create Quotation
                                    </a>
                                @elseif($pr->status === \App\Enums\RequestStatus::Completed)
                                    <span class="badge bg-secondary-subtle text-secondary border">In site quotation</span>
                                @elseif($pr->status !== \App\Enums\RequestStatus::Cancelled)
                                    <form method="POST" action="{{ route('plant.product-requests.convert', $pr) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <i class="bi bi-file-earmark-ruled me-1"></i>Create Quotation
                                        </button>
                                    </form>
                                @endif
                                @if(in_array($pr->status->value, ['submitted', 'in_progress']))
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
                    @include('partials.table-empty', ['colspan' => $isPurchaser ? 8 : 6, 'icon' => 'bi-clipboard-check', 'message' => 'No product requests'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $productRequests->links() }}</div>
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
    const hint = document.getElementById('bulk-selection-hint');

    function sync() {
        const checked = boxes().filter((el) => el.checked);
        const plantIds = [...new Set(checked.map((el) => el.dataset.plantId))];
        const samePlant = plantIds.length <= 1;
        btn.disabled = checked.length === 0 || !samePlant;

        if (checked.length === 0) {
            hint.textContent = 'Tick one or more requests from the same plant, or use Create Quotation on a single row';
            hint.classList.remove('text-danger');
        } else if (!samePlant) {
            hint.textContent = 'Selected requests must be from the same plant/site.';
            hint.classList.add('text-danger');
        } else {
            hint.classList.remove('text-danger');
            const name = checked[0].dataset.plantName || 'site';
            hint.textContent = `${checked.length} selected from ${name} — quotation no. will use plant name (e.g. piska-01)`;
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

    form.addEventListener('submit', (e) => {
        const checked = boxes().filter((el) => el.checked);
        const plantIds = [...new Set(checked.map((el) => el.dataset.plantId))];
        if (checked.length === 0) {
            e.preventDefault();
            return;
        }
        if (plantIds.length > 1) {
            e.preventDefault();
            alert('Select product requests from one plant/site only.');
            return;
        }
        const label = checked.length === 1 ? '1 request' : `${checked.length} requests`;
        if (!confirm(`Generate quotation for ${label}?`)) {
            e.preventDefault();
        }
    });

    sync();
})();
</script>
@endpush
@endif
