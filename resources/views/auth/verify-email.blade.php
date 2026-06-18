@extends('layouts.guest')
@section('title', 'Verify email')
@section('content')
    <div class="text-center mb-4">
        <h1 class="h4 fw-bold mb-1">Verify email</h1>
        <p class="text-muted small mb-0">Thanks for signing up! Click the link we sent to your email, or request a new one below.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
    <div class="alert alert-success" role="alert">{{ __('A new verification link has been sent.') }}</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
        @csrf
        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-envelope-check me-1"></i> Resend verification email
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <div class="d-grid">
            <button type="submit" class="btn btn-outline-secondary">Log out</button>
        </div>
    </form>
@endsection
