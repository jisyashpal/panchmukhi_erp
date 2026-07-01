@extends('layouts.ho')
@section('title', $product->name.' — Stock Movements')
@section('content')
@php
    $unit = $product->unit->abbreviation ?? $product->unit->name ?? '';
    $isFilteredPeriod = $dateFrom !== $financialYearRange->dateFrom || $dateTo !== $financialYearRange->dateTo;
    $periodLabel = $isFilteredPeriod
        ? \Carbon\Carbon::parse($dateFrom)->format('d M Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d M Y')
        : $financialYearRange->displayLabel();
@endphp
<x-admin-page-header
    title="{{ $product->name }}"
    subtitle="{{ $plant->name }} · {{ $periodLabel }}">
    <a href="{{ route('ho.stocks.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
</x-admin-page-header>

<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <input type="hidden" name="plant_id" value="{{ request('plant_id') }}">
        <input type="hidden" name="product_id" value="{{ request('product_id') }}">
        <div class="col-6 col-md-2">
            <label class="form-label small" for="date_from">From</label>
            <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}"
                min="{{ $financialYearRange->dateFrom }}" max="{{ $financialYearRange->dateTo }}"
                class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small" for="date_to">To</label>
            <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}"
                min="{{ $financialYearRange->dateFrom }}" max="{{ $financialYearRange->dateTo }}"
                class="form-control form-control-sm">
        </div>
        <div class="col-auto d-flex gap-1">
            <button type="submit" class="btn btn-admin-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Filter
            </button>
            @if($isFilteredPeriod)
                <a href="{{ route('ho.stocks.show', ['plant_id' => request('plant_id'), 'product_id' => request('product_id')]) }}"
                    class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="admin-card admin-card-body">
            <div class="text-muted small">Current Stock</div>
            <div class="h5 mb-0 fw-bold">{{ number_format($balance?->quantity ?? 0, 2) }} {{ $unit }}</div>
            <div class="small text-muted mt-1">Est. Value: ₹{{ number_format(($balance?->quantity ?? 0) * $latestRate, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-card admin-card-body">
            <div class="text-muted small">Stock In (Receipts)</div>
            <div class="h5 mb-0 text-success fw-bold">{{ number_format($periodStockIn, 2) }} {{ $unit }}</div>
            <div class="small text-muted mt-1">Total Cost: ₹{{ number_format($totalValueIn, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-card admin-card-body">
            <div class="text-muted small">Stock Out (Consumption)</div>
            <div class="h5 mb-0 text-danger fw-bold">{{ number_format($periodStockOut, 2) }} {{ $unit }}</div>
            <div class="small text-muted mt-1">Total Expense: ₹{{ number_format($totalValueOut, 2) }}</div>
        </div>
    </div>
</div>

<div class="admin-card admin-table-card">
    <div class="admin-card-header"><h2 class="h6 mb-0">Stock Ledger</h2></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Where / Source / Destination</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Receipt (In)</th>
                    <th class="text-end">Issue (Out)</th>
                    <th class="text-end">Trans. Value</th>
                    <th class="text-end">Running Bal.</th>
                    <th>Notes</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <!-- Opening Balance Row -->
                <tr class="table-light">
                    <td class="small text-muted">—</td>
                    <td><span class="badge bg-secondary-subtle text-secondary">Opening</span></td>
                    <td class="fw-semibold">Opening Balance as of {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}</td>
                    <td class="text-end">₹{{ number_format($openingRate, 2) }}</td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end fw-semibold">₹{{ number_format($openingValue, 2) }}</td>
                    <td class="text-end fw-bold text-dark">{{ number_format($openingBalance, 2) }} {{ $unit }}</td>
                    <td class="text-muted small">—</td>
                    <td class="text-muted small">—</td>
                </tr>

                @forelse($movements as $m)
                <tr>
                    <td class="small text-muted">{{ $m->moved_at->format('d M Y H:i') }}</td>
                    <td>
                        @if($m->type === \App\Enums\StockMovementType::In)
                            <span class="badge bg-success-subtle text-success">Receipt</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger">Issue</span>
                        @endif
                    </td>
                    <td>
                        @if($m->type === \App\Enums\StockMovementType::In)
                            @if($m->reference_type === \App\Enums\StockReferenceType::Purchase && $m->reference_id)
                                PO: {{ $purchaseReferences[$m->reference_id] ?? '#'.$m->reference_id }}
                            @else
                                Stock In
                            @endif
                        @elseif($m->subCategory)
                            Sub Category: {{ $m->subCategory->name }}
                        @elseif(filled($m->reason))
                            Reason: {{ $m->reason }}
                        @else
                            Other / plant use
                        @endif
                    </td>
                    <td class="text-end">₹{{ number_format($m->rate, 2) }}</td>
                    <td class="text-end text-success">
                        @if($m->type === \App\Enums\StockMovementType::In)
                            +{{ number_format($m->quantity, 2) }} {{ $unit }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end text-danger">
                        @if($m->type === \App\Enums\StockMovementType::Out)
                            -{{ number_format($m->quantity, 2) }} {{ $unit }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end fw-semibold {{ $m->type === \App\Enums\StockMovementType::In ? 'text-success' : 'text-danger' }}">
                        ₹{{ number_format($m->value, 2) }}
                    </td>
                    <td class="text-end fw-bold">
                        {{ number_format($m->running_balance, 2) }} {{ $unit }}
                    </td>
                    <td class="text-muted small">{{ $m->notes ?? '—' }}</td>
                    <td class="text-muted small">{{ $m->performer?->name ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <div class="admin-empty">No movements for {{ $periodLabel }}</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($movements->isNotEmpty() || $openingBalance > 0)
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="3">Closing Balance as of {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}</td>
                    <td class="text-end">₹{{ number_format($latestRate, 2) }}</td>
                    <td class="text-end text-success">+{{ number_format($periodStockIn, 2) }} {{ $unit }}</td>
                    <td class="text-end text-danger">-{{ number_format($periodStockOut, 2) }} {{ $unit }}</td>
                    <td class="text-end text-primary">
                        Net: ₹{{ number_format($totalValueIn - $totalValueOut, 2) }}
                    </td>
                    <td class="text-end text-primary fw-bold fs-6">
                        {{ number_format($movements->isEmpty() ? $openingBalance : $movements->last()->running_balance, 2) }} {{ $unit }}
                        <div class="small fw-normal text-muted mt-1" style="font-size: 0.75rem;">
                            Value: ₹{{ number_format(($movements->isEmpty() ? $openingBalance : $movements->last()->running_balance) * $latestRate, 2) }}
                        </div>
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<!-- Usage Summary Card -->
@if($usageSummary->isNotEmpty())
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0 py-3">
        <h3 class="h6 mb-0 text-dark fw-bold">
            <i class="bi bi-pie-chart-fill text-primary me-2"></i>Item Usage Summary (Consumption)
        </h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Used For / Destination</th>
                        <th class="text-end pe-4" style="width: 200px;">Quantity Consumed</th>
                        <th class="text-end pe-4" style="width: 250px;">Consumption Value / Expense</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usageSummary as $destination => $data)
                    <tr>
                        <td class="ps-4 fw-semibold text-secondary">{{ $destination }}</td>
                        <td class="text-end pe-4 fw-bold text-dark">{{ number_format($data['quantity'], 2) }} {{ $unit }}</td>
                        <td class="text-end pe-4 fw-bold text-danger">₹{{ number_format($data['value'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td class="ps-4">Total Consumed (Stock Out)</td>
                        <td class="text-end pe-4 text-dark fs-6">{{ number_format($periodStockOut, 2) }} {{ $unit }}</td>
                        <td class="text-end pe-4 text-danger fw-bold fs-6">
                            ₹{{ number_format($totalValueOut, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
