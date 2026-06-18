@extends('layouts.ho')
@section('title', 'Product Requests')
@section('content')
<x-admin-page-header title="Product Requests" subtitle="Edit rate, quantity, supplier and billing details" />

<x-list-filters :sites="$sites" :products="$products" :showStatus="true" />

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Site</th>
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
                    <td><x-status-badge :status="$pr->status" /></td>
                    <td>{{ $pr->created_at->format('d M Y') }}</td>
                    <td class="text-end">
                        <a href="{{ route('ho.product-requests.show', $pr) }}" class="btn btn-sm btn-admin-outline">View</a>
                        <a href="{{ route('ho.product-requests.edit', $pr) }}" class="btn btn-sm btn-primary">Edit</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5"><div class="admin-empty">No product requests</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $productRequests->links() }}</div>
@endsection
