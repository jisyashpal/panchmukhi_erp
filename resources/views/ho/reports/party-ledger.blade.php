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
                            @elseif($row->type === 'opening')
                                <span class="badge text-bg-warning">Opening</span>
                            @elseif($row->type === 'balance_bf')
                                <span class="badge text-bg-secondary">Balance B/F</span>
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
                        <td>
                            <button type="button"
                                class="btn btn-sm btn-admin-outline"
                                data-bs-toggle="modal"
                                data-bs-target="#ledgerParticularsModal"
                                data-ledger-title="{{ $row->reference }}"
                                data-ledger-body="ledger-particulars-{{ $loop->index }}">
                                <i class="bi bi-eye me-1"></i>View
                            </button>
                            <template id="ledger-particulars-{{ $loop->index }}">
                                @if($row->type === 'purchase')
                                    @forelse($row->items as $item)
                                        <div>• {{ $item->product->name }} <span class="text-muted">x {{ $item->quantity }}</span></div>
                                    @empty
                                        <div class="text-muted">No items</div>
                                    @endforelse
                                    @if($row->status)
                                        <div class="mt-2"><x-status-badge :status="$row->status" /></div>
                                    @endif
                                @else
                                    <div>{{ $row->particulars }}</div>
                                @endif
                            </template>
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

<div class="modal fade" id="ledgerParticularsModal" tabindex="-1" aria-labelledby="ledgerParticularsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ledgerParticularsModalLabel">Particulars</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body small" id="ledgerParticularsBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-admin-outline btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('ledgerParticularsModal');
    if (!modalEl) return;

    modalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const title = button.getAttribute('data-ledger-title') || 'Particulars';
        const bodyId = button.getAttribute('data-ledger-body');
        const source = bodyId ? document.getElementById(bodyId) : null;

        document.getElementById('ledgerParticularsModalLabel').textContent = title;
        document.getElementById('ledgerParticularsBody').innerHTML = source ? source.innerHTML : '';
    });
})();
</script>
@endpush
@endsection
