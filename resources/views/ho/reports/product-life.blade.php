@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Product Life Report')
@section('content')
    <x-admin-page-header title="Product Life Usage Report" subtitle="Vehicle-wise part changes with meter readings and life used">
        <x-report-export-button />
    </x-admin-page-header>

    <x-report-filters :plants="$plants" :categories="$categories" :products="$products" :vehicles="$vehicles" :show-dates="true"
        search-placeholder="Product, vehicle, subcategory or plant..." :clear-route="route('ho.reports.product-life')" />

    @if($entries->isNotEmpty())
        <div class="admin-stat-grid mb-3">
            <div class="admin-stat-card stat-accent-blue">
                <div class="stat-icon stat-icon-blue"><i class="bi bi-clock-history"></i></div>
                <div class="stat-label">Total Life Used</div>
                <div class="stat-value">{{ number_format($entries->sum('life_hours'), 1) }}</div>
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
                        @php $unit = $e->meterUnitLabel(); @endphp
                        <tr>
                            <td class="text-nowrap text-muted">{{ $e->used_at->format('d M Y') }}</td>
                            <td>{{ $e->plant->name }}</td>
                            <td class="fw-semibold">{{ $e->vehicle?->displayLabel() ?? '—' }}</td>
                            <td>
                                <span class="fw-semibold">{{ $e->product->name }}</span>
                                @if($e->product->category)
                                    <div class="small text-muted">{{ $e->product->category->name }}</div>
                                @endif
                            </td>
                            <td class="text-muted">{{ $e->replacedProduct?->name ?? '—' }}</td>
                            <td>{{ $e->subCategory?->name ?? '—' }}</td>
                            <td>{{ $e->meter_type === 'km' ? 'KM' : 'Hours' }}</td>
                            <td class="text-end text-muted">
                                {{ $e->previous_reading !== null ? number_format($e->previous_reading, 2).' '.$unit : '—' }}
                            </td>
                            <td class="text-end">
                                {{ $e->current_reading !== null ? number_format($e->current_reading, 2).' '.$unit : '—' }}
                            </td>
                            <td class="text-end fw-semibold text-primary">
                                {{ number_format($e->life_hours, 2) }} {{ $unit }}
                            </td>
                            <td class="text-end">{{ number_format($e->quantity, 2) }}</td>
                            <td class="small text-muted">{{ $e->recorder->name ?? '—' }}</td>
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
                            <td class="text-end text-primary">{{ number_format($entries->sum('life_hours'), 2) }}</td>
                            <td class="text-end">{{ number_format($entries->sum('quantity'), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
