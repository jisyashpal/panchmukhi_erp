@extends('layouts.guest')
@section('title', 'Reset password')
@section('content')
    <div class="text-center mb-4">
        <h1 class="h4 fw-bold mb-1">Reset password</h1>
        <p class="text-muted small mb-0">Choose a new password for your account</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-auth.field
            label="Email"
            name="email"
            type="email"
            icon="bi-envelope"
            :value="$request->email"
            :required="true"
        />

        <x-auth.field
            label="New password"
            name="password"
            type="password"
            icon="bi-lock"
            :required="true"
            :toggle="true"
        />

        <x-auth.field
            label="Confirm password"
            name="password_confirmation"
            type="password"
            icon="bi-lock-fill"
            :required="true"
            :toggle="true"
        />

        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check2-circle me-1"></i> Reset password
            </button>
        </div>
    </form>
@endsection
