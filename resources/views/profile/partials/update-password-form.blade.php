@php
    $passwordUpdateRoute = $passwordUpdateRoute ?? route('password.update');
@endphp

<section>
    <p class="text-muted small mb-3">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>

    <form method="post" action="{{ $passwordUpdateRoute }}">
        @csrf
        @method('put')
        <div class="row g-3">
            <x-admin-form-input label="Current Password" name="current_password" type="password" autocomplete="current-password" error-bag="updatePassword" required />
            <x-admin-form-input label="New Password" name="password" type="password" autocomplete="new-password" error-bag="updatePassword" required />
            <x-admin-form-input label="Confirm Password" name="password_confirmation" type="password" autocomplete="new-password" required />
            <div class="col-12 d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-admin-primary">{{ __('Update Password') }}</button>
                @if (session('status') === 'password-updated')
                    <span class="text-success small"><i class="bi bi-check-circle me-1"></i>{{ __('Saved.') }}</span>
                @endif
            </div>
        </div>
    </form>
</section>
