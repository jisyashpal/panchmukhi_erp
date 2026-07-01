@extends('layouts.site')
@section('title', 'Product Requests')
@section('content')
<x-admin-page-header title="Product Requests" :subtitle="$isPurchaser ? 'Review requests and create purchase orders' : 'Request products for your plant'">
    @if(auth()->user()->canCreateProductRequests())
        <a href="{{ route('site.product-requests.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Request
        </a>
    @endif
</x-admin-page-header>

<div class="admin-filters mb-0">
    @include('partials.status-tabs', [
        'route' => 'site.product-requests.index',
        'param' => 'status',
        'tabs' => [
            ['label' => 'All', 'key' => null],
            ['label' => 'Submitted', 'key' => 'submitted'],
            ['label' => 'In Progress', 'key' => 'in_progress'],
            ['label' => 'Completed', 'key' => 'completed'],
            ['label' => 'Cancelled', 'key' => 'cancelled'],
        ],
    ])
    <form method="GET" class="row g-3 mb-3">
        @if(request('status'))
            <input type="hidden" name="status" value="{{ request('status') }}">
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
    </form>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    @if($isPurchaser)
                        <th>Site</th>
                    @endif
                    <th>Requested By</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productRequests as $pr)
                <tr>
                    <td>#{{ $pr->id }}</td>
                    @if($isPurchaser)
                        <td class="fw-semibold">{{ $pr->site->name }}</td>
                    @endif
                    <td>{{ $pr->requester?->name ?? 'N/A' }}</td>
                    <td><x-status-badge :status="$pr->status" /></td>
                    <td>{{ $pr->created_at->format('d M Y') }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2 align-items-center justify-content-end flex-wrap">
                            @if($pr->status === \App\Enums\RequestStatus::Submitted)
                                <span class="badge bg-danger" style="font-size: 0.65rem;">New</span>
                            @endif

                            @if($isPurchaser)
                                @if($pr->purchaseRequest?->purchases->isNotEmpty())
                                    @if($pr->purchaseRequest->purchases->count() === 1)
                                        <a href="{{ route('site.purchases.show', $pr->purchaseRequest->purchases->first()) }}" class="btn btn-sm btn-admin-outline" title="View purchase order">
                                            <i class="bi bi-receipt me-1"></i>PO
                                        </a>
                                    @else
                                        <a href="{{ route('site.purchases.index', ['purchase_request_id' => $pr->purchaseRequest->id]) }}" class="btn btn-sm btn-admin-outline" title="View purchase orders">
                                            <i class="bi bi-receipt me-1"></i>{{ $pr->purchaseRequest->purchases->count() }} POs
                                        </a>
                                    @endif
                                @elseif(!in_array($pr->status->value, ['cancelled']))
                                    @if($pr->purchaseRequest)
                                        <a href="{{ route('site.purchases.create', ['purchase_request_id' => $pr->purchaseRequest->id]) }}" class="btn btn-sm btn-warning">
                                            <i class="bi bi-cart-plus me-1"></i>Create PO
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('site.product-requests.convert', $pr) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <i class="bi bi-cart-plus me-1"></i>Create PO
                                            </button>
                                        </form>
                                    @endif
                                @endif
                                @if(in_array($pr->status->value, ['submitted', 'in_progress']))
                                    <a href="{{ route('site.product-requests.edit', $pr) }}" class="btn btn-sm btn-primary">
                                        Edit
                                    </a>
                                @endif
                            @endif

                            <a href="{{ route('site.product-requests.show', $pr) }}" class="btn btn-sm btn-admin-outline" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                    @include('partials.table-empty', ['colspan' => $isPurchaser ? 6 : 5, 'icon' => 'bi-clipboard-check', 'message' => 'No product requests'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $productRequests->links() }}</div>
@endsection
