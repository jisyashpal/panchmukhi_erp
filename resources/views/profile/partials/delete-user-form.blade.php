@php
    $destroyRoute = $destroyRoute ?? route('profile.destroy');
@endphp

<section>
    <p class="text-muted small mb-3">{{ __('Once your account is deleted, all of its resources and data will be permanently deleted.') }}</p>

    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
        <i class="bi bi-trash me-1"></i>{{ __('Delete Account') }}
    </button>

    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="post" action="{{ $destroyRoute }}">
                    @csrf
                    @method('delete')
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title text-danger">{{ __('Delete account?') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">{{ __('Enter your password to confirm permanent deletion.') }}</p>
                        <input type="password" name="password" class="form-control @error('password', 'userDeletion') is-invalid @enderror" placeholder="{{ __('Password') }}" required>
                        @error('password', 'userDeletion')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-admin-outline" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('Delete Account') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

@if($errors->userDeletion->isNotEmpty())
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal('#deleteAccountModal').show();
    });
</script>
@endpush
@endif
