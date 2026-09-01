@php
    $portal = $portal ?? 'admin';
    $productCreateRoute = $portal.'.products.create';
    $returnQuery = [];
    $fieldMap = [
        'pc_name' => 'name',
        'pc_size' => 'size',
        'pc_sku_code' => 'sku_code',
        'pc_category_id' => 'category_id',
        'pc_sub_category_id' => 'sub_category_id',
        'pc_brand_id' => 'brand_id',
        'pc_unit_id' => 'unit_id',
        'pc_reorder_level' => 'reorder_level',
        'product_request_id' => 'product_request_id',
        'product_request_item_id' => 'product_request_item_id',
        'category_id' => 'category_id',
        'sub_category_id' => 'sub_category_id',
        'brand_id' => 'brand_id',
        'unit_id' => 'unit_id',
    ];
    foreach ($fieldMap as $from => $to) {
        if (request()->filled($from) && ! array_key_exists($to, $returnQuery)) {
            $returnQuery[$to] = request($from);
        }
    }
@endphp
@if (request('redirect') === 'products.create')
    <a href="{{ route($productCreateRoute, $returnQuery) }}" class="btn btn-admin-outline btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to product
    </a>
@else
    <a href="{{ $fallback }}" class="btn btn-admin-outline btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
@endif
