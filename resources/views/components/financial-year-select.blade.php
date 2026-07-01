@props([
    'compact' => false,
    'name' => 'fy',
    'selected' => null,
    'formMethod' => null,
    'formAction' => null,
])

@php
    $selected = $selected ?? ($financialYear->label ?? null);
    $options = $financialYearOptions ?? [];
@endphp

@if($formAction)
    <form method="{{ $formMethod ?? 'POST' }}" action="{{ $formAction }}" class="d-inline">
        @csrf
@endif

<select
    name="{{ $name }}"
    class="form-select {{ $compact ? 'form-select-sm' : '' }} financial-year-select"
    @if($formAction) onchange="this.form.submit()" @endif
    {{ $attributes }}
>
    @foreach($options as $value => $label)
        <option value="{{ $value }}" @selected($selected === $value)>{{ $label }}</option>
    @endforeach
</select>

@if($formAction)
    </form>
@endif

@if(!$formAction)
    @once
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.financial-year-select').forEach(function (select) {
                        if (select.closest('form')?.action && select.closest('form').querySelector('input[name="_token"]')) {
                            return;
                        }
                        select.addEventListener('change', function () {
                            const form = this.closest('form');
                            if (!form) return;
                            
                            const dateFromInput = form.querySelector('input[name="date_from"]');
                            const dateToInput = form.querySelector('input[name="date_to"]');
                            
                            if (dateFromInput) dateFromInput.value = '';
                            if (dateToInput) dateToInput.value = '';
                            
                            form.submit();
                        });
                    });
                });
            </script>
        @endpush
    @endonce
@endif

