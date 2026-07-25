@extends('layouts.ho')
@section('title', 'Dashboard')
@section('content')
@php
    $plantQuery = array_filter(['plant_id' => $plantId ?: null, 'fy' => request('fy') ?: null]);
@endphp
<x-admin-page-header title="Dashboard" subtitle="Operations for {{ $financialYearRange->displayLabel() }} across all plants">
    <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
        <x-financial-year-select compact :selected="request('fy', $financialYearRange->label)" />
        <select name="plant_id" onchange="this.form.submit()" class="form-select form-select-sm" style="min-width: 180px;">
            <option value="">All plants</option>
            @foreach($plants as $plant)
                <option value="{{ $plant->id }}" @selected($plantId == $plant->id)>{{ $plant->name }}</option>
            @endforeach
        </select>
    </form>
</x-admin-page-header>

{{-- Row 1: Core Stats --}}
<div class="admin-stat-grid">
    <a href="{{ route('ho.stocks.index', $plantQuery) }}" class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Stock Lines (qty sum)</div>
        <div class="stat-value">{{ number_format($totalStockValue, 0) }}</div>
    </a>
    <a href="{{ route('ho.purchases.index', array_merge($plantQuery, ['status' => 'ordered'])) }}" class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-cart"></i></div>
        <div class="stat-label">Pending POs</div>
        <div class="stat-value">{{ $pendingOrders }}</div>
    </a>
    <a href="{{ route('ho.purchases.index', array_merge($plantQuery, ['status' => 'delivered'])) }}" class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-check2-circle"></i></div>
        <div class="stat-label">Confirmed / Delivered</div>
        <div class="stat-value">{{ $confirmedOrders }}</div>
    </a>
    <a href="{{ route('ho.purchases.index', $plantQuery) }}" class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-currency-rupee"></i></div>
        <div class="stat-label">Total Purchase Amount</div>
        <div class="stat-value">₹{{ number_format($totalPurchaseAmount, 0) }}</div>
    </a>
</div>

{{-- Row 2: Stock In / Out Stats --}}
<div class="admin-stat-grid mt-3">
    <a href="{{ route('ho.reports.category-stock-in-out', $plantQuery) }}" class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-box-arrow-in-down"></i></div>
        <div class="stat-label">Stock In — Qty</div>
        <div class="stat-value">{{ number_format($stockInQty, 0) }}</div>
        <div class="stat-sub text-muted" style="font-size:.78rem;">{{ $stockInCount }} movements</div>
    </a>
    <a href="{{ route('ho.reports.category-stock-in-out', $plantQuery) }}" class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-currency-rupee"></i></div>
        <div class="stat-label">Stock In — Amount</div>
        <div class="stat-value">₹{{ number_format($stockInAmount, 0) }}</div>
        <div class="stat-sub text-muted" style="font-size:.78rem;">from PO receipts</div>
    </a>
    <a href="{{ route('ho.stocks.stock-out', $plantQuery) }}" class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-box-arrow-up"></i></div>
        <div class="stat-label">Stock Out — Qty</div>
        <div class="stat-value">{{ number_format($stockOutQty, 0) }}</div>
        <div class="stat-sub text-muted" style="font-size:.78rem;">{{ $stockOutCount }} movements</div>
    </a>
    <a href="{{ route('ho.reports.category-stock-in-out', $plantQuery) }}" class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="stat-label">Net Stock Movement</div>
        <div class="stat-value {{ $stockInQty - $stockOutQty >= 0 ? 'text-success' : 'text-danger' }}">
            {{ number_format($stockInQty - $stockOutQty, 0) }}
        </div>
        <div class="stat-sub text-muted" style="font-size:.78rem;">In − Out</div>
    </a>
</div>

{{-- Category-wise breakdown + Low Stock --}}
<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <div class="admin-card admin-table-card h-100">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Category-wise Stock In / Out</h2>
                <a href="{{ route('ho.reports.category-stock-in-out', array_filter(['plant_id' => $plantId])) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-right-circle me-1"></i>View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-end">In Qty</th>
                            <th class="text-end">In Amount (₹)</th>
                            <th class="text-end">Out Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categoryBreakdown as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row->category }}</td>
                            <td class="text-end text-success">{{ number_format($row->in_qty, 0) }}</td>
                            <td class="text-end">₹{{ number_format($row->in_amount, 0) }}</td>
                            <td class="text-end text-danger">{{ number_format($row->out_qty, 0) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4"><div class="admin-empty">No stock movements yet</div></td></tr>
                        @endforelse
                    </tbody>
                    @if($categoryBreakdown->isNotEmpty())
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td>Total</td>
                            <td class="text-end text-success">{{ number_format($categoryBreakdown->sum('in_qty'), 0) }}</td>
                            <td class="text-end">₹{{ number_format($categoryBreakdown->sum('in_amount'), 0) }}</td>
                            <td class="text-end text-danger">{{ number_format($categoryBreakdown->sum('out_qty'), 0) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="admin-card admin-table-card h-100">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Low Stock</h2>
                <a href="{{ route('ho.stocks.index', $plantQuery) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-right-circle me-1"></i>View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Plant</th><th>Product</th><th class="text-end">Qty</th></tr></thead>
                    <tbody>
                        @forelse($lowStock as $row)
                        <tr>
                            <td>{{ $row->plant->name ?? '' }}</td>
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
                        <tr><td colspan="3"><div class="admin-empty">No low stock items</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Recent Purchases --}}
<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="admin-card admin-table-card">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Recent Purchases</h2>
                <a href="{{ route('ho.purchases.index', $plantQuery) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-right-circle me-1"></i>View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Ref</th><th>Plant</th><th>Supplier</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($recentPurchases as $purchase)
                        <tr>
                            <td><a href="{{ route('ho.purchases.show', $purchase) }}">{{ $purchase->reference_no }}</a></td>
                            <td>{{ $purchase->plant->name }}</td>
                            <td>{{ $purchase->supplier->name ?? '—' }}</td>
                            <td><x-status-badge :status="$purchase->status" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="4"><div class="admin-empty">No purchases yet</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
