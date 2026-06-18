@props([
    'label',
    'name',
    'type' => 'text',
    'icon' => 'bi-envelope',
    'placeholder' => '',
    'required' => false,
    'autofocus' => false,
    'autocomplete' => null,
    'value' => null,
    'toggle' => false,
])

@php
    $hasError = $errors->has($name);
@endphp

<div class="auth-field mb-3 {{ $hasError ? 'auth-field--invalid' : '' }}">
    <label for="{{ $name }}" class="form-label fw-semibold">{{ $label }}</label>
    <div class="input-group auth-input-group">
        <span class="input-group-text"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
        <input
            id="{{ $name }}"
            type="{{ $type }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($required) required @endif
            @if($autofocus) autofocus @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}
        >
        @if($toggle)
        <button type="button" class="btn btn-outline-secondary auth-toggle-password" data-target="{{ $name }}" aria-label="Show password">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
        @endif
    </div>
    @error($name)
    <div id="{{ $name }}-error" class="auth-field-error" role="alert">
        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
        <span>{{ $message }}</span>
    </div>
    @enderror
</div>
