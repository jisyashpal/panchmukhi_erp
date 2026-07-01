@extends('layouts.site')
@section('title', 'Dashboard')
@section('content')
<x-admin-page-header title="Site Dashboard" subtitle="{{ $site->name }} — site-specific summary" />

<div class="admin-stat-grid">
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-clipboard-check"></i></div>
        <div class="stat-label">Submitted Product Requests</div>
        <div class="stat-value">{{ $submittedProductRequests }}</div>
    </div>
    <div class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-cart-check"></i></div>
        <div class="stat-label">Submitted Purchase Requests</div>
        <div class="stat-value">{{ $submittedPurchaseRequests }}</div>
    </div>
    @if(auth()->user()->isPurchaser())
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-send"></i></div>
        <div class="stat-label">Awaiting Dispatch</div>
        <div class="stat-value">{{ $awaitingDispatch }}</div>
    </div>
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-truck"></i></div>
        <div class="stat-label">Dispatched / Awaiting Receipt</div>
        <div class="stat-value">{{ $awaitingDelivery }}</div>
    </div>
    @endif
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-card admin-table-card h-100">
            <div class="admin-card-header"><h2>Low Stock</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Qty</th></tr></thead>
                    <tbody>
                        @forelse($lowStock as $row)
                        <tr><td>{{ $row->product->name }}</td><td class="text-end text-danger fw-semibold">{{ $row->quantity }}</td></tr>
                        @empty
                        <tr><td colspan="2"><div class="admin-empty">No low stock</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card admin-table-card h-100">
            <div class="admin-card-header"><h2>Top Stock Levels</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Qty</th></tr></thead>
                    <tbody>
                        @forelse($stockLines as $row)
                        <tr><td>{{ $row->product->name }}</td><td class="text-end">{{ $row->quantity }} {{ $row->product->unit?->abbreviation }}</td></tr>
                        @empty
                        <tr><td colspan="2"><div class="admin-empty">No stock</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
