@props(['products', 'containerId' => 'item-lines'])

<h2 class="h6 mt-4 mb-3">Items</h2>
<div id="{{ $containerId }}">
    <div class="dynamic-row row g-3 mb-2 align-items-end">
        @include('partials.request-item-line-row', ['products' => $products, 'index' => 0])
    </div>
</div>
<button type="button" class="btn btn-sm btn-admin-outline mb-3"
        onclick="addDynamicRow('{{ $containerId }}', document.getElementById('item-line-template').innerHTML)">
    + Add line
</button>
<template id="item-line-template">
    @include('partials.request-item-line-row', ['products' => $products, 'index' => '__INDEX__'])
</template>
