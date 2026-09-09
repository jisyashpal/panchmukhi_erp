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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <label class="form-label fw-semibold mb-0">Permissions</label>
                <p class="text-muted small mb-0">Select what users with this role can do across each portal.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-admin-outline btn-sm" data-permission-select-all>
                    <i class="bi bi-check2-square me-1"></i>Select all
                </button>
                <button type="button" class="btn btn-admin-outline btn-sm" data-permission-clear-all>
                    <i class="bi bi-square me-1"></i>Clear all
                </button>
            </div>
        </div>
    </div>

    @foreach($permissionsByModule as $module => $permissions)
        <div class="col-12">
            <div class="border rounded p-3 mb-2">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <div class="fw-semibold small text-uppercase text-muted">
                        {{ config("permissions.modules.{$module}", ucfirst($module)) }}
                    </div>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-permission-module="{{ $module }}">
                        Select all
                    </button>
                </div>
                <div class="row g-2">
                    @foreach($permissions as $permission)
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="permission_ids[]"
                                    value="{{ $permission->id }}"
                                    class="form-check-input permission-checkbox"
                                    data-permission-module="{{ $module }}"
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = () => Array.from(document.querySelectorAll('.permission-checkbox'));

        document.querySelector('[data-permission-select-all]')?.addEventListener('click', function () {
            checkboxes().forEach((checkbox) => checkbox.checked = true);
        });

        document.querySelector('[data-permission-clear-all]')?.addEventListener('click', function () {
            checkboxes().forEach((checkbox) => checkbox.checked = false);
        });

        document.querySelectorAll('[data-permission-module]').forEach(function (button) {
            if (button.matches('button')) {
                button.addEventListener('click', function () {
                    const module = button.dataset.permissionModule;
                    document.querySelectorAll('.permission-checkbox[data-permission-module="' + module + '"]')
                        .forEach((checkbox) => checkbox.checked = true);
                });
            }
        });
    });
</script>
@endpush
