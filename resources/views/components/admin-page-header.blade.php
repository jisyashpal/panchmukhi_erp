@props(['title', 'subtitle' => null])

<div class="admin-page-header">
    <div>
        <h1>{{ $title }}</h1>
        @if($subtitle)
            <p class="fw-semibold text-shadow-sm  mb-0 mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @if(trim($slot))
        <div class="admin-page-actions">
            {{ $slot }}
        </div>
    @endif
</div>
