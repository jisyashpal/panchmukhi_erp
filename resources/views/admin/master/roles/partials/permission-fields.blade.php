@props([
    'permissionsByModule' => [],
    'assignedPermissionIds' => [],
])

@if($permissionsByModule === [])
    <div class="col-12">
        <div class="alert alert-light border mb-0">
            No permissions defined yet.
            <a href="{{ route('admin.permissions.create') }}">Add a permission</a> first.
        </div>
    </div>
@else
    <div class="col-12">
        <label class="form-label fw-semibold">Permissions</label>
        <p class="text-muted small mb-3">Select what users with this role can do across each portal.</p>
    </div>

    @foreach($permissionsByModule as $module => $permissions)
        <div class="col-12">
            <div class="border rounded p-3 mb-2">
                <div class="fw-semibold small text-uppercase text-muted mb-2">
                    {{ config("permissions.modules.{$module}", ucfirst($module)) }}
                </div>
                <div class="row g-2">
                    @foreach($permissions as $permission)
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="permission_ids[]"
                                    value="{{ $permission->id }}"
                                    class="form-check-input"
                                    id="permission_{{ $permission->id }}"
                                    @checked(in_array($permission->id, old('permission_ids', $assignedPermissionIds)))
                                >
                                <label class="form-check-label" for="permission_{{ $permission->id }}">
                                    {{ $permission->name }}
                                    <code class="small text-muted d-block">{{ $permission->slug }}</code>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
@endif
