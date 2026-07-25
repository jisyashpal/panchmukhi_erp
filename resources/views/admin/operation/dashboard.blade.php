@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<x-admin-page-header title="Dashboard" subtitle="System overview — master data & operations oversight">
    <form method="GET" class="d-flex align-items-center gap-2">
        <select name="plant_id" onchange="this.form.submit()" class="form-select form-select-sm" style="min-width: 180px;">
            <option value="">All plants</option>
            @foreach($plants as $plant)
                <option value="{{ $plant->id }}" @selected($plantId == $plant->id)>{{ $plant->name }}</option>
            @endforeach
        </select>
    </form>
</x-admin-page-header>

<div class="admin-stat-grid">
    <a href="{{ route('admin.plants.index') }}" class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-building"></i></div>
        <div class="stat-label">Active Plants</div>
        <div class="stat-value">{{ $activePlants }}</div>
    </a>
    <a href="{{ route('admin.users.index') }}" class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-people"></i></div>
        <div class="stat-label">Active Users</div>
        <div class="stat-value">{{ $usersCount }}</div>
    </a>
    <a href="{{ route('admin.products.index') }}" class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-box"></i></div>
        <div class="stat-label">Products</div>
        <div class="stat-value">{{ $productsCount }}</div>
    </a>
    <a href="{{ route('ho.stocks.index', array_filter(['plant_id' => $plantId ?: null])) }}" class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Stock Lines</div>
        <div class="stat-value">{{ $totalStockLines }}</div>
    </a>
    <a href="{{ route('ho.purchases.index', array_filter(['plant_id' => $plantId ?: null, 'status' => 'ordered'])) }}" class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-cart"></i></div>
        <div class="stat-label">Pending Orders</div>
        <div class="stat-value">{{ $pendingOrders }}</div>
    </a>
    <a href="{{ route('ho.purchases.index', array_filter(['plant_id' => $plantId ?: null, 'status' => 'delivered'])) }}" class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-check2-circle"></i></div>
        <div class="stat-label">Delivered Purchases</div>
        <div class="stat-value">{{ $deliveredPurchases }}</div>
    </a>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-4">
        <div class="admin-card admin-card-body h-100">
            <h2 class="h6">Operations Overview</h2>
            <p class="small text-muted">Access HO operations (stock, PO, reports) with full Super Admin visibility.</p>
            <div class="d-grid gap-2">
                <a href="{{ route('ho.dashboard') }}" class="btn btn-admin-outline btn-sm">HO Dashboard</a>
                <a href="{{ route('ho.product-requests.index') }}" class="btn btn-admin-outline btn-sm">Product Requests</a>
                <a href="{{ route('ho.stocks.index') }}" class="btn btn-admin-outline btn-sm">Stocks</a>
                <a href="{{ route('ho.product-life.index') }}" class="btn btn-admin-outline btn-sm">Product Life</a>
                <a href="{{ route('ho.reports.index') }}" class="btn btn-admin-outline btn-sm">Reports</a>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card admin-table-card h-100">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Low Stock</h2>
                <a href="{{ route('ho.stocks.index', array_filter(['plant_id' => $plantId ?: null])) }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Plant</th><th>Product</th><th class="text-end">Qty</th></tr></thead>
                    <tbody>
                        @forelse($lowStock as $row)
                        <tr>
                            <td>{{ $row->plant->name }}</td>
                            <td>
                                @if($row->product && $row->plant_id)
                                    <a href="{{ route('ho.stocks.show', ['plant_id' => $row->plant_id, 'product_id' => $row->product_id]) }}">
                                        {{ $row->product->name }}
                                    </a>
                                @else
                                    {{ $row->product->name ?? '' }}
                                @endif
                            </td>
                            <td class="text-end text-danger fw-semibold">{{ $row->quantity }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3"><div class="admin-empty">No low stock</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card admin-table-card h-100">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Recent Purchases</h2>
                <a href="{{ route('ho.purchases.index', array_filter(['plant_id' => $plantId ?: null])) }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Ref</th><th>Plant</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($recentPurchases as $purchase)
                        <tr>
                            <td><a href="{{ route('ho.purchases.show', $purchase) }}">{{ $purchase->reference_no }}</a></td>
                            <td>{{ $purchase->plant->name }}</td>
                            <td><x-status-badge :status="$purchase->status" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="3"><div class="admin-empty">No purchases</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
