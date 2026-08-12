<aside class="sidebar-admin bg-dark text-white d-none d-lg-flex flex-column flex-shrink-0">
    <div class="sidebar-brand px-3 py-3 border-bottom border-secondary">
        <a href="{{ route('admin.dashboard') }}" class="text-white text-decoration-none d-flex align-items-center gap-2">
            <span class="sidebar-brand-icon rounded  d-flex align-items-center justify-content-center">
                <img src="{{ asset('img/PMCMPL.png') }}" alt="Panchmukhi" class="img-fluid rounded flex-shrink-0">
            </span>
            <!-- <span>
                <strong class="d-block lh-sm">Panchmukhi</strong>
                <small class="text-white-50">Head Office</small>
            </span> -->
        </a>
    </div>

    <nav class="sidebar-nav flex-grow-1 overflow-auto py-2 px-2">
        <x-portal-sidebar-menu portal="admin" />
    </nav>

    <div class="sidebar-footer border-top border-secondary p-3 mt-auto">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span
                class="sidebar-user-avatar rounded-circle bg-secondary d-flex align-items-center justify-content-center">
                <i class="bi bi-person-fill"></i>
            </span>
            <div class="overflow-hidden">
                <div class="small fw-semibold text-truncate">{{ auth()->user()->name }}</div>
                <div class="text-white-50 text-truncate" style="font-size: .7rem;">Super Admin</div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm w-100">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </button>
        </form>
    </div>
</aside>
