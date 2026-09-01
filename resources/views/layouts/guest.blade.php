<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title', 'Sign in') — {{ config('app.name', 'Panchmukhi ERP') }}</title>
    <link rel="shortcut icon" href="{{ asset('img/p.png') }}" type="image/x-icon">
    @include('partials.bootstrap-head')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
</head>

<body class="auth-page">
    <div class="container-fluid g-0">
        <div class="row g-0 min-vh-100">
            @include('partials.auth-brand-panel')

            <div class="col-12 col-md-6 auth-form-column d-flex flex-column">
                <header class="auth-mobile-header d-md-none text-center px-3 border-bottom bg-white shadow-sm">
                    @include('partials.auth-logo', ['compact' => true])
                </header>

                <div class="flex-grow-1 d-flex align-items-center justify-content-center py-3 py-sm-4">
                    <div class="auth-form-inner">
                        <div class="card auth-card border-0">
                            <div class="card-body">
                                @yield('content')
                            </div>
                        </div>
                        <p class="auth-footer-note text-center fw-bold small mt-3 mb-0">
                            Secure access for authorized personnel only
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.bootstrap-scripts')
    @include('partials.auth-scripts')
    @stack('scripts')
</body>

</html>