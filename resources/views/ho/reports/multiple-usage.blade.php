@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Multiple Usage Report')
@section('content')
<x-admin-page-header title="Multiple Usage Highlights" subtitle="Products used more than once — sorted by highest usage count">
    <x-report-export-button />
</x-admin-page-header>

<x-report-filters
    :plants="$plants"
    :categories="$categories"
    :products="$products"
    :vehicles="$vehicles"
    :show-dates="true"
    search-placeholder="Product name..."
    :clear-route="route('ho.reports.multiple-usage')"
/>

{{-- Summary stat cards --}}
@if($rows->isNotEmpty())
<div class="admin-stat-grid mb-3">
    <div class="admin-stat-card stat-accent-amber">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-exclamation-diamond"></i></div>
        <div class="stat-label">Flagged Products</div>
        <div class="stat-value">{{ $rows->count() }}</div>
    </div>
    <div class="admin-stat-card stat-accent-blue">
        <div class="stat-icon stat-icon-blue"><i class="bi bi-arrow-repeat"></i></div>
        <div class="stat-label">Total Use Entries</div>
        <div class="stat-value">{{ number_format($rows->sum('use_count'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-violet">
        <div class="stat-icon stat-icon-violet"><i class="bi bi-boxes"></i></div>
        <div class="stat-label">Total Qty Consumed</div>
        <div class="stat-value">{{ number_format($rows->sum('total_qty'), 0) }}</div>
    </div>
    <div class="admin-stat-card stat-accent-emerald">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-clock-history"></i></div>
        <div class="stat-label">Total Life Used</div>
        <div class="stat-value">{{ number_format($rows->sum('total_life_hours'), 0) }}</div>
    </div>
</div>
@endif

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th class="text-end">Use Count</th>
                    <th class="text-end">Total Qty</th>
                    <th class="text-end">Avg Qty/Use</th>
                    <th class="text-end">Total Life Used</th>
                    <th class="text-end">Avg Life Used</th>
                    <th class="text-end">Plants</th>
                    <th class="text-end">Vehicles</th>
                    <th>Last Used</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $i => $r)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="fw-semibold">{{ $r->product_name }}</td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle">
                                {{ $r->category_name }}
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="badge {{ $r->use_count >= 5 ? 'bg-danger' : ($r->use_count >= 3 ? 'bg-warning text-dark' : 'bg-info text-dark') }}">
                                {{ number_format($r->use_count, 0) }}×
                            </span>
                        </td>
                        <td class="text-end fw-semibold">{{ number_format($r->total_qty, 0) }}</td>
                        <td class="text-end text-muted">{{ number_format($r->avg_qty_per_use, 1) }}</td>
                        <td class="text-end">{{ number_format($r->total_life_hours, 0) }}</td>
                        <td class="text-end text-muted">{{ number_format($r->avg_life_hours, 1) }}</td>
                        <td class="text-end">{{ number_format($r->plant_count, 0) }}</td>
                        <td class="text-end">{{ number_format($r->vehicle_count ?? 0, 0) }}</td>
                        <td class="text-muted text-nowrap">
                            {{ $r->last_used_at ? \Carbon\Carbon::parse($r->last_used_at)->format('d M Y') : '—' }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('ho.reports.product-life', array_merge($filters->toQueryArray(), ['product_id' => $r->product_id])) }}"
                               class="btn btn-sm btn-outline-primary"
                               title="View full ledger for {{ $r->product_name }}">
                                <i class="bi bi-journal-text me-1"></i>Ledger
                            </a>
                        </td>
                    </tr>
                @empty
                    @include('partials.table-empty', [
                        'colspan' => 12,
                        'icon'    => 'bi-exclamation-diamond',
                        'message' => 'No products with multiple usage found for the selected filters.',
                    ])
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="3">Total</td>
                    <td class="text-end">{{ number_format($rows->sum('use_count'), 0) }}</td>
                    <td class="text-end">{{ number_format($rows->sum('total_qty'), 0) }}</td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end">{{ number_format($rows->sum('total_life_hours'), 0) }}</td>
                    <td class="text-end text-muted">—</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
