@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Purchase List')
@section('content')
<x-admin-page-header title="Product Purchase List" subtitle="Supplier-wise totals for {{ $filters->financialYear ? 'FY '.$filters->financialYear : 'selected period' }}">
</x-admin-page-header>

<div class="admin-filters mb-3">
    <form method="GET" class="row g-2 align-items-end">
        @if(isset($financialYear))
            <div class="col-6 col-md-2">
                <label class="form-label small">Financial Year</label>
                <x-financial-year-select compact :selected="request('fy', $financialYear->label)" />
            </div>
        @endif
        <div class="col-6 col-md-2">
            <label class="form-label small">Plant</label>
            <select name="plant_id" class="form-select form-select-sm">
                <option value="">All plants</option>
                @foreach($plants as $s)
                    <option value="{{ $s->id }}" @selected(request('plant_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">From date</label>
            <input type="date" name="date_from" value="{{ request('date_from', $financialYear->dateFrom ?? '') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small">To date</label>
            <input type="date" name="date_to" value="{{ request('date_to', $financialYear->dateTo ?? '') }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-admin-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
            @if(request()->anyFilled(['fy', 'plant_id', 'date_from', 'date_to']))
                <a href="{{ route('ho.purchase-list.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="admin-card admin-table-card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th class="text-end">Orders</th>
                    <th class="text-end">B/F</th>
                    <th class="text-end">Total Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th class="text-end" style="width: 140px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($supplierTotals as $row)
                <tr>
                    <td>{{ $row->supplier?->name ?? 'Unknown' }}</td>
                    <td class="text-end">{{ $row->order_count }}</td>
                    <td class="text-end">₹{{ number_format($row->brought_forward, 2) }}</td>
                    <td class="text-end fw-semibold">₹{{ number_format($row->total_amount, 2) }}</td>
                    <td class="text-end">₹{{ number_format($row->paid_amount, 2) }}</td>
                    <td class="text-end fw-semibold">₹{{ number_format($row->balance, 2) }}</td>
                    <td class="text-end">
                        @if($row->supplier)
                            <button type="button"
                                class="btn btn-sm btn-admin-outline btn-add-payment"
                                data-supplier-name="{{ $row->supplier->name }}"
                                data-payment-url="{{ route('ho.purchase-list.payments.store', $row->supplier) }}">
                                <i class="bi bi-cash-coin me-1"></i>Add Payment
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7"><div class="admin-empty">No purchases for the selected financial year.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="supplierPaymentModal" tabindex="-1" aria-labelledby="supplierPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="supplier_payment_form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="supplierPaymentModalLabel">Add Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Record payment for <strong id="payment_supplier_name">—</strong></p>
                    <div class="mb-3">
                        <label class="form-label small mb-1" for="payment_amount">Amount (₹)</label>
                        <input type="number" name="amount" id="payment_amount" class="form-control form-control-sm"
                            step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1" for="payment_date">Payment date</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control form-control-sm"
                            value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small mb-1" for="payment_notes">Notes <span class="text-muted">(optional)</span></label>
                        <textarea name="notes" id="payment_notes" rows="2" class="form-control form-control-sm"
                            maxlength="2000" placeholder="Cheque no / UTR / remark..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-admin-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-admin-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>Save payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('supplierPaymentModal');
    const form = document.getElementById('supplier_payment_form');
    if (!modalEl || !form) return;

    const modal = new bootstrap.Modal(modalEl);

    document.querySelectorAll('.btn-add-payment').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = btn.dataset.paymentUrl || '';
            document.getElementById('payment_supplier_name').textContent = btn.dataset.supplierName || '—';
            document.getElementById('payment_amount').value = '';
            document.getElementById('payment_date').value = '{{ now()->toDateString() }}';
            document.getElementById('payment_notes').value = '';
            modal.show();
        });
    });
})();
</script>
@endpush
@endsection
