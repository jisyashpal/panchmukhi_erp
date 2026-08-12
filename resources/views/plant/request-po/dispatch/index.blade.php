@extends('layouts.plant')
@section('title', 'Order Dispatch')
@section('content')
<x-admin-page-header title="Order Dispatch" subtitle="Set product-wise dispatch date, mark not-provided items, or choose alternatives">
    <div class="btn-group btn-group-sm">
        <a href="{{ route('plant.dispatch.index') }}" class="btn btn-admin-outline @if(!request('filter')) active @endif">All</a>
        <a href="{{ route('plant.dispatch.index', ['filter' => 'pending']) }}" class="btn btn-admin-outline @if(request('filter')==='pending') active @endif">Pending</a>
        <a href="{{ route('plant.dispatch.index', ['filter' => 'dispatched']) }}" class="btn btn-admin-outline @if(request('filter')==='dispatched') active @endif">Dispatched</a>
    </div>
</x-admin-page-header>
<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>PO Ref</th>
                    @if($isPurchaser)
                        <th>Plant</th>
                    @endif
                    <th>Supplier</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Dispatched</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $p)
                <tr>
                    <td class="fw-semibold">{{ $p->reference_no }}</td>
                    @if($isPurchaser)
                        <td>{{ $p->plant->name }}</td>
                    @endif
                    <td>{{ $p->supplier->name }}</td>
                    <td>{{ $p->items->count() }}</td>
                    <td><x-status-badge :status="$p->status" /></td>
                    <td>
                        @if($p->dispatched_at)
                            <span class="text-success">{{ $p->dispatched_at->format('d M Y') }}</span>
                        @else
                            <span class="text-muted">Pending</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('plant.dispatch.show', $p) }}" class="btn btn-sm {{ $p->dispatched_at ? 'btn-admin-outline' : 'btn-success' }}">
                            <i class="bi bi-{{ $p->dispatched_at ? 'eye' : 'truck' }} me-1"></i>
                            {{ $p->dispatched_at ? 'View' : 'Dispatch' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $isPurchaser ? 7 : 6 }}"><div class="admin-empty">No orders</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $purchases->links() }}</div>
@endsection
