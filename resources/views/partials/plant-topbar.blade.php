@php
    $pageTitle = $pageTitle ?? 'Plant';
    $user = auth()->user();
    $user->loadMissing(['role', 'plant']);
@endphp
<header class="admin-topbar">
    <div class="admin-topbar-start">
        <button class="admin-menu-btn d-lg-none flex-shrink-0" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#plantSidebarOffcanvas" aria-label="Open menu">
            <i class="bi bi-list fs-5"></i>
        </button>

        <div class="admin-topbar-brand d-none d-md-flex align-items-center gap-2 flex-shrink-0">
            <a href="{{ route('plant.dashboard') }}" class="admin-topbar-logo" title="Dashboard">
                <i class="bi bi-building"></i>
            </a>
        </div>

        <div class="admin-topbar-heading min-w-0">
            <nav class="admin-topbar-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('plant.dashboard') }}">Plant</a>
                <i class="bi bi-chevron-right"></i>
                <span>{{ $pageTitle }}</span>
            </nav>
            <h1 class="admin-topbar-title text-truncate">{{ $pageTitle }}</h1>
            @if($user->plant)
                <small class="text-muted d-block text-truncate" style="font-size: .75rem;">
                    {{ $user->plant->name }} · {{ $user->plant->code }}
                </small>
            @endif
        </div>
    </div>

    <div class="admin-topbar-end">
        @include('partials.notification-bell', [
            'indexRoute' => 'plant.notifications.index',
            'readRoute' => 'plant.notifications.read',
            'readAllRoute' => 'plant.notifications.read-all',
        ])

        <div class="dropdown">
            <a href="#" class="admin-user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" role="button">
                <span class="admin-user-chip-avatar">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </span>
                <span class="d-none d-sm-block text-start">
                    <span class="d-block small fw-semibold lh-1 text-truncate" style="max-width: 120px;">{{ $user->name }}</span>
                    <span class="text-muted" style="font-size: .7rem;">{{ $user->role?->name ?? 'Plant User' }}</span>
                </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end admin-topbar-dropdown shadow border-0">
                <li class="px-3 py-2 border-bottom">
                    <div class="small fw-semibold">{{ $user->name }}</div>
                    <div class="text-muted text-truncate" style="font-size: .75rem;">{{ $user->email }}</div>
                    @if($user->plant)
                        <div class="text-muted mt-1" style="font-size: .7rem;">
                            <i class="bi bi-geo-alt me-1"></i>{{ $user->plant->name }}
                        </div>
                    @endif
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('plant.dashboard') }}">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('plant.profile.edit') }}">
                        <i class="bi bi-person me-2"></i>Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ route('plant.notifications.index') }}">
                        <span><i class="bi bi-bell me-2"></i>Notifications</span>
                        @php $unreadCount = $user->unreadNotifications()->count(); @endphp
                        @if($unreadCount > 0)
                            <span class="badge bg-danger rounded-pill">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                        @endif
                    </a>
                </li>
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
