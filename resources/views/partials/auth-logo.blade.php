<a href="{{ route('login') }}" class="d-inline-flex align-items-center gap-3 text-decoration-none {{ ($compact ?? false) ? 'text-dark' : 'text-white' }}">
    <span class="auth-logo-wrap rounded d-flex align-items-center justify-content-center flex-shrink-0">
        <img src="{{ asset('img/p.png') }}" alt="Panchmukhi" class="auth-logo-img" width="52" height="52">
    </span>
    <span>
        <strong class="d-block fs-5">Panchmukhi</strong>
        <small class="{{ ($compact ?? false) ? 'text-muted' : 'text-white-50' }}">
            {{ ($compact ?? false) ? 'ERP · Head Office' : 'Enterprise Resource Planning' }}
        </small>
    </span>
</a>
