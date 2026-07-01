@php
    $pageTitle = $pageTitle ?? 'Admin';
@endphp
<header class="admin-topbar">
    <div class="admin-topbar-start">
        <button class="admin-menu-btn d-lg-none flex-shrink-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebarOffcanvas" aria-label="Open menu">
            <i class="bi bi-list fs-5"></i>
        </button>
    
        <div class="admin-topbar-brand d-none d-md-flex align-items-center gap-2 flex-shrink-0">
            <a href="{{ route('admin.dashboard') }}" class="admin-topbar-logo" title="Dashboard">
                <i class="bi bi-grid-1x2-fill"></i>
            </a>
        </div>

        <div class="admin-topbar-heading min-w-0">
            <nav class="admin-topbar-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Home</a>
                <i class="bi bi-chevron-right"></i>
                <span>{{ $pageTitle }}</span>
            </nav>
            <h1 class="admin-topbar-title text-truncate">{{ $pageTitle }}</h1>
        </div>
    </div>

    <div class="admin-topbar-center d-none d-lg-flex">
        <form method="GET" action="{{ route('admin.products.index') }}" class="admin-topbar-search">
            <i class="bi bi-search"></i>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search products..." aria-label="Search products">
        </form>
    </div>

    <div class="admin-topbar-end">
        <span class="admin-topbar-date d-none d-xl-inline-flex">
            <i class="bi bi-calendar3"></i>
            {{ now()->format('l, d M Y') }}
        </span>

        <a href="{{ route('admin.dashboard') }}" class="admin-topbar-icon-btn d-none d-sm-inline-flex" title="Dashboard">
            <i class="bi bi-speedometer2"></i>
        </a>

        <div class="dropdown">
            <button type="button" class="admin-topbar-icon-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Quick links">
                <i class="bi bi-grid-3x3-gap"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end admin-topbar-dropdown shadow border-0">
                <li class="dropdown-header small text-uppercase">Quick access</li>
                <li><a class="dropdown-item" href="{{ route('admin.plants.index') }}"><i class="bi bi-building me-2"></i>Plants</a></li>
                <li><a class="dropdown-item" href="{{ route('admin.products.index') }}"><i class="bi bi-box-seam me-2"></i>Products</a></li>
                <li><a class="dropdown-item" href="{{ route('ho.product-requests.index') }}"><i class="bi bi-clipboard-check me-2"></i>Product Requests</a></li>
                <li><a class="dropdown-item" href="{{ route('ho.purchases.index') }}"><i class="bi bi-cart me-2"></i>Purchases</a></li>
                <li><a class="dropdown-item" href="{{ route('ho.stocks.index') }}"><i class="bi bi-boxes me-2"></i>Stock</a></li>
            </ul>
        </div>

        @hasSection('topbar-actions')
            <div class="admin-topbar-page-actions d-none d-md-flex align-items-center gap-2">
                @yield('topbar-actions')
            </div>
        @endif

        @include('partials.notification-bell', ['indexRoute' => 'admin.notifications.index', 'readRoute' => 'admin.notifications.read'])

        <div class="dropdown">
            <a href="#" class="admin-user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="admin-user-chip-avatar">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="d-none d-sm-block text-start">
                    <span class="d-block small fw-semibold lh-1 text-truncate" style="max-width: 120px;">{{ auth()->user()->name }}</span>
                    <span class="text-muted" style="font-size: .7rem;">Super Admin</span>
                </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end admin-topbar-dropdown shadow border-0">
                <li class="px-3 py-2 border-bottom">
                    <div class="small fw-semibold">{{ auth()->user()->name }}</div>
                    <div class="text-muted" style="font-size: .75rem;">{{ auth()->user()->email }}</div>
                </li>
                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                <li><a class="dropdown-item" href="{{ route('admin.profile.edit') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
