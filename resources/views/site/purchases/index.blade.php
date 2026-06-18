@extends('layouts.site')
@section('title', 'Receive Stock')
@section('content')
<x-admin-page-header title="Receive Stock (PO)" subtitle="Confirm delivery and record stock in" />

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $purchase)
                <tr>
                    <td class="fw-semibold">{{ $purchase->reference_no }}</td>
                    <td>{{ $purchase->supplier->name }}</td>
                    <td><span class="badge bg-secondary">{{ $purchase->status->label() }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('site.purchases.receive', $purchase) }}" class="btn btn-sm btn-success">Receive</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4">
                        <div class="admin-empty"><i class="bi bi-truck d-block"></i>No purchases awaiting receipt</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $purchases->links() }}</div>
@endsection
