@extends('layouts.plant')
@section('title', 'Dispatch — '.$purchase->reference_no)
@section('content')
<x-admin-page-header title="Dispatch — {{ $purchase->reference_no }}" subtitle="{{ $purchase->plant->name }} · {{ $purchase->supplier->name }}">
    <a href="{{ route('plant.dispatch.index') }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</x-admin-page-header>

<div class="purchase-create-pr-strip mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="purchase-create-pr-icon"><i class="bi bi-truck"></i></span>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge bg-primary">{{ $purchase->reference_no }}</span>
                <x-status-badge :status="$purchase->status" />
                @if($purchase->dispatched_at)
                    <span class="badge bg-success-subtle text-success border">Dispatched {{ $purchase->dispatched_at->format('d M Y') }}</span>
                @else
                    <span class="badge bg-warning-subtle text-warning border">Awaiting product-wise dispatch</span>
                @endif
            </div>
            <p class="mb-0 small text-muted">
                Set dispatch date per product. Mark items not sent by supplier, or choose an alternative product.
            </p>
        </div>
    </div>
</div>

@if($locked)
<div class="alert alert-info">
    Dispatch is locked
    @if($purchase->dispatched_at)
        (submitted {{ $purchase->dispatched_at->format('d M Y') }}@if($purchase->dispatcher) by {{ $purchase->dispatcher->name }}@endif)
    @endif.
</div>
@endif

<form method="POST" action="{{ route('plant.dispatch.store', $purchase) }}" id="dispatch-form">
    @csrf

    <div class="admin-card admin-table-card mb-4">
        <div class="admin-card-header">
            <h2 class="h6 mb-0"><i class="bi bi-box-seam me-2 text-primary"></i>Product-wise dispatch</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Ordered</th>
                        <th style="min-width: 11rem;">Status</th>
                        <th style="min-width: 9rem;">Dispatch date</th>
                        <th style="min-width: 6rem;" class="text-end">Dispatch qty</th>
                        <th style="min-width: 12rem;">Alternative / remark</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchase->items as $i => $item)
                        @php
                            $status = old("items.$i.dispatch_status", $item->dispatch_status?->value);
                            $orderedName = $item->originalProduct?->name ?? $item->product?->name;
                        @endphp
                        <tr class="dispatch-line" data-index="{{ $i }}">
                            <td>
                                <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                <span class="fw-semibold d-block">{{ $orderedName }}</span>
                                @if($item->product?->sku)
                                    <code class="small text-muted">{{ $item->product->sku }}</code>
                                @endif
                                @if($item->dispatch_status === \App\Enums\PurchaseItemDispatchStatus::Alternative && $item->originalProduct)
                                    <div class="small text-muted mt-1">
                                        Ordered: {{ $item->originalProduct->name }} → Receiving: {{ $item->product?->name }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($item->quantity, 2) }}</td>
                            <td>
                                <select name="items[{{ $i }}][dispatch_status]"
                                    class="form-select form-select-sm dispatch-status"
                                    @disabled($locked) required>
                                    <option value="">— Select —</option>
                                    @foreach(\App\Enums\PurchaseItemDispatchStatus::cases() as $case)
                                        <option value="{{ $case->value }}" @selected($status === $case->value)>
                                            {{ $case->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="date" name="items[{{ $i }}][dispatched_at]"
                                    value="{{ old("items.$i.dispatched_at", $item->dispatched_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                                    class="form-control form-control-sm dispatch-date"
                                    @disabled($locked)>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0.01" max="{{ $item->quantity }}"
                                    name="items[{{ $i }}][dispatched_quantity]"
                                    value="{{ old("items.$i.dispatched_quantity", $item->dispatched_quantity ?? $item->quantity) }}"
                                    class="form-control form-control-sm text-end dispatch-qty"
                                    @disabled($locked)>
                            </td>
                            <td>
                                <select name="items[{{ $i }}][alternative_product_id]"
                                    class="form-select form-select-sm dispatch-alt searchable-item-select mb-1 d-none"
                                    @disabled($locked)>
                                    <option value="">Search Item Name / SKU</option>
                                    @foreach($products as $p)
                                        @if($p->id !== ($item->original_product_id ?: $item->product_id) || $status === \App\Enums\PurchaseItemDispatchStatus::Alternative->value)
                                            <option value="{{ $p->id }}"
                                                data-name="{{ $p->name }}"
                                                data-sku="{{ $p->sku }}"
                                                data-category="{{ $p->category?->name }}"
                                                data-item-type="{{ $p->subCategory?->name }}"
                                                @selected(old("items.$i.alternative_product_id", $item->alternative_product_id) == $p->id)>
                                                {{ $p->name }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <textarea name="items[{{ $i }}][dispatch_remark]" rows="2"
                                    class="form-control form-control-sm dispatch-remark"
                                    placeholder="Remark (required if not provided)"
                                    @disabled($locked)>{{ old("items.$i.dispatch_remark", $item->dispatch_remark) }}</textarea>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @unless($locked)
    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-truck me-1"></i>Submit dispatch
        </button>
        <a href="{{ route('plant.dispatch.index') }}" class="btn btn-admin-outline">Cancel</a>
    </div>
    @endunless
</form>

@push('scripts')
<script>
    function syncDispatchRow(row) {
        const status = row.querySelector('.dispatch-status')?.value || '';
        const dateInput = row.querySelector('.dispatch-date');
        const qtyInput = row.querySelector('.dispatch-qty');
        const altSelect = row.querySelector('.dispatch-alt');
        const remark = row.querySelector('.dispatch-remark');

        if (!dateInput || !qtyInput || !altSelect || !remark) return;

        const needsDate = status === 'dispatched' || status === 'alternative';
        const needsAlt = status === 'alternative';
        const needsRemark = status === 'not_provided';

        dateInput.disabled = dateInput.hasAttribute('data-locked') || !needsDate;
        qtyInput.disabled = qtyInput.hasAttribute('data-locked') || !needsDate;
        dateInput.required = needsDate && !dateInput.hasAttribute('data-locked');
        qtyInput.required = needsDate && !qtyInput.hasAttribute('data-locked');
        altSelect.classList.toggle('d-none', !needsAlt);
        altSelect.required = needsAlt;
        remark.required = needsRemark;
        remark.placeholder = needsRemark
            ? 'Why supplier did not provide this item *'
            : (needsAlt ? 'Optional note about alternative' : 'Optional remark');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const locked = @json($locked);
        document.querySelectorAll('.dispatch-line').forEach(row => {
            if (locked) {
                row.querySelectorAll('input, select, textarea').forEach(el => {
                    el.setAttribute('data-locked', '1');
                });
            }
            const statusEl = row.querySelector('.dispatch-status');
            statusEl?.addEventListener('change', () => syncDispatchRow(row));
            syncDispatchRow(row);
        });
    });
</script>
@endpush
@endsection
