@extends('layouts.ho')
@section('title', 'Product Requests')
@section('content')
<x-admin-page-header title="Product Requests" subtitle="Edit rate, quantity, supplier and billing details">
    <a href="{{ route('ho.product-requests.create') }}" class="btn btn-admin-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Request
    </a>
</x-admin-page-header>

<x-list-filters :sites="$sites" :products="$products" :showStatus="true" />

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Site</th>
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
                    <td class="fw-semibold">{{ $pr->site->name }}</td>
                    <td>{{ $pr->requester?->name ?? 'N/A' }}</td>
                    <td><x-status-badge :status="$pr->status" /></td>
                    <td>{{ $pr->created_at->format('d M Y') }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2 align-items-center justify-content-end flex-wrap">
                            @if($pr->status === \App\Enums\RequestStatus::Submitted)
                                <span class="badge bg-danger" style="font-size: 0.65rem;">New</span>
                            @endif
                            @if($pr->purchaseRequest?->purchases->isNotEmpty())
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-receipt me-1"></i>PO created
                                </span>
                            @endif
                            <a href="{{ route('ho.product-requests.show', $pr) }}" class="btn btn-sm btn-admin-outline" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('ho.product-requests.edit', $pr) }}" class="btn btn-sm btn-primary">Edit</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="admin-empty">No product requests</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $productRequests->links() }}</div>
@endsection
