@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<div class="auth-form-header text-center mb-4">
    <div class="auth-form-logo d-none d-md-flex justify-content-center ">
        <img src="{{ asset('img/p.png') }}" alt="Panchmukhi" class="auth-logo-img rounded flex-shrink-0">
    </div>
    <h1 class="auth-form-title h4 fw-bold mb-2">Welcome back</h1>
    <p class="auth-form-subtitle text-muted small mb-0">Sign in to your Head Office account</p>
</div>

@if (session('status'))
<div class="alert alert-success auth-form-alert d-flex align-items-start gap-2 mb-4" role="alert">
    <i class="bi bi-check-circle-fill flex-shrink-0 mt-1" aria-hidden="true"></i>
    <span>{{ session('status') }}</span>
</div>
@endif

@include('partials.auth-form-errors')

<form method="POST" action="{{ route('login', absolute: false) }}" novalidate class="auth-form">
    @csrf

    <x-auth.field
        label="Email address"
        name="email"
        type="email"
        icon="bi-envelope"
        placeholder="you@company.com"
        :required="true"
        :autofocus="true"
        autocomplete="username" />

    <x-auth.field
        label="Password"
        name="password"
        type="password"
        icon="bi-lock"
        placeholder="Enter your password"
        :required="true"
        :toggle="true"
        autocomplete="current-password" />

    <div class="auth-form-actions mb-4">
        <div class="form-check mb-0">
            <input type="checkbox" name="remember" id="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">Keep me signed in</label>
        </div>
        @if (Route::has('password.request'))
        <a href="{{ route('password.request') }}" class="auth-link small text-nowrap">Forgot password?</a>
        @endif
    </div>

    <div class="d-grid">
        <button type="submit" class="btn btn-primary btn-lg auth-btn-submit">
            <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>Sign in
        </button>
    </div>
</form>

<!-- @if (config('app.debug'))
<div class="auth-demo-hint mt-4">
    <div class="auth-demo-hint-inner small text-center">
        <span class="text-muted">Local demo</span>
        <code>admin@gmail.com</code>
        <span class="text-muted">/</span>
        <code>12345678</code>
    </div>
</div>
@endif -->
@endsection