@props(['label', 'name', 'type' => 'text', 'value' => null, 'required' => false, 'col' => 'col-md-4', 'errorBag' => null])

<x-admin-form-group :label="$label" :name="$name" :col="$col" :required="$required" :error-bag="$errorBag">
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if($required) required @endif
        {{ $attributes->merge(['class' => 'form-control'.($errors->has($name, $errorBag) ? ' is-invalid' : '')]) }}
    >
</x-admin-form-group>
