@php
    $pageTitle = $pageTitle ?? 'HO Operations';
@endphp
<header class="admin-topbar">
    <div class="admin-topbar-start">
        <button class="admin-menu-btn d-lg-none flex-shrink-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#hoSidebarOffcanvas" aria-label="Open menu">
            <i class="bi bi-list fs-5"></i>
        </button>

        <div class="admin-topbar-brand d-none d-md-flex align-items-center gap-2 flex-shrink-0">
            <a href="{{ route('ho.dashboard') }}" class="admin-topbar-logo" title="Dashboard">
                <i class="bi bi-database-gear"></i>
            </a>
        </div>

        <div class="admin-topbar-heading min-w-0">
            <nav class="admin-topbar-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('ho.dashboard') }}">Home</a>
                <i class="bi bi-chevron-right"></i>
                <span>{{ $pageTitle }}</span>
            </nav>
            <h1 class="admin-topbar-title text-truncate">{{ $pageTitle }}</h1>
        </div>
    </div>

    <div class="admin-topbar-end">
        @isset($financialYear)
            <div class="d-none d-lg-flex align-items-center gap-2 me-2">
                <!-- <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    <i class="bi bi-calendar-range me-1"></i>{{ $financialYear->displayLabel() }}
                </span> -->
                <x-financial-year-select
                    compact
                    :form-action="route('ho.financial-year.set')"
                    style="min-width: 8rem;"
                />
            </div>
        @endisset

        <span class="admin-topbar-date d-none d-xl-inline-flex">
            <i class="bi bi-calendar3"></i>
            {{ now()->format('l, d M Y') }}
        </span>

        @include('partials.notification-bell', ['indexRoute' => 'ho.notifications.index', 'readRoute' => 'ho.notifications.read'])

        <div class="dropdown">
            <a href="#" class="admin-user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="admin-user-chip-avatar">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="d-none d-sm-block text-start">
                    <span class="d-block small fw-semibold lh-1 text-truncate" style="max-width: 120px;">{{ auth()->user()->name }}</span>
                    <span class="text-muted" style="font-size: .7rem;">HO Data Operator</span>
                </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end admin-topbar-dropdown shadow border-0">
                <li class="px-3 py-2 border-bottom">
                    <div class="small fw-semibold">{{ auth()->user()->name }}</div>
                    <div class="text-muted" style="font-size: .75rem;">{{ auth()->user()->email }}</div>
                </li>
                <li><a class="dropdown-item" href="{{ route('ho.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                <li><a class="dropdown-item" href="{{ route('ho.profile.edit') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
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
