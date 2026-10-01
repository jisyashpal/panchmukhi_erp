@extends('layouts.plant')
@section('title', 'Receive Stock')
@section('content')
<x-admin-page-header title="Receive Stock" subtitle="Purchase {{ $purchase->reference_no }}">
    <a href="{{ route('plant.purchases.index') }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</x-admin-page-header>

<div class="admin-card">
    <div class="admin-card-body">
        <p class="small text-muted mb-3">Supplier: {{ $purchase->supplier->name }}</p>
        <form method="POST" action="{{ route('plant.purchases.receive.store', $purchase) }}">
            @csrf
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end" style="width: 140px;">Receive Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->items as $i => $item)
                        @php
                            $pending = $item->receivableQuantity();
                            $isReceivable = $item->isReceivableForStockIn();
                        @endphp
                        <tr>
                            <td>
                                {{ $item->product->name }}
                                <input type="hidden" name="lines[{{ $i }}][product_id]" value="{{ $item->product_id }}">
                            </td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">
                                @if(! $isReceivable)
                                    <span class="badge bg-warning-subtle text-warning">Dispatch pending</span>
                                    <input type="hidden" name="lines[{{ $i }}][quantity]" value="0">
                                @elseif($pending > 0)
                                    <input type="number" step="0.01" min="0" max="{{ $pending }}" name="lines[{{ $i }}][quantity]" value="{{ $pending }}" class="form-control form-control-sm text-end">
                                @else
                                    <span class="badge bg-success-subtle text-success">Done</span>
                                    <input type="hidden" name="lines[{{ $i }}][quantity]" value="0">
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3" style="max-width: 16rem;">
                <label class="form-label small" for="moved_at">Stock in date</label>
                <input type="date" name="moved_at" id="moved_at"
                    value="{{ old('moved_at', now()->toDateString()) }}"
                    class="form-control form-control-sm @error('moved_at') is-invalid @enderror" required>
                @error('moved_at')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-check2-circle me-1"></i>Confirm Stock In
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
