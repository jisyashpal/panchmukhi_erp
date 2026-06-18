@php
    $profileUpdateRoute = $profileUpdateRoute ?? route('profile.update');
@endphp

<section>
    <p class="text-muted small mb-3">{{ __("Update your account's profile information and email address.") }}</p>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

    <form method="post" action="{{ $profileUpdateRoute }}">
        @csrf
        @method('patch')
        <div class="row g-3">
            <x-admin-form-input label="Name" name="name" :value="old('name', $user->name)" required />
            <x-admin-form-input label="Email" name="email" type="email" :value="old('email', $user->email)" required />
            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="col-12">
                    <p class="small mb-0">{{ __('Your email address is unverified.') }}
                        <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">{{ __('Re-send verification') }}</button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <div class="alert alert-success mt-2 mb-0">{{ __('A new verification link has been sent.') }}</div>
                    @endif
                </div>
            @endif
            <div class="col-12 d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-admin-primary">{{ __('Save Changes') }}</button>
                @if (session('status') === 'profile-updated')
                    <span class="text-success small"><i class="bi bi-check-circle me-1"></i>{{ __('Saved.') }}</span>
                @endif
            </div>
        </div>
    </form>
</section>
