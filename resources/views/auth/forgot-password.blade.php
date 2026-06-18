@extends('layouts.guest')
@section('title', 'Forgot password')
@section('content')
    <a href="{{ route('login') }}" class="btn btn-link btn-sm text-muted text-decoration-none px-0 mb-3">
        <i class="bi bi-arrow-left"></i> Back to sign in
    </a>

    <div class="text-center mb-4">
        <h1 class="h4 fw-bold mb-1">Forgot password?</h1>
        <p class="text-muted small mb-0">Enter your email and we will send a reset link</p>
    </div>

    @if (session('status'))
    <div class="alert alert-success auth-form-alert d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="bi bi-check-circle-fill flex-shrink-0 mt-1" aria-hidden="true"></i>
        <span>{{ session('status') }}</span>
    </div>
    @endif

    @include('partials.auth-form-errors')

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <x-auth.field
            label="Email address"
            name="email"
            type="email"
            icon="bi-envelope"
            placeholder="you@company.com"
            :required="true"
            :autofocus="true"
        />

        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-send me-1"></i> Send reset link
            </button>
        </div>
    </form>
@endsection
