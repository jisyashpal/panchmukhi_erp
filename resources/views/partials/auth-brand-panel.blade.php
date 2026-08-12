<div class="col-md-6 d-none d-md-flex flex-column justify-content-between p-4 p-lg-5 text-white auth-brand-side">
    <div>
        @include('partials.auth-logo')
        <h2 class="display-6 fw-bold">Manage plants, stock &amp; purchases from one place</h2>
        <p class="text-white-50 mb-4">Head office control for plants, inventory, product requests, and plant-wise reporting.</p>
        <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
            <li class="d-flex align-items-center gap-3">
                <span class="auth-feature-icon rounded d-flex align-items-center justify-content-center">
                    <i class="bi bi-building"></i>
                </span>
                <span>Multi-plant management</span>
            </li>
            <li class="d-flex align-items-center gap-3">
                <span class="auth-feature-icon rounded d-flex align-items-center justify-content-center">
                    <i class="bi bi-boxes"></i>
                </span>
                <span>Real-time stock &amp; consumption</span>
            </li>
            <li class="d-flex align-items-center gap-3">
                <span class="auth-feature-icon rounded d-flex align-items-center justify-content-center">
                    <i class="bi bi-cart-check"></i>
                </span>
                <span>Purchase requests &amp; approvals</span>
            </li>
            <li class="d-flex align-items-center gap-3">
                <span class="auth-feature-icon rounded d-flex align-items-center justify-content-center">
                    <i class="bi bi-graph-up"></i>
                </span>
                <span>Dashboard &amp; plant-wise reports</span>
            </li>
        </ul>
    </div>
    <p class="text-white-50 small mb-0">&copy; {{ date('Y') }} {{ config('app.name', 'Panchmukhi ERP') }}. All rights reserved.</p>
</div>
