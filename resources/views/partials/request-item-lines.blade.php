@props(['products', 'subCategories', 'categories', 'containerId' => 'item-lines'])

<h2 class="h6 mt-4 mb-3">Items</h2>
<div id="{{ $containerId }}">
    <div class="dynamic-row row g-3 mb-2 align-items-end">
        @include('partials.request-item-line-row', [
            'products' => $products,
            'subCategories' => $subCategories,
            'categories' => $categories,
            'index' => 0
        ])
    </div>
</div>
<button type="button" class="btn btn-sm btn-admin-outline mb-3"
        onclick="addDynamicRow('{{ $containerId }}', document.getElementById('item-line-template').innerHTML)">
    + Add line
</button>
<template id="item-line-template">
    @include('partials.request-item-line-row', [
        'products' => $products,
        'subCategories' => $subCategories,
        'categories' => $categories,
        'index' => '__INDEX__'
    ])
</template>

@push('scripts')
<script>
(function () {
    function getOrStoreOriginalOptions(select) {
        if (!select.originalOptions) {
            select.originalOptions = Array.from(select.options);
        }
        return select.originalOptions;
    }

    function filterDropdown(select, attribute, matchValue) {
        const original = getOrStoreOriginalOptions(select);
        select.innerHTML = '';
        
        if (original.length > 0) {
            select.appendChild(original[0]);
        }
        
        original.slice(1).forEach(option => {
            if (!matchValue || option.getAttribute(attribute) === matchValue) {
                select.appendChild(option);
            }
        });
        select.value = '';
    }

    function resetDropdown(select) {
        const original = getOrStoreOriginalOptions(select);
        select.innerHTML = '';
        if (original.length > 0) {
            select.appendChild(original[0]);
        }
        select.value = '';
    }

    function initializeRowFilters(row) {
        const catSelect = row.querySelector('.item-category-select');
        const subCatSelect = row.querySelector('.item-subcategory-select');
        const prodSelect = row.querySelector('.item-product-select');
        
        if (catSelect && subCatSelect && prodSelect) {
            const currentSubCatValue = subCatSelect.value;
            const currentProdValue = prodSelect.value;

            if (!catSelect.value) {
                subCatSelect.disabled = true;
                prodSelect.disabled = true;
            } else {
                subCatSelect.disabled = false;
                filterDropdown(subCatSelect, 'data-category-id', catSelect.value);
                subCatSelect.value = currentSubCatValue;
                
                if (!subCatSelect.value) {
                    prodSelect.disabled = true;
                } else {
                    prodSelect.disabled = false;
                    filterDropdown(prodSelect, 'data-subcategory-id', subCatSelect.value);
                    prodSelect.value = currentProdValue;
                }
            }
        }
    }

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (target.matches('.item-category-select')) {
            const row = target.closest('.dynamic-row');
            const subCatSelect = row.querySelector('.item-subcategory-select');
            const prodSelect = row.querySelector('.item-product-select');
            
            const catId = target.value;
            if (catId) {
                filterDropdown(subCatSelect, 'data-category-id', catId);
                subCatSelect.disabled = false;
            } else {
                resetDropdown(subCatSelect);
                subCatSelect.disabled = true;
            }
            
            resetDropdown(prodSelect);
            prodSelect.disabled = true;
            
        } else if (target.matches('.item-subcategory-select')) {
            const row = target.closest('.dynamic-row');
            const prodSelect = row.querySelector('.item-product-select');
            
            const subCatId = target.value;
            if (subCatId) {
                filterDropdown(prodSelect, 'data-subcategory-id', subCatId);
                prodSelect.disabled = false;
            } else {
                resetDropdown(prodSelect);
                prodSelect.disabled = true;
            }
            
        }
    });

    // Run filters on page load for any pre-populated items
    document.querySelectorAll('.dynamic-row').forEach(row => {
        initializeRowFilters(row);
    });

    // Mutation observer to initialize newly added dynamic rows
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) {
                    if (node.matches('.dynamic-row')) {
                        initializeRowFilters(node);
                    }
                    node.querySelectorAll('.dynamic-row').forEach(row => {
                        initializeRowFilters(row);
                    });
                }
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
</script>
@endpush
