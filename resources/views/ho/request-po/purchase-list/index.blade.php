@extends('layouts.ho')
@section('title', 'Purchase List')
@section('content')
<x-admin-page-header title="Product Purchase List" subtitle="Supplier-wise totals for {{ $filters->financialYear ? 'FY '.$filters->financialYear : 'selected period' }}">
</x-admin-page-header>

<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        @if(isset($financialYear))
            <div class="col-6 col-md-2">
                <label class="form-label small">Financial Year</label>
                <x-financial-year-select compact :selected="request('fy', $financialYear->label)" />
            </div>
        @endif
        <div class="col-6 col-md-2">
            <label class="form-label small">Plant</label>
            <select name="plant_id" class="form-select form-select-sm">
                <option value="">All plants</option>
                @foreach($plants as $s)
                    <option value="{{ $s->id }}" @selected(request('plant_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">From date</label>
            <input type="date" name="date_from" value="{{ request('date_from', $financialYear->dateFrom ?? '') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">To date</label>
            <input type="date" name="date_to" value="{{ request('date_to', $financialYear->dateTo ?? '') }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
            @if(request()->anyFilled(['fy', 'plant_id', 'date_from', 'date_to']))
                <a href="{{ route('ho.purchase-list.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

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
                <tr><td colspan="3"><div class="admin-empty">No purchases for the selected financial year.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
