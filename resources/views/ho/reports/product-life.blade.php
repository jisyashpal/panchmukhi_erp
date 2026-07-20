@extends('layouts.ho')
@section('title', 'Product Life Report')
@section('content')
    <x-admin-page-header title="Product Life Usage Report" subtitle="Usage entries with detailed life hours logs">
        <x-report-export-button />
    </x-admin-page-header>

    <x-report-filters :plants="$plants" :categories="$categories" :products="$products" :show-dates="true"
        search-placeholder="Product, subcategory or plant..." :clear-route="route('ho.reports.product-life')" />

    {{-- Summary statistics cards --}}
    @if($entries->isNotEmpty())
        <div class="admin-stat-grid mb-3">
            <div class="admin-stat-card stat-accent-blue">
                <div class="stat-icon stat-icon-blue"><i class="bi bi-clock-history"></i></div>
                <div class="stat-label">Total Life Hours</div>
                <div class="stat-value">{{ number_format($entries->sum('life_hours'), 1) }}</div>
            </div>
            <div class="admin-stat-card stat-accent-violet">
                <div class="stat-icon stat-icon-violet"><i class="bi bi-boxes"></i></div>
                <div class="stat-label">Total Qty Used</div>
                <div class="stat-value">{{ number_format($entries->sum('quantity'), 0) }}</div>
            </div>
            <div class="admin-stat-card stat-accent-emerald">
                <div class="stat-icon stat-icon-emerald"><i class="bi bi-arrow-repeat"></i></div>
                <div class="stat-label">Usage Count Sum</div>
                <div class="stat-value">{{ number_format($entries->sum('usage_count'), 0) }}</div>
            </div>
            <div class="admin-stat-card stat-accent-amber">
                <div class="stat-icon stat-icon-amber"><i class="bi bi-calculator"></i></div>
                <div class="stat-label">Avg Life Hours / Entry</div>
                <div class="stat-value">{{ number_format($entries->avg('life_hours'), 1) }}</div>
            </div>
        </div>
    @endif

    <div class="admin-card admin-table-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Plant</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Item Type</th>
                        <th class="text-end">Qty</th>
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
                        @include('partials.table-empty', [
                            'colspan' => 9,
                            'icon' => 'bi-clock-history',
                            'message' => 'No usage entries found for the selected filters.'
                        ])
                    @endforelse
                </tbody>
                @if($entries->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="5">Total / Summary</td>
                            <td class="text-end">{{ number_format($entries->sum('quantity'), 2) }}</td>
                            <td class="text-end">{{ number_format($entries->sum('usage_count'), 0) }}</td>
                            <td class="text-end text-primary">{{ number_format($entries->sum('life_hours'), 2) }} hrs</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
