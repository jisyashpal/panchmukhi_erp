@extends('layouts.ho')
@section('title', 'Purchase List')
@section('content')
<x-admin-page-header title="Product Purchase List" subtitle="Supplier-wise totals">
    <form method="GET" class="d-flex gap-2">
        <select name="site_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All sites</option>
            @foreach($sites as $s)<option value="{{ $s->id }}" @selected($siteId==$s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </form>
</x-admin-page-header>
<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Supplier</th><th class="text-end">Orders</th><th class="text-end">Total Amount</th></tr></thead>
            <tbody>
                @forelse($supplierTotals as $row)
                <tr>
                    <td>{{ $row->supplier?->name ?? 'Unknown' }}</td>
                    <td class="text-end">{{ $row->order_count }}</td>
                    <td class="text-end fw-semibold">₹{{ number_format($row->total_amount, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3"><div class="admin-empty">No purchases</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
