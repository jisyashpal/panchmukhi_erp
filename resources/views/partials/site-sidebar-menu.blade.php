@php
    $user = auth()->user();
    $requestsOpen = request()->routeIs([
        'site.product-requests.*',
        'site.purchase-requests.*',
    ]);
    $purchaserOpen = request()->routeIs([
        'site.suppliers.*',
        'site.dispatch.*',
        'site.purchases.*',
    ]);
@endphp

<p class="sidebar-menu-label px-2 mb-1">{{ $user->isPurchaser() ? 'Purchaser Menu' : 'Site Menu' }}</p>

<a href="{{ route('site.dashboard') }}"
   class="sidebar-link {{ request()->routeIs('site.dashboard') ? 'active' : '' }}">
    <i class="bi bi-speedometer2"></i>
    <span>Dashboard</span>
</a>

@if($user->isSiteInCharge())
    <a href="{{ route('site.product-requests.index') }}"
       class="sidebar-link {{ request()->routeIs('site.product-requests.*') ? 'active' : '' }}">
        <i class="bi bi-clipboard-check"></i>
        <span>Product Requests</span>
    </a>
@endif

@if($user->isSiteInCharge() || $user->isPurchaser())
    <a href="{{ route('site.purchase-requests.index') }}"
       class="sidebar-link {{ request()->routeIs('site.purchase-requests.*') ? 'active' : '' }}">
        <i class="bi bi-cart-check"></i>
        <span>Purchase Requests</span>
    </a>
@endif

@if($user->isPurchaser())
    <div class="sidebar-menu-group {{ $purchaserOpen ? 'is-open' : '' }}">
        <button class="sidebar-menu-toggle {{ $purchaserOpen ? '' : 'collapsed' }}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#menuPurchaser"
                aria-expanded="{{ $purchaserOpen ? 'true' : 'false' }}">
            <span class="d-flex align-items-center gap-2">
                <i class="bi bi-truck"></i>
                <span>Procurement</span>
            </span>
            <i class="bi bi-chevron-down sidebar-chevron"></i>
        </button>
        <div class="collapse {{ $purchaserOpen ? 'show' : '' }}" id="menuPurchaser">
            <div class="sidebar-submenu">
                <a href="{{ route('site.suppliers.index') }}" class="sidebar-sublink {{ request()->routeIs('site.suppliers.*') ? 'active' : '' }}">
                    <i class="bi bi-building"></i> Suppliers
                </a>
                <a href="{{ route('site.dispatch.index') }}" class="sidebar-sublink {{ request()->routeIs('site.dispatch.*') ? 'active' : '' }}">
                    <i class="bi bi-send"></i> Order Dispatch
                </a>
                <a href="{{ route('site.purchases.index') }}" class="sidebar-sublink {{ request()->routeIs('site.purchases.*') ? 'active' : '' }}">
                    <i class="bi bi-receipt"></i> Pending Orders
                </a>
            </div>
        </div>
    </div>
@endif

<a href="{{ route('site.notifications.index') }}"
   class="sidebar-link {{ request()->routeIs('site.notifications.*') ? 'active' : '' }}">
    <i class="bi bi-bell"></i>
    <span>Notifications</span>
</a>
