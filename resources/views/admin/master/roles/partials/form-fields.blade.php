@props([
    'role' => null,
    'permissionsByModule' => [],
    'assignedPermissionIds' => [],
])

<x-admin-form-input
    label="Role Name"
    name="name"
    :value="old('name', $role?->name)"
    required
/>

<x-admin-form-group label="Slug" name="slug">
    <input
        type="text"
        name="slug"
        id="slug"
        value="{{ old('slug', $role?->slug) }}"
        class="form-control @error('slug') is-invalid @enderror"
        @if($role?->is_system) readonly @endif
    >
    @if($role?->is_system)
        <div class="form-text">System role slug cannot be changed.</div>
    @else
        <div class="form-text">Leave empty to auto-generate from name.</div>
    @endif
</x-admin-form-group>

<x-admin-form-group label="Description" name="description" col="col-md-12">
    <textarea name="description" id="description" class="form-control" rows="2">{{ old('description', $role?->description) }}</textarea>
</x-admin-form-group>

<x-admin-form-input
    label="Sort Order"
    name="sort_order"
    type="number"
    :value="old('sort_order', $role?->sort_order ?? 10)"
/>

<div class="col-md-4">
    <div class="form-check mt-4">
        <input type="hidden" name="requires_plant" value="0">
        <input
            type="checkbox"
            name="requires_plant"
            value="1"
            class="form-check-input"
            id="requires_plant"
            @checked(old('requires_plant', $role?->requires_plant ?? true))
        >
        <label class="form-check-label" for="requires_plant">Requires plant assignment</label>
    </div>
</div>

<div class="col-md-4">
    <div class="form-check mt-4">
        <input type="hidden" name="is_active" value="0">
        <input
            type="checkbox"
            name="is_active"
            value="1"
            class="form-check-input"
            id="active"
            @checked(old('is_active', $role?->is_active ?? true))
        >
        <label class="form-check-label" for="active">Active</label>
    </div>
</div>

@include('admin.master.roles.partials.permission-fields', [
    'permissionsByModule' => $permissionsByModule,
    'assignedPermissionIds' => $assignedPermissionIds,
])
