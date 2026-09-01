@if (request('redirect') === 'products.create')
    <input type="hidden" name="redirect" value="products.create">
    @foreach ([
        'product_request_id',
        'product_request_item_id',
        'pc_name',
        'pc_size',
        'pc_sku_code',
        'pc_category_id',
        'pc_sub_category_id',
        'pc_brand_id',
        'pc_unit_id',
        'pc_reorder_level',
    ] as $key)
        @if (request()->filled($key))
            <input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
        @endif
    @endforeach
@endif
