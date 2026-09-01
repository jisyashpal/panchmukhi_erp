<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>@yield('title', 'HO Operations') — {{ config('app.name', 'Panchmukhi ERP') }}</title>
    <link rel="shortcut icon" href="{{ asset('img/p.png') }}" type="image/x-icon">
    @include('partials.bootstrap-head')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>

<body class="admin-body bg-light">
    <div class="admin-wrapper d-flex min-vh-100">
        @include('partials.ho-sidebar')

        <div class="admin-content flex-grow-1 d-flex flex-column min-vh-100">
            @include('partials.ho-topbar', [
                'pageTitle' => trim($__env->yieldContent('title')) ?: 'HO Operations',
            ])

            <main class="admin-main flex-grow-1">
                @include('partials.flash')
                @yield('content')
            </main>
        </div>
    </div>

    <div class="offcanvas offcanvas-start sidebar-admin text-white" tabindex="-1" id="hoSidebarOffcanvas" aria-labelledby="hoSidebarOffcanvasLabel">
        <div class="offcanvas-header border-bottom border-secondary border-opacity-25">
            <div class="d-flex align-items-center gap-2" id="hoSidebarOffcanvasLabel">
                <span class="sidebar-brand-icon rounded d-flex align-items-center justify-content-center">
                    <img src="{{ asset('img/PMCMPL.png') }}" alt="Panchmukhi" class="img-fluid rounded flex-shrink-0">
                </span>
                <!-- <div>
                    <strong class="d-block">Panchmukhi</strong>
                    <small class="text-white-50">HO Data Operator</small>
                </div> -->
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column">
            <nav class="sidebar-nav flex-grow-1 overflow-auto p-2">
                <x-portal-sidebar-menu portal="ho" menuIdSuffix="Mobile" />
            </nav>
            <div class="sidebar-footer p-3 border-top border-secondary border-opacity-25">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="sidebar-user-avatar rounded-circle d-flex align-items-center justify-content-center">
                        <i class="bi bi-person-fill"></i>
                    </span>
                    <div class="small fw-semibold text-truncate">{{ auth()->user()->name }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>

    @include('partials.bootstrap-scripts')
    <script>
        document.querySelectorAll('#hoSidebarOffcanvas .sidebar-link, #hoSidebarOffcanvas .sidebar-sublink').forEach(function(el) {
            el.addEventListener('click', function() {
                var canvas = bootstrap.Offcanvas.getInstance(document.getElementById('hoSidebarOffcanvas'));
                if (canvas) canvas.hide();
            });
        });
    </script>
    @stack('scripts')
</body>

</html>
