@props([
    'permission' => null,
    'modules' => [],
])

<x-admin-form-input
    label="Permission Name"
    name="name"
    :value="old('name', $permission?->name)"
    required
/>

<x-admin-form-group label="Slug" name="slug">
    <input
        type="text"
        name="slug"
        id="slug"
        value="{{ old('slug', $permission?->slug) }}"
        class="form-control @error('slug') is-invalid @enderror"
        @if($permission?->is_system) readonly @endif
    >
    @if($permission?->is_system)
        <div class="form-text">System permission slug cannot be changed.</div>
    @else
        <div class="form-text">Use module.action format, e.g. plant.manage_stocks</div>
    @endif
</x-admin-form-group>

<x-admin-form-group label="Module" name="module" required>
    <select name="module" id="module" class="form-select" required @if($permission?->is_system) disabled @endif>
        @foreach($modules as $key => $label)
            <option value="{{ $key }}" @selected(old('module', $permission?->module) === $key)>{{ $label }}</option>
        @endforeach
    </select>
    @if($permission?->is_system)
        <input type="hidden" name="module" value="{{ $permission->module }}">
    @endif
</x-admin-form-group>

<x-admin-form-group label="Description" name="description" col="col-md-12">
    <textarea name="description" id="description" class="form-control" rows="2">{{ old('description', $permission?->description) }}</textarea>
</x-admin-form-group>

<x-admin-form-input
    label="Sort Order"
    name="sort_order"
    type="number"
    :value="old('sort_order', $permission?->sort_order ?? 10)"
/>
