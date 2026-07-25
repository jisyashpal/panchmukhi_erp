@extends('layouts.ho')
@section('title', 'Usage by Category')
@section('content')
<x-admin-page-header title="Product Usage by Category" subtitle="Consumption grouped by product category">
    <x-report-export-button />
</x-admin-page-header>

<x-report-filters
    :plants="$plants"
    :categories="$categories"
    :vehicles="$vehicles"
    :show-dates="true"
    search-placeholder="Category name..."
    :clear-route="route('ho.reports.usage-by-category')"
/>

{{-- Summary totals --}}
@if($rows->isNotEmpty())
<div class="admin-stat-grid mb-3">
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-diagram-3"></i></div>
        <div class="stat-label">Categories</div>
        <div class="stat-value">{{ $rows->count() }}</div>
    </div>
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Total Qty Used</div>
        <div class="stat-value">{{ number_format($rows->sum('total_qty'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-clock-history"></i></div>
        <div class="stat-label">Total Life Used</div>
        <div class="stat-value">{{ number_format($rows->sum('total_life_hours'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-arrow-repeat"></i></div>
        <div class="stat-label">Total Usage Entries</div>
        <div class="stat-value">{{ number_format($rows->sum('usage_count'), 0) }}</div>
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
                    <th class="text-end">Products Used</th>
                    <th class="text-end">Vehicles</th>
                    <th class="text-end">Usage Entries</th>
                    <th class="text-end">Total Qty</th>
                    <th class="text-end">Total Life Used</th>
                    <th class="text-end">Avg Life Used</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $i => $r)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="fw-semibold">{{ $r->category_name }}</td>
                        <td class="text-end">{{ number_format($r->product_count, 0) }}</td>
                        <td class="text-end">{{ number_format($r->vehicle_count ?? 0, 0) }}</td>
                        <td class="text-end">{{ number_format($r->usage_count, 0) }}</td>
                        <td class="text-end fw-semibold text-primary">{{ number_format($r->total_qty, 0) }}</td>
                        <td class="text-end">{{ number_format($r->total_life_hours, 0) }}</td>
                        <td class="text-end text-muted">{{ number_format($r->avg_life_hours, 1) }}</td>
                        <td class="text-end">
                            <a href="{{ route('ho.reports.product-life', array_merge($filters->toQueryArray(), ['category_id' => $r->category_id])) }}"
                               class="btn btn-sm btn-outline-primary"
                               title="View detailed ledger for {{ $r->category_name }}">
                                <i class="bi bi-journal-text me-1"></i>Ledger
                            </a>
                        </td>
                    </tr>
                @empty
                    @include('partials.table-empty', [
                        'colspan' => 9,
                        'icon'    => 'bi-diagram-3',
                        'message' => 'No usage data found for the selected filters.',
                    ])
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="2">Total</td>
                    <td class="text-end">{{ number_format($rows->sum('product_count'), 0) }}</td>
                    <td class="text-end">{{ number_format($rows->sum('vehicle_count'), 0) }}</td>
                    <td class="text-end">{{ number_format($rows->sum('usage_count'), 0) }}</td>
                    <td class="text-end text-primary">{{ number_format($rows->sum('total_qty'), 0) }}</td>
                    <td class="text-end">{{ number_format($rows->sum('total_life_hours'), 0) }}</td>
                    <td class="text-end text-muted">—</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
