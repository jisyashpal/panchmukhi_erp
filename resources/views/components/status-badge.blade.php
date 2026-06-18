@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $class = match($value) {
        'draft' => 'bg-secondary',
        'submitted' => 'bg-info text-dark',
        'in_progress' => 'bg-warning text-dark',
        'completed', 'delivered' => 'bg-success',
        'cancelled' => 'bg-danger',
        'ordered' => 'bg-primary',
        'partial' => 'bg-info text-dark',
        default => 'bg-secondary',
    };
    $label = $status instanceof \BackedEnum && method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', $value));
@endphp

<span {{ $attributes->merge(['class' => "badge $class"]) }}>{{ $label }}</span>
