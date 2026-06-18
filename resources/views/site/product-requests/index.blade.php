@extends('layouts.site')
@section('title', 'Product Requests')
@section('content')
<x-admin-page-header title="Product Requests" subtitle="Request products for your plant">
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
                    <td>{{ $pr->requester->name }}</td>
                    <td><x-status-badge :status="$pr->status" /></td>
                    <td>{{ $pr->created_at->format('d M Y') }}</td>
                    <td class="text-end">
                        <a href="{{ route('site.product-requests.show', $pr) }}" class="btn btn-sm btn-admin-outline">View</a>
                    </td>
                </tr>
                @empty
                    @include('partials.table-empty', ['colspan' => 5, 'icon' => 'bi-clipboard-check', 'message' => 'No product requests'])
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $productRequests->links() }}</div>
@endsection
