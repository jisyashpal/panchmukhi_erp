@extends('layouts.plant')
@section('title', 'Dashboard')
@section('content')
<x-admin-page-header
    title="{{ $isPurchaser ? 'Purchaser Dashboard' : 'Plant Dashboard' }}"
    subtitle="{{ $isPurchaser ? 'All plants — requests, purchases & stock overview' : ($plant->name.' — plant-specific summary') }}"
/>

<div class="admin-stat-grid mb-3">
    <a href="{{ route('plant.product-requests.index') }}" class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-clipboard-check"></i></div>
        <div class="stat-label">Submitted Product Requests</div>
        <div class="stat-value">{{ $submittedProductRequests }}</div>
    </a>
    <a href="{{ route('plant.purchases.index') }}" class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-cart-check"></i></div>
        <div class="stat-label">Total Purchases</div>
        <div class="stat-value">{{ $totalPurchases }}</div>
    </a>
    <a href="{{ route('plant.stocks.index') }}" class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Total Stocks</div>
        <div class="stat-value">{{ number_format($totalStockLines) }}</div>
        <div class="stat-sub text-muted" style="font-size:.78rem;">
            Qty sum: {{ number_format($totalStockQty, 0) }}
        </div>
    </a>
    @if(auth()->user()->isPurchaser())
    <a href="{{ route('plant.dispatch.index') }}" class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-send"></i></div>
        <div class="stat-label">Awaiting Dispatch</div>
        <div class="stat-value">{{ $awaitingDispatch }}</div>
    </a>
    <a href="{{ route('plant.purchases.index') }}" class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-truck"></i></div>
        <div class="stat-label">Dispatched / Awaiting Receipt</div>
        <div class="stat-value">{{ $awaitingDelivery }}</div>
    </a>
    @endif
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-card admin-table-card h-100">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Low Stock</h2>
                <a href="{{ route('plant.stocks.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Qty</th></tr></thead>
                    <tbody>
                        @forelse($lowStock as $row)
                        <tr>
                            <td>
                                {{ $row->product->name }}
                                @if($isPurchaser && $row->plant)
                                    <span class="text-muted small d-block">{{ $row->plant->name }}</span>
                                @endif
                            </td>
                            <td class="text-end text-danger fw-semibold">{{ $row->quantity }}</td>
                        </tr>
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
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Top Stock Levels</h2>
                <a href="{{ route('plant.stocks.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Qty</th></tr></thead>
                    <tbody>
                        @forelse($stockLines as $row)
                        <tr>
                            <td>
                                {{ $row->product->name }}
                                @if($isPurchaser && $row->plant)
                                    <span class="text-muted small d-block">{{ $row->plant->name }}</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $row->quantity }} {{ $row->product->unit?->abbreviation }}</td>
                        </tr>
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
