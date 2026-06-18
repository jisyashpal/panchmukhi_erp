@php
    $masterOpen = false;
    $requestsOpen = request()->routeIs([
        'ho.product-requests.*',
        'ho.purchase-list.*',
        'ho.purchases.*',
    ]);
    $inventoryOpen = request()->routeIs([
        'ho.stocks.*',
        'ho.product-life.*',
    ]);
    $reportsOpen = request()->routeIs('ho.reports.*');
@endphp

<p class="sidebar-menu-label px-2 mb-1">HO Operations</p>

<a href="{{ route('ho.dashboard') }}" class="sidebar-link {{ request()->routeIs('ho.dashboard') ? 'active' : '' }}">
    <i class="bi bi-speedometer2"></i>
    <span>Dashboard</span>
</a>

<a href="{{ route('ho.notifications.index') }}"
    class="sidebar-link {{ request()->routeIs('ho.notifications.*') ? 'active' : '' }}">
    <i class="bi bi-bell"></i>
    <span>Notifications</span>
</a>

<div class="sidebar-menu-group {{ $requestsOpen ? 'is-open' : '' }}">
    <button class="sidebar-menu-toggle {{ $requestsOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse"
        data-bs-target="#menuHoRequests{{ $menuIdSuffix ?? '' }}" aria-expanded="{{ $requestsOpen ? 'true' : 'false' }}">
        <span class="d-flex align-items-center gap-2">
            <i class="bi bi-clipboard-check"></i>
            <span>Requests & PO</span>
        </span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $requestsOpen ? 'show' : '' }}" id="menuHoRequests{{ $menuIdSuffix ?? '' }}">
        <div class="sidebar-submenu">
            <a href="{{ route('ho.product-requests.index') }}"
                class="sidebar-sublink {{ request()->routeIs('ho.product-requests.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard-check"></i> Product Requests
            </a>
            <a href="{{ route('ho.purchase-list.index') }}"
                class="sidebar-sublink {{ request()->routeIs('ho.purchase-list.*') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i> Purchase List
            </a>
            <a href="{{ route('ho.purchases.create') }}"
                class="sidebar-sublink {{ request()->routeIs('ho.purchases.create') ? 'active' : '' }}">
                <i class="bi bi-plus-circle"></i> Create PO
            </a>
            <a href="{{ route('ho.purchases.index') }}"
                class="sidebar-sublink {{ request()->routeIs('ho.purchases.index', 'ho.purchases.show') ? 'active' : '' }}">
                <i class="bi bi-cart-check"></i> Purchases
            </a>
        </div>
    </div>
</div>

<div class="sidebar-menu-group {{ $inventoryOpen ? 'is-open' : '' }}">
    <button class="sidebar-menu-toggle {{ $inventoryOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse"
        data-bs-target="#menuHoInventory{{ $menuIdSuffix ?? '' }}" aria-expanded="{{ $inventoryOpen ? 'true' : 'false' }}">
        <span class="d-flex align-items-center gap-2">
            <i class="bi bi-archive"></i>
            <span>Inventory</span>
        </span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $inventoryOpen ? 'show' : '' }}" id="menuHoInventory{{ $menuIdSuffix ?? '' }}">
        <div class="sidebar-submenu">
            <a href="{{ route('ho.stocks.index') }}"
                class="sidebar-sublink {{ request()->routeIs('ho.stocks.index') ? 'active' : '' }}">
                <i class="bi bi-boxes"></i> Stocks
            </a>
            <a href="{{ route('ho.stocks.stock-in') }}"
                class="sidebar-sublink {{ request()->routeIs('ho.stocks.stock-in*') ? 'active' : '' }}">
                <i class="bi bi-box-arrow-in-down"></i> Stock In
            </a>
            <a href="{{ route('ho.stocks.stock-out') }}"
                class="sidebar-sublink {{ request()->routeIs('ho.stocks.stock-out*') ? 'active' : '' }}">
                <i class="bi bi-box-arrow-up"></i> Stock Out
            </a>
            <a href="{{ route('ho.product-life.index') }}"
                class="sidebar-sublink {{ request()->routeIs('ho.product-life.*') ? 'active' : '' }}">
                <i class="bi bi-hourglass-split"></i> Product Life
            </a>
        </div>
    </div>
</div>

<div class="sidebar-menu-group {{ $reportsOpen ? 'is-open' : '' }}">
    <button class="sidebar-menu-toggle {{ $reportsOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse"
        data-bs-target="#menuHoReports{{ $menuIdSuffix ?? '' }}" aria-expanded="{{ $reportsOpen ? 'true' : 'false' }}">
        <span class="d-flex align-items-center gap-2">
            <i class="bi bi-bar-chart-line"></i>
            <span>Reports</span>
        </span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $reportsOpen ? 'show' : '' }}" id="menuHoReports{{ $menuIdSuffix ?? '' }}">
        <div class="sidebar-submenu">
            <a href="{{ route('ho.reports.product-life') }}" class="sidebar-sublink {{ request()->routeIs('ho.reports.product-life') ? 'active' : '' }}">
                <i class="bi bi-clock-history"></i> Product Life Usage
            </a>
            <a href="{{ route('ho.reports.category-purchase') }}" class="sidebar-sublink {{ request()->routeIs('ho.reports.category-purchase') ? 'active' : '' }}">
                <i class="bi bi-pie-chart"></i> Category Purchase
            </a>
            <a href="{{ route('ho.reports.multiple-usage') }}" class="sidebar-sublink {{ request()->routeIs('ho.reports.multiple-usage') ? 'active' : '' }}">
                <i class="bi bi-exclamation-diamond"></i> Multiple Usage
            </a>
            <a href="{{ route('ho.reports.usage-by-category') }}" class="sidebar-sublink {{ request()->routeIs('ho.reports.usage-by-category') ? 'active' : '' }}">
                <i class="bi bi-diagram-3"></i> Usage by Category
            </a>
        </div>
    </div>
</div>
