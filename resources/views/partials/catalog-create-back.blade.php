@if (request('redirect') === 'products.create')
    <a href="{{ route('admin.products.create', request()->only(['category_id', 'sub_category_id', 'brand_id', 'unit_id'])) }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to product
    </a>
@else
    <a href="{{ $fallback }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
@endif
