@extends('layouts.site')
@section('title', 'Purchase Requests')
@section('content')
<x-admin-page-header title="Purchase Requests" subtitle="Track requests sent to Head Office">
    @if(auth()->user()->canManagePurchaseRequests())
        <a href="{{ route('site.purchase-requests.create') }}" class="btn btn-admin-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Request
        </a>
    @endif
</x-admin-page-header>

<div class="admin-filters mb-0">
    @include('partials.status-tabs', [
        'route' => 'site.purchase-requests.index',
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
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseRequests as $pr)
                <tr>
                    <td>#{{ $pr->id }}</td>
                    <td>{{ $pr->product_request_id ? 'From product request' : 'Direct' }}</td>
                    <td><x-status-badge :status="$pr->status" /></td>
                    <td>{{ $pr->created_at->format('d M Y') }}</td>
                    <td class="text-end">
                        <a href="{{ route('site.purchase-requests.show', $pr) }}" class="btn btn-sm btn-admin-outline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="admin-empty"><i class="bi bi-cart-check d-block"></i>No purchase requests</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $purchaseRequests->links() }}</div>
@endsection
