@extends('layouts.guest')
@section('title', 'Confirm password')
@section('content')
    <div class="text-center mb-4">
        <h1 class="h4 fw-bold mb-1">Confirm password</h1>
        <p class="text-muted small mb-0">This is a secure area. Please confirm your password to continue.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <x-auth.field
            label="Password"
            name="password"
            type="password"
            icon="bi-lock"
            :required="true"
            :autofocus="true"
            :toggle="true"
        />

        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-shield-check me-1"></i> Confirm
            </button>
        </div>
    </form>
@endsection
