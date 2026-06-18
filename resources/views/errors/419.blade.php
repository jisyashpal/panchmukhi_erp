@extends('layouts.guest')
@section('title', 'Session expired')
@section('content')
    <div class="text-center">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning mb-3" style="width:64px;height:64px">
            <i class="bi bi-clock-history fs-3"></i>
        </span>
        <h1 class="h4 fw-bold mb-2">Session expired</h1>
        <p class="text-muted small mb-4">
            Your session or security token expired. This often happens if the page was open too long,
            or if you use <code>localhost</code> in one tab and <code>127.0.0.1</code> in another.
        </p>
        <div class="d-grid">
            <a href="{{ route('login') }}" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right me-1"></i> Back to sign in
            </a>
        </div>
    </div>
@endsection
