@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Party Ledger - ' . $supplier->name)
@section('content')
<x-admin-page-header title="Party Ledger: {{ $supplier->name }}" subtitle="Purchases and payments for the supplier">
    <div class="d-flex gap-2">
        <a href="{{ route('ho.reports.party-purchase') }}" class="btn btn-sm btn-admin-outline">
            <i class="bi bi-arrow-left me-1"></i>Back to Summary
        </a>
        <x-report-export-button />
    </div>
</x-admin-page-header>

<x-report-filters
    :plants="$plants"
    :show-dates="true"
    search-placeholder="PO reference, item, or payment note..."
    :clear-route="route('ho.reports.party-ledger', $supplier->id)"
/>

<!-- Supplier Details Card -->
<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <span class="text-muted small d-block">Contact Person</span>
                <strong>{{ $supplier->contact_person ?? '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Phone / Email</span>
                <strong>{{ $supplier->phone ?? '—' }}</strong>
                @if($supplier->email)
                    <span class="d-block small text-muted">{{ $supplier->email }}</span>
                @endif
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">GSTIN</span>
                <strong>{{ $supplier->gstin ?? '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted small d-block">Address</span>
                <span class="small">{{ $supplier->address ?? '—' }}</span>
            </div>
        </div>
    </div>
</div>

@php
    $totalDebit = $rows->sum('debit');
    $totalCredit = $rows->sum('credit');
    $closingBalance = $rows->last()->balance ?? 0;
@endphp

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Particulars</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                    <th class="text-end">Balance</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="small text-muted">{{ $row->date?->format('d M Y') ?? '—' }}</td>
                        <td>
                            @if($row->type === 'payment')
                                <span class="badge text-bg-success">Payment</span>
                            @else
                                <span class="badge text-bg-primary">Purchase</span>
                            @endif
                        </td>
                        <td>
                            @if($row->type === 'purchase')
                                <code class="small">{{ $row->reference }}</code>
                                @if($row->plant)
                                    <span class="d-block small text-muted">{{ $row->plant }}</span>
                                @endif
                            @else
                                <span class="small">{{ $row->reference }}</span>
                            @endif
                        </td>
                        <td class="small" style="max-width: 280px;">
                            @if($row->type === 'purchase')
                                @foreach($row->items as $item)
                                    <div class="text-truncate" title="{{ $item->product->name }} (Qty: {{ $item->quantity }})">
                                        • {{ $item->product->name }} <span class="text-muted">x {{ $item->quantity }}</span>
                                    </div>
                                @endforeach
                                @if($row->status)
                                    <div class="mt-1"><x-status-badge :status="$row->status" /></div>
                                @endif
                            @else
                                {{ $row->particulars }}
                            @endif
                        </td>
                        <td class="text-end">{{ $row->debit > 0 ? '₹'.number_format($row->debit, 2) : '—' }}</td>
                        <td class="text-end">{{ $row->credit > 0 ? '₹'.number_format($row->credit, 2) : '—' }}</td>
                        <td class="text-end fw-semibold">₹{{ number_format($row->balance, 2) }}</td>
                        <td class="text-end">
                            @if($row->type === 'purchase' && $row->purchase)
                                <a href="{{ route('ho.purchases.show', $row->purchase) }}" class="btn btn-sm btn-admin-outline">
                                    <i class="bi bi-eye me-1"></i>View PO
                                </a>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    @include('partials.table-empty', ['colspan' => 8, 'icon' => 'bi-receipt', 'message' => 'No ledger entries found for the selected filters.'])
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot>
                    <tr class="table-light fw-semibold">
                        <td colspan="4">Total</td>
                        <td class="text-end">₹{{ number_format($totalDebit, 2) }}</td>
                        <td class="text-end">₹{{ number_format($totalCredit, 2) }}</td>
                        <td class="text-end">₹{{ number_format($closingBalance, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
