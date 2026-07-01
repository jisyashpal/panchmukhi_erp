@extends('layouts.ho')
@section('title', 'Product Life')
@section('content')
<x-admin-page-header title="Product Life" subtitle="Logged product usage and running life hours">
    <a href="{{ route('ho.product-life.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Add Entry
    </a>
</x-admin-page-header>

<x-list-filters :plants="$plants" :products="$products" :showFinancialYear="true" :showDates="true" :clearRoute="route('ho.product-life.index')" />

{{-- Summary Stats cards --}}
<div class="admin-stat-grid mb-3">
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-clock-history"></i></div>
        <div class="stat-label">Total Life Hours</div>
        <div class="stat-value">{{ number_format($entries->sum('life_hours'), 1) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Total Quantity Used</div>
        <div class="stat-value">{{ number_format($entries->sum('quantity'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-arrow-repeat"></i></div>
        <div class="stat-label">Usage count sum</div>
        <div class="stat-value">{{ number_format($entries->sum('usage_count'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-list-ul"></i></div>
        <div class="stat-label">Entries count</div>
        <div class="stat-value">{{ number_format($entries->total(), 0) }}</div>
    </div>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Plant</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Sub Category</th>
                    <th class="text-end">Qty Used</th>
                    <th class="text-end">Usage Count</th>
                    <th class="text-end">Life hrs</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $e)
                <tr>
                    <td class="text-nowrap text-muted">{{ $e->used_at->format('d M Y') }}</td>
                    <td>{{ $e->plant->name }}</td>
                    <td class="fw-semibold">{{ $e->product->name }}</td>
                    <td>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle">
                            {{ $e->product->category->name ?? '—' }}
                        </span>
                    </td>
                    <td>{{ $e->subCategory?->name ?? '—' }}</td>
                    <td class="text-end fw-semibold">{{ number_format($e->quantity, 2) }}</td>
                    <td class="text-end text-muted">{{ $e->usage_count }}</td>
                    <td class="text-end fw-semibold text-primary">{{ number_format($e->life_hours, 2) }} hrs</td>
                    <td class="small text-muted">{{ $e->recorder->name ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="9"><div class="admin-empty">No entries found for selected filters</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $entries->links() }}</div>
@endsection
