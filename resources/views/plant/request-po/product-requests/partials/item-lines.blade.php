@props(['products', 'subCategories', 'categories', 'containerId' => 'item-lines'])

<h2 class="h6 mt-4 mb-3">Product Items</h2>

<div id="{{ $containerId }}">
    <div class="dynamic-row row g-3 mb-2 align-items-end">
        @include('plant.request-po.product-requests.partials.item-line-row', [
            'products' => $products,
            'subCategories' => $subCategories,
            'categories' => $categories,
            'index' => 0,
        ])
    </div>
</div>

<template id="plant-item-line-template">
    @include('plant.request-po.product-requests.partials.item-line-row', [
        'products' => $products,
        'subCategories' => $subCategories,
        'categories' => $categories,
        'index' => '__INDEX__',
    ])
</template>

@php
    $productCatalog = $products->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '',
            'size' => $p->size,
            'unit' => $p->unit?->abbreviation ?? $p->unit?->name ?? '',
            'sub_category_id' => $p->sub_category_id,
            'category_id' => $p->category_id,
        ];
    })->values();
@endphp

@push('scripts')
    <style>
    #plant-item-autocomplete-portal {
        position: fixed;
        max-height: 250px;
        overflow-y: auto;
        z-index: 2000;
        display: none;
    }
    #plant-item-autocomplete-portal.is-open {
        display: block;
    }
    #plant-item-autocomplete-portal .suggestion-item {
        cursor: pointer;
        transition: background 0.15s ease-in-out;
    }
    #plant-item-autocomplete-portal .suggestion-item:hover {
        background-color: #f8f9fa;
    }
    #plant-item-autocomplete-portal .suggestion-item:last-child {
        border-bottom: none !important;
    }
    </style>
    @include('partials.infinite-suggestions')
    <script type="application/json" id="plant-request-products-data">@json($productCatalog)</script>
    <script>
    (function () {
        const productsDataEl = document.getElementById('plant-request-products-data');
        if (!productsDataEl) {
            return;
        }

        const products = JSON.parse(productsDataEl.textContent);
        let activeInput = null;
        let activeRow = null;

        const portal = document.createElement('div');
        portal.id = 'plant-item-autocomplete-portal';
        portal.className = 'autocomplete-suggestions shadow border rounded-bottom bg-white';
        document.body.appendChild(portal);

        const infinite = window.PanchmukhiInfiniteSuggestions.create(portal, {
            pageSize: 25,
            renderItem: function (product) {
                const div = document.createElement('div');
                div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                div.innerHTML = '<span class="fw-semibold text-dark d-block text-truncate" style="max-width:100%">'
                    + product.name
                    + '</span> <span class="text-muted small">'
                    + (product.sku || 'No SKU')
                    + '</span>';
                div.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    if (activeRow) {
                        applyProductSelection(activeRow, product);
                    }
                });
                return div;
            },
            renderTail: function () {
                @if(auth()->user()->canManagePlantProducts())
                if (!activeInput || !activeRow) {
                    return null;
                }
                const subCatSelect = activeRow.querySelector('.item-subcategory-select');
                const subCatId = subCatSelect ? subCatSelect.value : '';
                const catSelect = activeRow.querySelector('.item-category-select');
                const addProduct = document.createElement('a');
                addProduct.href = @json(route('plant.products.create'));
                const params = new URLSearchParams();
                if (activeInput.value.trim()) {
                    params.set('name', activeInput.value.trim());
                }
                if (subCatId) {
                    params.set('sub_category_id', subCatId);
                }
                if (catSelect && catSelect.value) {
                    params.set('category_id', catSelect.value);
                }
                const sizeInput = activeRow.querySelector('.item-size-input');
                if (sizeInput && sizeInput.value.trim()) {
                    params.set('size', sizeInput.value.trim());
                }
                const qs = params.toString();
                if (qs) {
                    addProduct.href += '?' + qs;
                }
                addProduct.target = '_blank';
                addProduct.className = 'suggestion-item px-3 py-2 border-top text-primary text-decoration-none d-flex align-items-center gap-1';
                addProduct.innerHTML = '<i class="bi bi-plus-circle"></i><span class="fw-semibold">Add Product</span>';
                addProduct.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    window.open(addProduct.href, '_blank');
                    hideSuggestions();
                });
                return addProduct;
                @else
                return null;
                @endif
            },
        });

        function hideSuggestions() {
            infinite.reset();
            portal.classList.remove('is-open');
            activeInput = null;
            activeRow = null;
        }

        function positionPortal(input) {
            const rect = input.getBoundingClientRect();
            portal.style.top = (rect.bottom + 2) + 'px';
            portal.style.left = rect.left + 'px';
            portal.style.width = rect.width + 'px';
        }

        function applyProductSelection(row, product) {
            const input = row.querySelector('.item-name-input');
            const productIdInput = row.querySelector('.item-product-id');
            const sizeInput = row.querySelector('.item-size-input');
            const catSelect = row.querySelector('.item-category-select');
            const subCatSelect = row.querySelector('.item-subcategory-select');
            const unitInput = row.querySelector('.item-unit-input');

            input.value = product.name;
            productIdInput.value = product.id;

            if (product.unit && unitInput && !unitInput.value.trim()) {
                unitInput.value = product.unit;
            }

            if (product.size) {
                sizeInput.value = product.size;
            }

            if (product.category_id && catSelect) {
                catSelect.value = String(product.category_id);
                if (subCatSelect) {
                    subCatSelect.disabled = false;
                    filterDropdown(subCatSelect, 'data-category-id', String(product.category_id));
                }
            }
            if (product.sub_category_id && subCatSelect) {
                subCatSelect.value = String(product.sub_category_id);
            }

            hideSuggestions();
        }

        function showSuggestions(input) {
            const row = input.closest('.dynamic-row');
            if (!row) {
                return;
            }

            const val = input.value.trim().toLowerCase();

            activeInput = input;
            activeRow = row;

            const subCatSelect = row.querySelector('.item-subcategory-select');
            const subCatId = subCatSelect ? subCatSelect.value : '';

            const filtered = products.filter(function (product) {
                if (subCatId && String(product.sub_category_id) !== String(subCatId)) {
                    return false;
                }
                if (!val) {
                    return true;
                }
                return product.name.toLowerCase().includes(val)
                    || (product.sku && product.sku.toLowerCase().includes(val));
            });

            if (filtered.length === 0) {
                infinite.setResults([]);
                const empty = document.createElement('div');
                empty.className = 'px-3 py-2 text-muted small';
                empty.textContent = 'No matching product in catalog';
                portal.insertBefore(empty, portal.firstChild);
            } else {
                infinite.setResults(filtered);
            }

            if (portal.children.length === 0) {
                hideSuggestions();
                return;
            }

            positionPortal(input);
            portal.classList.add('is-open');
        }

        document.addEventListener('input', function (event) {
            if (event.target.matches('.item-name-input')) {
                const row = event.target.closest('.dynamic-row');
                const productIdInput = row.querySelector('.item-product-id');
                if (productIdInput) {
                    productIdInput.value = '';
                }
                showSuggestions(event.target);
            }
        });

        document.addEventListener('focusin', function (event) {
            if (event.target.matches('.item-name-input')) {
                showSuggestions(event.target);
            }
        });

        document.addEventListener('click', function (event) {
            if (event.target.matches('.item-name-input')) {
                showSuggestions(event.target);
                return;
            }
            if (activeInput && (activeInput.contains(event.target) || portal.contains(event.target))) {
                return;
            }
            hideSuggestions();
        });

        window.addEventListener('scroll', function (event) {
            if (!activeInput || !portal.classList.contains('is-open')) {
                return;
            }
            if (portal.contains(event.target)) {
                return;
            }
            positionPortal(activeInput);
        }, true);

        window.addEventListener('resize', function () {
            if (activeInput && portal.classList.contains('is-open')) {
                positionPortal(activeInput);
            }
        });

        // Cascading Dropdown functionality
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

            if (catSelect && subCatSelect) {
                const currentSubCatValue = subCatSelect.value;

                if (!catSelect.value) {
                    subCatSelect.disabled = true;
                } else {
                    subCatSelect.disabled = false;
                    filterDropdown(subCatSelect, 'data-category-id', catSelect.value);
                    subCatSelect.value = currentSubCatValue;
                }
            }
        }

        document.addEventListener('change', function (event) {
            const target = event.target;
            if (target.matches('.item-category-select')) {
                const row = target.closest('.dynamic-row');
                const subCatSelect = row.querySelector('.item-subcategory-select');

                const catId = target.value;
                if (catId) {
                    filterDropdown(subCatSelect, 'data-category-id', catId);
                    subCatSelect.disabled = false;
                } else {
                    resetDropdown(subCatSelect);
                    subCatSelect.disabled = true;
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
