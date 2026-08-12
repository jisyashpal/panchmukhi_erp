@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Category Purchase Report')
@section('content')
<x-admin-page-header title="Category-wise Purchase Amount" subtitle="Purchase amount grouped by category">
    <x-report-export-button />
</x-admin-page-header>

<x-report-filters
    :plants="$plants"
    :categories="$categories"
    :show-dates="true"
    search-placeholder="Category name..."
    :clear-route="route('ho.reports.category-purchase')"
/>

{{-- Summary stat cards --}}
@if($rows->isNotEmpty())
<div class="admin-stat-grid mb-3">
    <div class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-folder2"></i></div>
        <div class="stat-label">Categories</div>
        <div class="stat-value">{{ $rows->count() }}</div>
    </div>
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Total Qty</div>
        <div class="stat-value">{{ number_format($rows->sum('total_qty'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-currency-rupee"></i></div>
        <div class="stat-label">Total Amount</div>
        <div class="stat-value">₹{{ number_format($rows->sum('total_amount'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-receipt"></i></div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-value">{{ number_format($rows->sum('order_count'), 0) }}</div>
    </div>
</div>
@endif

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Category</th>
                    <th>Items Purchased</th>
                    <th class="text-end">Products</th>
                    <th class="text-end">Orders</th>
                    <th class="text-end">Total Qty</th>
                    <th class="text-end">Total Amount</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $i => $r)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="fw-semibold">{{ $r->category_name }}</td>
                        <td class="text-muted small" style="max-width: 280px; white-space: normal;">
                            {{ \Illuminate\Support\Str::limit($r->top_items ?? '—', 80) }}
                        </td>
                        <td class="text-end">{{ number_format($r->product_count, 0) }}</td>
                        <td class="text-end">{{ number_format($r->order_count, 0) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($r->total_qty, 0) }}</td>
                        <td class="text-end fw-semibold text-success">₹{{ number_format($r->total_amount, 2) }}</td>
                        <td class="text-end">
                            <a href="{{ route('ho.reports.category-purchase.ledger', array_merge(['category' => $r->category_id], $filters->toQueryArray())) }}"
                               class="btn btn-sm btn-outline-primary"
                               title="View purchase ledger for {{ $r->category_name }}">
                                <i class="bi bi-journal-text me-1"></i>Ledger
                            </a>
                        </td>
                    </tr>
                @empty
                    @include('partials.table-empty', [
                        'colspan' => 8,
                        'icon'    => 'bi-pie-chart',
                        'message' => 'No purchase data found for the selected filters.',
                    ])
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="3">Total</td>
                    <td class="text-end">{{ number_format($rows->sum('product_count'), 0) }}</td>
                    <td class="text-end">{{ number_format($rows->sum('order_count'), 0) }}</td>
                    <td class="text-end">{{ number_format($rows->sum('total_qty'), 0) }}</td>
                    <td class="text-end text-success">₹{{ number_format($rows->sum('total_amount'), 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
