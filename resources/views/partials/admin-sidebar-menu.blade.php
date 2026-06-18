@php
    $masterOpen = request()->routeIs(['admin.sites.*', 'admin.suppliers.*', 'admin.roles.*', 'admin.users.*']);
    $productOpen = request()->routeIs([
        'admin.categories.*',
        'admin.sub-categories.*',
        'admin.brands.*',
        'admin.units.*',
        'admin.products.*',
    ]);
    $oversightOpen = request()->routeIs('ho.*');
@endphp

<p class="sidebar-menu-label px-2 mb-1">Main Menu</p>

<a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
    <i class="bi bi-speedometer2"></i>
    <span>Dashboard</span>
</a>

<a href="{{ route('admin.notifications.index') }}"
    class="sidebar-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
    <i class="bi bi-bell"></i>
    <span>Notifications</span>
</a>

<div class="sidebar-menu-group {{ $masterOpen ? 'is-open' : '' }}">
    <button class="sidebar-menu-toggle {{ $masterOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse"
        data-bs-target="#menuMaster{{ $menuIdSuffix ?? '' }}" aria-expanded="{{ $masterOpen ? 'true' : 'false' }}">
        <span class="d-flex align-items-center gap-2">
            <i class="bi bi-building"></i>
            <span>Master</span>
        </span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $masterOpen ? 'show' : '' }}" id="menuMaster{{ $menuIdSuffix ?? '' }}">
        <div class="sidebar-submenu">
            <a href="{{ route('admin.sites.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.sites.*') ? 'active' : '' }}">
                <i class="bi bi-geo-alt"></i> Sites / Plants
            </a>
            <a href="{{ route('admin.suppliers.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}">
                <i class="bi bi-truck"></i> Suppliers
            </a>
            <a href="{{ route('admin.roles.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                <i class="bi bi-shield-lock"></i> User Roles
            </a>
            <a href="{{ route('admin.users.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i> Users
            </a>
        </div>
    </div>
</div>

<div class="sidebar-menu-group {{ $productOpen ? 'is-open' : '' }}">
    <button class="sidebar-menu-toggle {{ $productOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse"
        data-bs-target="#menuProduct{{ $menuIdSuffix ?? '' }}" aria-expanded="{{ $productOpen ? 'true' : 'false' }}">
        <span class="d-flex align-items-center gap-2">
            <i class="bi bi-box-seam"></i>
            <span>Product Catalog</span>
        </span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $productOpen ? 'show' : '' }}" id="menuProduct{{ $menuIdSuffix ?? '' }}">
        <div class="sidebar-submenu">
            <a href="{{ route('admin.categories.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                <i class="bi bi-folder"></i> Categories
            </a>
            <a href="{{ route('admin.sub-categories.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.sub-categories.*') ? 'active' : '' }}">
                <i class="bi bi-folder2-open"></i> Sub Categories
            </a>
            <a href="{{ route('admin.brands.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.brands.*') ? 'active' : '' }}">
                <i class="bi bi-tag"></i> Brands
            </a>
            <a href="{{ route('admin.units.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.units.*') ? 'active' : '' }}">
                <i class="bi bi-rulers"></i> Units
            </a>
            <a href="{{ route('admin.products.index') }}" class="sidebar-sublink {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                <i class="bi bi-box"></i> Products
            </a>
        </div>
    </div>
</div>

<div class="sidebar-menu-group {{ $oversightOpen ? 'is-open' : '' }}">
    <button class="sidebar-menu-toggle {{ $oversightOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse"
        data-bs-target="#menuOversight{{ $menuIdSuffix ?? '' }}" aria-expanded="{{ $oversightOpen ? 'true' : 'false' }}">
        <span class="d-flex align-items-center gap-2">
            <i class="bi bi-eye"></i>
            <span>Operations Overview</span>
        </span>
        <i class="bi bi-chevron-down sidebar-chevron"></i>
    </button>
    <div class="collapse {{ $oversightOpen ? 'show' : '' }}" id="menuOversight{{ $menuIdSuffix ?? '' }}">
        <div class="sidebar-submenu">
            <a href="{{ route('ho.dashboard') }}" class="sidebar-sublink">
                <i class="bi bi-speedometer2"></i> HO Dashboard
            </a>
            <a href="{{ route('ho.product-requests.index') }}" class="sidebar-sublink">
                <i class="bi bi-clipboard-check"></i> Product Requests
            </a>
            <a href="{{ route('ho.purchases.index') }}" class="sidebar-sublink">
                <i class="bi bi-cart"></i> Purchases & PO
            </a>
            <a href="{{ route('ho.stocks.index') }}" class="sidebar-sublink">
                <i class="bi bi-boxes"></i> Stocks
            </a>
            <a href="{{ route('ho.reports.index') }}" class="sidebar-sublink">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>
        </div>
    </div>
</div>
