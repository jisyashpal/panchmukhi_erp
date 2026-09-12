@props(['label', 'name' => null, 'col' => 'col-md-4', 'required' => false, 'errorBag' => null, 'createHref' => null, 'createLabel' => 'Create', 'labelColor' => ''])

<div {{ $attributes->merge(['class' => trim($col.' admin-form-group')]) }}>
    @if ($label)
        <div class="d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
            <label class="form-label mb-0 {{ $labelColor }}" @if($name) for="{{ $name }}" @endif>
                {{ $label }}
                @if($required)<span class="text-danger">*</span>@endif
            </label>
            @if ($createHref)
                <a href="{{ $createHref }}" class="admin-form-create-link small text-decoration-none">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>{{ $createLabel }}
                </a>
            @endif
        </div>
    @endif
    {{ $slot }}
    @if($name)
        @error($name, $errorBag)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    @endif
</div>
