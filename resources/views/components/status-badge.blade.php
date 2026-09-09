@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $class = match($value) {
        'low' => 'bg-secondary',
        'medium' => 'bg-info text-dark',
        'high' => 'bg-warning text-dark',
        'urgent' => 'bg-danger',
        'draft' => 'bg-secondary',
        'submitted' => 'bg-info text-dark',
        'in_progress' => 'bg-warning text-dark',
        'send_quotation' => 'bg-warning text-dark',
        'pending' => 'bg-warning text-dark',
        'create_po' => 'bg-info text-dark',
        'po_created' => 'bg-primary',
        'dispatch', 'dispatched' => 'bg-success',
        'completed', 'delivered' => 'bg-success',
        'cancelled' => 'bg-danger',
        'ordered' => 'bg-primary',
        'partial' => 'bg-info text-dark',
        default => 'bg-secondary',
    };
    $label = $status instanceof \BackedEnum && method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', $value));
@endphp

<span {{ $attributes->merge(['class' => "badge $class"]) }}>{{ $label }}</span>
