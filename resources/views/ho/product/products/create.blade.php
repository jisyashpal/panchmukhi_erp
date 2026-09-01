@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Add Products')
@section('content')
    <x-admin-page-header title="Add Products" subtitle="Add new products to the catalog">
        <div class="d-flex gap-2">
            <a href="{{ route('ho.products.import-form') }}" class="btn btn-admin-primary btn-sm">
                <i class="bi bi-upload me-1"></i>Import Products
            </a>
            <a href="{{ route('ho.products.index') }}" class="btn btn-admin-outline btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>
    </x-admin-page-header>

    <div class="admin-card">
        <div class="admin-card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('ho.products.store') }}">
                @csrf
                <div id="products-container" class="mb-3">
                    <!-- Rows injected via javascript -->
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button type="button" class="btn btn-primary btn-sm" id="add-row-btn">
                        <i class="bi bi-plus-lg me-1"></i>Add Product
                    </button>
                    <button type="submit" class="btn btn-admin-primary">Save Products</button>
                </div>
            </form>
        </div>
    </div>

    <template id="product-row-template">
        <div class="product-row-group border rounded p-3 mb-3 bg-light position-relative">
            <button type="button" class="btn btn-outline-danger btn-sm position-absolute top-0 end-0 m-2 remove-row-btn"
                title="Remove Product">
                <i class="bi bi-trash"></i>
            </button>

            <!-- Row 1: SKU Code, Name, Size, SKU, Active -->
            <div class="row g-3 mb-3 align-items-end pe-5">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">SKU Code</label>
                    <input type="text" name="products[__INDEX__][sku_code]" class="form-control form-control-sm product-sku-code"
                        maxlength="20" placeholder="SKU only">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary">Item Name <span
                            class="text-danger">*</span></label>
                    <input type="text" name="products[__INDEX__][name]" class="form-control form-control-sm product-name"
                        required placeholder="Item Name">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Size/Part</label>
                    <input type="text" name="products[__INDEX__][size]" class="form-control form-control-sm product-size"
                        placeholder="Size/Part">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary">SKU Preview</label>
                    <input type="text" class="form-control form-control-sm product-sku" readonly placeholder="Auto SKU"
                        disabled>
                </div>
                <div class="col-md-2 d-flex align-items-center" style="height: 38px;">
                    <div class="form-check">
                        <input type="hidden" name="products[__INDEX__][is_active]" value="0">
                        <input type="checkbox" name="products[__INDEX__][is_active]" value="1" checked
                            class="form-check-input" id="active___INDEX__">
                        <label class="form-check-label small fw-semibold text-secondary"
                            for="active___INDEX__">Active</label>
                    </div>
                </div>
            </div>

            <!-- Row 2: Category, Item Type, Brand, Unit, Reorder Level -->
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0">Category <span
                                class="text-danger">*</span></label>
                        <a href="{{ route('ho.categories.create', ['redirect' => 'products.create']) }}"
                            class="text-primary small" style="text-decoration: none;">
                            <i class="bi bi-plus-circle me-1"></i>Create
                        </a>
                    </div>
                    <select name="products[__INDEX__][category_id]" class="form-select form-select-sm product-category"
                        required>
                        <option value="">Select category</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0">Item Type</label>
                        <a href="{{ route('ho.sub-categories.create', ['redirect' => 'products.create']) }}"
                            class="text-primary small" style="text-decoration: none;">
                            <i class="bi bi-plus-circle me-1"></i>Create
                        </a>
                    </div>
                    <select name="products[__INDEX__][sub_category_id]"
                        class="form-select form-select-sm product-subcategory">
                        <option value="">Select Item Type</option>
                        @foreach ($subCategories as $sc)
                            <option value="{{ $sc->id }}" data-category-id="{{ $sc->category_id }}">{{ $sc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0">Brand</label>
                        <a href="{{ route('ho.brands.create', ['redirect' => 'products.create']) }}"
                            class="text-primary small" style="text-decoration: none;">
                            <i class="bi bi-plus-circle me-1"></i>Create
                        </a>
                    </div>
                    <select name="products[__INDEX__][brand_id]" class="form-select form-select-sm product-brand">
                        <option value="">—</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0">Unit <span
                                class="text-danger">*</span></label>
                        <a href="{{ route('ho.units.create', ['redirect' => 'products.create']) }}"
                            class="text-primary small" style="text-decoration: none;">
                            <i class="bi bi-plus-circle me-1"></i>Create
                        </a>
                    </div>
                    <select name="products[__INDEX__][unit_id]" class="form-select form-select-sm product-unit" required>
                        @foreach ($units as $u)
                            <option value="{{ $u->id }}" @selected(strtolower((string) $u->abbreviation) === 'nos')>{{ $u->name }} ({{ $u->abbreviation }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Reorder Level <span
                            class="text-danger">*</span></label>
                    <input type="number" name="products[__INDEX__][reorder_level]"
                        class="form-control form-control-sm product-reorder" value="10" min="0" required>
                </div>
            </div>
        </div>
    </template>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const container = document.getElementById('products-container');
                const addRowBtn = document.getElementById('add-row-btn');
                const template = document.getElementById('product-row-template').innerHTML;
                let rowIndex = 0;

                function getOrStoreOriginalOptions(select) {
                    if (!select.originalOptions) {
                        select.originalOptions = Array.from(select.options);
                    }
                    return select.originalOptions;
                }

                function filterSubCategories(row) {
                    const categorySelect = row.querySelector('.product-category');
                    const subCategorySelect = row.querySelector('.product-subcategory');
                    if (!categorySelect || !subCategorySelect) return;

                    const selectedCategoryId = categorySelect.value;
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
                    subCategorySelect.appendChild(originalOptions[0]); // placeholder

                    originalOptions.slice(1).forEach(option => {
                        if (option.getAttribute('data-category-id') === selectedCategoryId) {
                            subCategorySelect.appendChild(option);
                        }
                    });
                    subCategorySelect.value = '';
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

                function initializeRow(row) {
                    const categorySelect = row.querySelector('.product-category');
                    const subCategorySelect = row.querySelector('.product-subcategory');
                    const brandSelect = row.querySelector('.product-brand');
                    const nameInput = row.querySelector('.product-name');
                    const skuCodeInput = row.querySelector('.product-sku-code');
                    const sizeInput = row.querySelector('.product-size');

                    if (categorySelect) {
                        categorySelect.addEventListener('change', function () {
                            filterSubCategories(row);
                            generateRowSku(row);
                        });
                        filterSubCategories(row);
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
                }

                function addRow() {
                    const html = template.replace(/__INDEX__/g, rowIndex);
                    const div = document.createElement('div');
                    div.className = 'product-row-group border rounded p-3 mb-3 bg-light position-relative';
                    div.setAttribute('data-index', rowIndex);
                    div.innerHTML = html;
                    container.appendChild(div);
                    initializeRow(div);
                    rowIndex++;
                }

                if (addRowBtn) {
                    addRowBtn.addEventListener('click', addRow);
                }

                // Add first row on load
                addRow();
            });
        </script>
    @endpush
@endsection