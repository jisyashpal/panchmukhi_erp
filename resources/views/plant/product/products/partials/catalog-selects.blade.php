@php
    $product = $product ?? null;
    $portal = $portal ?? 'plant';
    $selectedCategoryId = old('category_id', $product?->category_id ?? request('category_id'));
    $selectedSubCategoryId = old('sub_category_id', $product?->sub_category_id ?? request('sub_category_id'));
    $selectedBrandId = old('brand_id', $product?->brand_id ?? request('brand_id'));
    $selectedUnitId = old('unit_id', $product?->unit_id ?? request('unit_id'));
    $returnParams = ['redirect' => 'products.create'];
    $subCategoryCreateParams = array_filter(array_merge($returnParams, [
        'category_id' => $selectedCategoryId,
    ]));
@endphp

<x-admin-form-group label="Category" name="category_id" required
    :create-href="route($portal.'.categories.create', $returnParams)" create-label="Create category">
    <select name="category_id" id="category_id" class="form-select" required>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected($selectedCategoryId == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
</x-admin-form-group>

<x-admin-form-group label="Item Type" name="sub_category_id"
    :create-href="route($portal.'.sub-categories.create', $subCategoryCreateParams)" create-label="Create Item Type">
    <select name="sub_category_id" id="sub_category_id" class="form-select">
        <option value="">—</option>
        @foreach ($subCategories as $sc)
            <option value="{{ $sc->id }}" data-category-id="{{ $sc->category_id }}"
                @selected($selectedSubCategoryId == $sc->id)>{{ $sc->name }}</option>
        @endforeach
    </select>
</x-admin-form-group>

<x-admin-form-group label="Brand" name="brand_id" :create-href="route($portal.'.brands.create', $returnParams)"
    create-label="Create brand">
    <select name="brand_id" id="brand_id" class="form-select">
        <option value="">—</option>
        @foreach ($brands as $b)
            <option value="{{ $b->id }}" @selected($selectedBrandId == $b->id)>{{ $b->name }}</option>
        @endforeach
    </select>
</x-admin-form-group>

<x-admin-form-group label="Unit" name="unit_id" required :create-href="route($portal.'.units.create', $returnParams)"
    create-label="Create unit">
    <select name="unit_id" id="unit_id" class="form-select" required>
        @foreach ($units as $u)
            <option value="{{ $u->id }}" @selected($selectedUnitId == $u->id)>{{ $u->name }} ({{ $u->abbreviation }})</option>
        @endforeach
    </select>
</x-admin-form-group>