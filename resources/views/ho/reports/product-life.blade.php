@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Product Life Report')
@section('content')
    <x-admin-page-header title="Product Life Usage Report" subtitle="Stock issued to vehicles. Meter readings appear when a product life entry was recorded.">
        <x-report-export-button />
    </x-admin-page-header>

    <x-report-filters :plants="$plants" :categories="$categories" :products="$products" :vehicles="$vehicles" :show-dates="true"
        search-placeholder="Product, vehicle, subcategory or plant..." :clear-route="route('ho.reports.product-life')" />

    @if($entries->isNotEmpty())
        <div class="admin-stat-grid mb-3">
            <div class="admin-stat-card stat-accent-blue">
                <div class="stat-icon stat-icon-blue"><i class="bi bi-clock-history"></i></div>
                <div class="stat-label">Total Life Used</div>
                <div class="stat-value">{{ number_format($entries->sum(fn ($e) => (float) ($e->productUse?->life_hours ?? 0)), 1) }}</div>
            </div>
            <div class="admin-stat-card stat-accent-violet">
                <div class="stat-icon stat-icon-violet"><i class="bi bi-boxes"></i></div>
                <div class="stat-label">Total Qty Used</div>
                <div class="stat-value">{{ number_format($entries->sum('quantity'), 0) }}</div>
            </div>
            <div class="admin-stat-card stat-accent-emerald">
                <div class="stat-icon stat-icon-emerald"><i class="bi bi-truck"></i></div>
                <div class="stat-label">Vehicles</div>
                <div class="stat-value">{{ number_format($entries->pluck('vehicle_id')->filter()->unique()->count(), 0) }}</div>
            </div>
            <div class="admin-stat-card stat-accent-amber">
                <div class="stat-icon stat-icon-amber"><i class="bi bi-calculator"></i></div>
                <div class="stat-label">Avg Life Used / Entry</div>
                <div class="stat-value">{{ number_format($entries->avg(fn ($e) => (float) ($e->productUse?->life_hours ?? 0)), 1) }}</div>
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
                        <th>Vehicle</th>
                        <th>Current item</th>
                        <th>Replaced item</th>
                        <th>Item Type</th>
                        <th>Meter</th>
                        <th class="text-end">Prev</th>
                        <th class="text-end">Current</th>
                        <th class="text-end">Prev. item life</th>
                        <th class="text-end">Qty</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $e)
                        @php
                            $use = $e->productUse;
                            $unit = $use?->meterUnitLabel() ?? '';
                        @endphp
                        <tr>
                            <td class="text-nowrap text-muted">{{ $e->moved_at->format('d M Y') }}</td>
                            <td>{{ $e->plant->name }}</td>
                            <td class="fw-semibold">{{ $e->vehicle?->displayLabel() ?? '—' }}</td>
                            <td>
                                <span class="fw-semibold">{{ $e->product->name }}</span>
                                @if($e->product->category)
                                    <div class="small text-muted">{{ $e->product->category->name }}</div>
                                @endif
                            </td>
                            <td class="text-muted">{{ $use?->replacedProduct?->name ?? '—' }}</td>
                            <td>{{ $use?->subCategory?->name ?? $e->subCategory?->name ?? '—' }}</td>
                            <td>{{ $use ? ($use->meter_type === 'km' ? 'KM' : 'Hours') : '—' }}</td>
                            <td class="text-end text-muted">
                                {{ $use?->previous_reading !== null ? number_format($use->previous_reading, 2).' '.$unit : '—' }}
                            </td>
                            <td class="text-end">
                                {{ $use?->current_reading !== null ? number_format($use->current_reading, 2).' '.$unit : '—' }}
                            </td>
                            <td class="text-end fw-semibold text-primary">
                                {{ $use ? number_format($use->life_hours, 2).' '.$unit : '—' }}
                            </td>
                            <td class="text-end">{{ number_format($e->quantity, 2) }}</td>
                            <td class="small text-muted">{{ $e->performer->name ?? '—' }}</td>
                        </tr>
                    @empty
                        @include('partials.table-empty', [
                            'colspan' => 12,
                            'icon' => 'bi-clock-history',
                            'message' => 'No usage entries found for the selected filters.'
                        ])
                    @endforelse
                </tbody>
                @if($entries->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="9">Total / Summary</td>
                            <td class="text-end text-primary">{{ number_format($entries->sum(fn ($e) => (float) ($e->productUse?->life_hours ?? 0)), 2) }}</td>
                            <td class="text-end">{{ number_format($entries->sum('quantity'), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
