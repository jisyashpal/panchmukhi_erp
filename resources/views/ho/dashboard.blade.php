@extends('layouts.ho')
@section('title', 'Dashboard')
@section('content')
<x-admin-page-header title="Dashboard" subtitle="Stock summary, purchases, and operations across all sites">
    <form method="GET" class="d-flex align-items-center gap-2">
        <select name="site_id" onchange="this.form.submit()" class="form-select form-select-sm" style="min-width: 180px;">
            <option value="">All sites</option>
            @foreach($sites as $site)
                <option value="{{ $site->id }}" @selected($siteId == $site->id)>{{ $site->name }}</option>
            @endforeach
        </select>
    </form>
</x-admin-page-header>

<div class="admin-stat-grid">
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Stock Lines (qty sum)</div>
        <div class="stat-value">{{ number_format($totalStockValue, 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-cart"></i></div>
        <div class="stat-label">Pending POs</div>
        <div class="stat-value">{{ $pendingOrders }}</div>
    </div>
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-check2-circle"></i></div>
        <div class="stat-label">Confirmed / Delivered</div>
        <div class="stat-value">{{ $confirmedOrders }}</div>
    </div>
    <div class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-currency-rupee"></i></div>
        <div class="stat-label">Total Purchase Amount</div>
        <div class="stat-value">₹{{ number_format($totalPurchaseAmount, 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-box-arrow-in-down"></i></div>
        <div class="stat-label">Stock In Movements</div>
        <div class="stat-value">{{ $stockInCount }}</div>
    </div>
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-box-arrow-up"></i></div>
        <div class="stat-label">Stock Out Movements</div>
        <div class="stat-value">{{ $stockOutCount }}</div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <div class="admin-card admin-table-card h-100">
            <div class="admin-card-header"><h2>Low Stock</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Site</th><th>Product</th><th class="text-end">Qty</th></tr></thead>
                    <tbody>
                        @forelse($lowStock as $row)
                        <tr>
                            <td>{{ $row->site->name ?? '' }}</td>
                            <td>{{ $row->product->name ?? '' }}</td>
                            <td class="text-end text-danger fw-semibold">{{ $row->quantity }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3"><div class="admin-empty">No low stock items</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card admin-table-card h-100">
            <div class="admin-card-header"><h2>Recent Purchases</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Ref</th><th>Site</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($recentPurchases as $purchase)
                        <tr>
                            <td><a href="{{ route('ho.purchases.show', $purchase) }}">{{ $purchase->reference_no }}</a></td>
                            <td>{{ $purchase->site->name }}</td>
                            <td><x-status-badge :status="$purchase->status" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="3"><div class="admin-empty">No purchases yet</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
