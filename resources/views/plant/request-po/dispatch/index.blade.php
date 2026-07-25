@extends('layouts.plant')
@section('title', 'Order Dispatch')
@section('content')
<x-admin-page-header title="Order Dispatch" subtitle="Mark purchase orders as dispatched">
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
                    <th>Status</th>
                    <th>Dispatched</th>
                    <th class="text-end"> Actions</th>
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
                    <td><x-status-badge :status="$p->status" /></td>
                    <td>{{ $p->dispatched_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-end">
                        @if(!$p->dispatched_at && $p->status->value === 'ordered')
                        <form method="POST" action="{{ route('plant.dispatch.store', $p) }}"
                            class="d-inline-flex align-items-center gap-2 justify-content-end flex-wrap">
                            @csrf
                            <input type="date" name="dispatched_at" value="{{ old('dispatched_at', now()->format('Y-m-d')) }}"
                                class="form-control form-control-sm" style="width: auto;" required
                                title="Dispatch date">
                            <button type="submit" class="btn btn-success btn-sm">Dispatch</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $isPurchaser ? 6 : 5 }}"><div class="admin-empty">No orders</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $purchases->links() }}</div>
@endsection
