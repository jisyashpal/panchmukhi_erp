@if (session('success'))
    <div class="admin-alert admin-alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <span>{{ session('success') }}</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('warning'))
    @php
        $warningItems = session('warning');
        $warningItems = is_array($warningItems) ? $warningItems : [$warningItems];
    @endphp
    <div class="admin-alert admin-alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            <strong>{{ count($warningItems) }} row(s) skipped during bulk upload</strong>
            <ul class="mb-0 mt-2 ps-3 small">
                @foreach(array_slice($warningItems, 0, 25) as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
            @if(count($warningItems) > 25)
                <div class="small mt-1">…and {{ count($warningItems) - 25 }} more.</div>
            @endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="admin-alert admin-alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>{{ session('error') }}</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
