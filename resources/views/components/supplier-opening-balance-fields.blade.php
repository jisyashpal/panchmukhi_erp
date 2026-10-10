@props(['supplier' => null, 'editable' => true])

<div style="display: contents;">

@php
    $amount = old('opening_balance', $supplier?->opening_balance);
    $type = old('opening_balance_type', $supplier?->opening_balance_type ?? 'debit');
    $date = old('opening_balance_date', $supplier?->opening_balance_date?->format('Y-m-d'));
    $typeLabel = $supplier?->opening_balance_type === 'credit'
        ? 'Credit (advance already paid)'
        : 'Debit (amount payable)';
@endphp

<div class="col-12">
    <hr class="my-1">
    <h6 class="mb-1">Opening Balance</h6>
    <p class="small text-muted mb-0">Debit means this party is already owed. Credit means an advance was already paid. Leave the amount blank if there is no opening balance.</p>
</div>

@if($editable)
    <x-admin-form-group label="Amount" name="opening_balance">
        <input
            id="opening_balance"
            name="opening_balance"
            type="number"
            min="0"
            step="0.01"
            value="{{ $amount }}"
            class="form-control @error('opening_balance') is-invalid @enderror"
        >
    </x-admin-form-group>
    <x-admin-form-group label="Type" name="opening_balance_type">
        <select
            id="opening_balance_type"
            name="opening_balance_type"
            class="form-select @error('opening_balance_type') is-invalid @enderror"
        >
            <option value="debit" @selected($type === 'debit')>Debit (amount payable)</option>
            <option value="credit" @selected($type === 'credit')>Credit (advance already paid)</option>
        </select>
    </x-admin-form-group>
    <x-admin-form-group label="As-on date" name="opening_balance_date">
        <input
            id="opening_balance_date"
            name="opening_balance_date"
            type="date"
            value="{{ $date }}"
            class="form-control @error('opening_balance_date') is-invalid @enderror"
        >
    </x-admin-form-group>
@elseif($supplier?->hasOpeningBalance())
    <x-admin-form-group label="Amount" name="opening_balance_display">
        <input type="text" class="form-control" value="{{ number_format((float) $supplier->opening_balance, 2) }}" disabled>
    </x-admin-form-group>
    <x-admin-form-group label="Type" name="opening_balance_type_display">
        <input type="text" class="form-control" value="{{ $typeLabel }}" disabled>
    </x-admin-form-group>
    <x-admin-form-group label="As-on date" name="opening_balance_date_display">
        <input type="text" class="form-control" value="{{ $supplier->opening_balance_date->format('d M Y') }}" disabled>
    </x-admin-form-group>
@else
    <div class="col-12">
        <span class="text-muted small">Not set</span>
    </div>
@endif
</div>
