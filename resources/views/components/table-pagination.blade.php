@props(['paginator', 'label' => 'records'])

@if($paginator->total() > 0)
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-3">
        <p class="small text-muted mb-0">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $label }}
        </p>
        @if($paginator->hasPages())
            <div>{{ $paginator->withQueryString()->links('pagination.links-only') }}</div>
        @endif
    </div>
@endif
