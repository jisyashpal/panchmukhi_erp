<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('products-container');
    const addRowBtn = document.getElementById('add-row-btn');
    const templateEl = document.getElementById('product-row-template');
    if (!container || !templateEl) {
        return;
    }

    const template = templateEl.innerHTML;
    let rowIndex = 0;

    function getOrStoreOriginalOptions(select) {
        if (!select.originalOptions) {
            select.originalOptions = Array.from(select.options);
        }
        return select.originalOptions;
    }

    function filterSubCategories(row, preserveSelected) {
        const categorySelect = row.querySelector('.product-category');
        const subCategorySelect = row.querySelector('.product-subcategory');
        if (!categorySelect || !subCategorySelect) return;

        const selectedCategoryId = categorySelect.value;
        const previousSubCategoryId = preserveSelected ? subCategorySelect.value : '';
        const originalOptions = getOrStoreOriginalOptions(subCategorySelect);

        subCategorySelect.innerHTML = '';

        if (!selectedCategoryId) {
            subCategorySelect.disabled = true;
            const defaultOpt = originalOptions[0].cloneNode(true);
            defaultOpt.text = 'Select Category First';
            subCategorySelect.appendChild(defaultOpt);
            subCategorySelect.value = '';
            generateRowSku(row);
            return;
        }

        subCategorySelect.disabled = false;
        subCategorySelect.appendChild(originalOptions[0].cloneNode(true));

        originalOptions.slice(1).forEach(option => {
            if (option.getAttribute('data-category-id') === selectedCategoryId) {
                subCategorySelect.appendChild(option.cloneNode(true));
            }
        });

        if (previousSubCategoryId && Array.from(subCategorySelect.options).some(option => option.value === previousSubCategoryId)) {
            subCategorySelect.value = previousSubCategoryId;
        } else {
            subCategorySelect.value = '';
        }
        generateRowSku(row);
    }

    function generateRowSku(row) {
        const nameInput = row.querySelector('.product-name');
        const skuCodeInput = row.querySelector('.product-sku-code');
        const sizeInput = row.querySelector('.product-size');
        const categorySelect = row.querySelector('.product-category');
        const subCategorySelect = row.querySelector('.product-subcategory');
        const brandSelect = row.querySelector('.product-brand');
        const skuInput = row.querySelector('.product-sku');

        if (!nameInput || !sizeInput || !categorySelect || !subCategorySelect || !skuInput) return;

        const categoryOption = categorySelect.options[categorySelect.selectedIndex];
        const categoryName = (categoryOption && categoryOption.value) ? categoryOption.text : '';
        const subCategoryOption = subCategorySelect.options[subCategorySelect.selectedIndex];
        const subCategoryName = (subCategoryOption && subCategoryOption.value) ? subCategoryOption.text : '';
        const brandOption = brandSelect ? brandSelect.options[brandSelect.selectedIndex] : null;
        const brandName = (brandOption && brandOption.value) ? brandOption.text : '';

        const cleanCategory = categoryName.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanSubCategory = subCategoryName.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanSize = sizeInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanBrand = brandName.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        const cleanSkuCode = skuCodeInput ? skuCodeInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase() : '';
        const cleanName = nameInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();

        const catPart = cleanCategory ? cleanCategory.substring(0, 3) : '';
        const subPart = cleanSubCategory ? cleanSubCategory.substring(0, 3) : '';
        const sizePart = cleanSize || '';
        const brandPart = cleanBrand ? cleanBrand.substring(0, 3) : '';
        const skuCodePart = cleanSkuCode || '';
        const namePart = cleanName ? cleanName.substring(0, 3) : '';

        let sku = catPart;
        if (subPart) {
            sku += '_' + subPart;
        }
        if (brandPart) {
            sku += '_' + brandPart;
        }
        if (skuCodePart) {
            sku += '_' + skuCodePart;
        }
        if (namePart) {
            sku += '_' + namePart;
        }
        if (sizePart) {
            sku += '_' + sizePart;
        }

        skuInput.value = sku;
    }

    function catalogReturnParams(row) {
        const params = new URLSearchParams();
        params.set('redirect', 'products.create');

        const productRequestId = document.querySelector('input[name="product_request_id"]')?.value;
        const productRequestItemId = document.querySelector('input[name="product_request_item_id"]')?.value;
        if (productRequestId) params.set('product_request_id', productRequestId);
        if (productRequestItemId) params.set('product_request_item_id', productRequestItemId);

        const values = {
            pc_name: row.querySelector('.product-name')?.value,
            pc_size: row.querySelector('.product-size')?.value,
            pc_sku_code: row.querySelector('.product-sku-code')?.value,
            pc_category_id: row.querySelector('.product-category')?.value,
            pc_sub_category_id: row.querySelector('.product-subcategory')?.value,
            pc_brand_id: row.querySelector('.product-brand')?.value,
            pc_unit_id: row.querySelector('.product-unit')?.value,
            pc_reorder_level: row.querySelector('.product-reorder')?.value,
        };

        Object.entries(values).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });

        if (values.pc_category_id) {
            params.set('category_id', values.pc_category_id);
        }

        return params;
    }

    function syncCatalogCreateHref(link, row) {
        const url = new URL(link.getAttribute('href'), window.location.origin);
        const params = catalogReturnParams(row);
        params.forEach((value, key) => url.searchParams.set(key, value));
        link.href = url.pathname + '?' + params.toString();
    }

    function bindCatalogCreateLinks(row) {
        row.querySelectorAll('.js-create-catalog').forEach((link) => {
            const sync = () => syncCatalogCreateHref(link, row);
            link.addEventListener('mouseenter', sync);
            link.addEventListener('focus', sync);
            link.addEventListener('click', sync);
        });
    }

    function initializeRow(row) {
        const categorySelect = row.querySelector('.product-category');
        const subCategorySelect = row.querySelector('.product-subcategory');
        const brandSelect = row.querySelector('.product-brand');
        const nameInput = row.querySelector('.product-name');
        const skuCodeInput = row.querySelector('.product-sku-code');
        const sizeInput = row.querySelector('.product-size');

        if (categorySelect) {
            categorySelect.addEventListener('change', function () {
                filterSubCategories(row, false);
                generateRowSku(row);
            });
            filterSubCategories(row, true);
        }

        if (subCategorySelect) {
            subCategorySelect.addEventListener('change', function () {
                generateRowSku(row);
            });
        }

        if (brandSelect) {
            brandSelect.addEventListener('change', function () {
                generateRowSku(row);
            });
        }

        if (skuCodeInput) {
            skuCodeInput.addEventListener('input', function () {
                generateRowSku(row);
            });
        }

        if (nameInput) {
            nameInput.addEventListener('input', function () {
                generateRowSku(row);
            });
        }

        if (sizeInput) {
            sizeInput.addEventListener('input', function () {
                generateRowSku(row);
            });
        }

        const removeBtn = row.querySelector('.remove-row-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                const rows = container.querySelectorAll('.product-row-group');
                if (rows.length > 1) {
                    row.remove();
                } else {
                    alert('At least one product entry is required.');
                }
            });
        }

        bindCatalogCreateLinks(row);
    }

    function addRow() {
        const html = template.replace(/__INDEX__/g, rowIndex);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const div = wrapper.firstElementChild;
        if (!div) {
            return null;
        }
        div.setAttribute('data-index', rowIndex);
        container.appendChild(div);
        initializeRow(div);
        rowIndex++;
        return div;
    }

    if (addRowBtn) {
        addRowBtn.addEventListener('click', addRow);
    }

    addRow();

    const prefill = {
        name: @json(request('name')),
        size: @json(request('size')),
        sku_code: @json(request('sku_code')),
        category_id: @json(request('category_id')),
        sub_category_id: @json(request('sub_category_id')),
        brand_id: @json(request('brand_id')),
        unit_id: @json(request('unit_id')),
        reorder_level: @json(request('reorder_level')),
    };
    const firstRow = container.querySelector('.product-row-group');
    if (firstRow) {
        const nameInput = firstRow.querySelector('.product-name');
        const sizeInput = firstRow.querySelector('.product-size');
        const skuCodeInput = firstRow.querySelector('.product-sku-code');
        const categorySelect = firstRow.querySelector('.product-category');
        const subCategorySelect = firstRow.querySelector('.product-subcategory');
        const brandSelect = firstRow.querySelector('.product-brand');
        const unitSelect = firstRow.querySelector('.product-unit');
        const reorderInput = firstRow.querySelector('.product-reorder');

        if (prefill.name && nameInput) {
            nameInput.value = prefill.name;
        }
        if (prefill.size && sizeInput) {
            sizeInput.value = prefill.size;
        }
        if (prefill.sku_code && skuCodeInput) {
            skuCodeInput.value = prefill.sku_code;
        }
        if (prefill.category_id && categorySelect) {
            categorySelect.value = String(prefill.category_id);
            filterSubCategories(firstRow, true);
        }
        if (prefill.sub_category_id && subCategorySelect) {
            subCategorySelect.value = String(prefill.sub_category_id);
        }
        if (prefill.brand_id && brandSelect) {
            brandSelect.value = String(prefill.brand_id);
        }
        if (prefill.unit_id && unitSelect) {
            unitSelect.value = String(prefill.unit_id);
        }
        if (prefill.reorder_level && reorderInput) {
            reorderInput.value = prefill.reorder_level;
        }
        generateRowSku(firstRow);
    }
});
</script>
