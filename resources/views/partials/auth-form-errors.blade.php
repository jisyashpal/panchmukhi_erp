@if ($errors->count() > 1)
<div class="auth-form-alert alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1" aria-hidden="true"></i>
    <div class="flex-grow-1 min-w-0">
        <p class="fw-semibold mb-2">Please fix the following</p>
        <ul class="mb-0 ps-3 auth-form-alert-list">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif