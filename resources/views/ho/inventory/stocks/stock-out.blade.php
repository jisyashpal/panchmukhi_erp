@extends('layouts.ho')
@section('title', 'Stock Out')
@section('content')
    @php
        $selectedProduct = $products->firstWhere('id', (int) $selectedProductId);
    @endphp
    <x-admin-page-header title="Stock Out" subtitle="Search product, check available qty, and record consumption">
        <a href="{{ route('ho.stocks.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
    </x-admin-page-header>

    <form method="POST" action="{{ route('ho.stocks.stock-out.store') }}" enctype="multipart/form-data" id="stock-out-form">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="admin-card mb-4">
                    <div class="admin-card-header">
                        <h2 class="h6 mb-0">Plant &amp; product</h2>
                    </div>
                    <div class="admin-card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="plant_id">Plant</label>
                                <select name="plant_id" id="plant_id" class="form-select form-select-sm" required>
                                    @foreach($plants as $s)
                                        <option value="{{ $s->id }}" @selected((int) $plantId === $s->id)>{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="category_id">Category</label>
                                <select id="category_id" class="form-select form-select-sm">
                                    <option value="">All categories</option>
                                    @foreach($categories as $c)
                                        <option value="{{ $c->id }}" @selected((int) $selectedCategoryId === $c->id)>
                                            {{ $c->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="filter_sub_category_id">Item Type</label>
                                <select id="filter_sub_category_id" class="form-select form-select-sm">
                                    <option value="">All item types</option>
                                    @foreach($subCategories as $sc)
                                        <option value="{{ $sc->id }}" data-category-id="{{ $sc->category_id }}"
                                            @selected((int) $selectedSubCategoryId === $sc->id)>
                                            {{ $sc->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="product-search">Search item name</label>
                                <input type="text" id="product-search"
                                    class="form-control form-control-sm @error('product_id') is-invalid @enderror"
                                    value="{{ $selectedProduct?->name ?? old('product_search') }}"
                                    placeholder="Type item name or SKU..." autocomplete="off" required>
                                <input type="hidden" name="product_id" id="product_id" value="{{ $selectedProductId }}">
                                @error('product_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div id="selected-product-meta"
                                    class="form-text @unless($selectedProduct) d-none @endunless">
                                    @if($selectedProduct)
                                        SKU: <code>{{ $selectedProduct->sku }}</code>
                                        · Unit: {{ $selectedProduct->unit->abbreviation ?? $selectedProduct->unit->name }}
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Available at plant</label>
                                <div class="form-control form-control-sm bg-light" id="available-qty" readonly>
                                    @if($selectedProduct && $plantId)
                                        —
                                    @else
                                        Select plant and product
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="quantity">Quantity to issue</label>
                                <input type="number" step="0.01" min="0.01" name="quantity" id="quantity"
                                    value="{{ old('quantity') }}"
                                    class="form-control form-control-sm @error('quantity') is-invalid @enderror" required>
                                @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-card mb-4">
                    <div class="admin-card-header">
                        <h2 class="h6 mb-0">Usage details</h2>
                    </div>
                    <div class="admin-card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label d-block">Used for</label>
                                <div class="d-flex flex-wrap gap-3 pt-1">
                                    <div class="form-check">
                                        <input type="radio" name="usage_type" value="vehicle" id="usage_vehicle"
                                            class="form-check-input usage-type-input" @checked(old('usage_type', 'vehicle') === 'vehicle')>
                                        <label class="form-check-label" for="usage_vehicle">Vehicle</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="usage_type" value="other" id="usage_other"
                                            class="form-check-input usage-type-input" @checked(old('usage_type') === 'other')>
                                        <label class="form-check-label" for="usage_other">Other / plant use</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6" id="vehicle-field">
                                <label class="form-label" for="vehicle_id">Vehicle <span class="text-danger">*</span></label>
                                <select name="vehicle_id" id="vehicle_id"
                                    class="form-select form-select-sm @error('vehicle_id') is-invalid @enderror">
                                    <option value="">— Select vehicle —</option>
                                    @foreach($vehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}" data-category-id="{{ $vehicle->category_id }}"
                                            @selected(old('vehicle_id') == $vehicle->id)>
                                            {{ $vehicle->displayLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vehicle_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Vehicles are filtered by selected category.
                                    <a href="{{ route('ho.vehicles.create') }}" target="_blank">Add vehicle</a>
                                </div>
                            </div>
                            <div class="col-md-6 d-none" id="other-purpose-field">
                                <label class="form-label" for="other_purpose">Purpose / location</label>
                                <input type="text" id="other_purpose" value="{{ old('other_purpose') }}"
                                    class="form-control form-control-sm" placeholder="e.g. Maintenance, office, wastage">
                                <div class="form-text">Optional detail — include in notes below if needed.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="reason">Notes / reason <span class="text-danger"
                                        id="reason-required-mark">*</span></label>
                                <textarea name="reason" id="reason" rows="3"
                                    class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                    placeholder="Why is this stock being issued?">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="admin-card mb-4">
                    <div class="admin-card-header">
                        <h2 class="h6 mb-0">Confirmation</h2>
                    </div>
                    <div class="admin-card-body">
                        <div class="mb-3">
                            <label class="form-label small" for="photo_confirmed">Photo confirmation</label>
                            <select name="photo_confirmed" id="photo_confirmed" class="form-select form-select-sm" required>
                                <option value="1" @selected(old('photo_confirmed', '1') == '1')>Yes — photo verified</option>
                                <option value="0" @selected(old('photo_confirmed') === '0')>No</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="photo">Photo upload</label>
                            <input type="file" name="photo" id="photo" accept="image/*"
                                class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="form-label small" for="notes">Additional notes</label>
                            <textarea name="notes" id="notes" class="form-control form-control-sm" rows="2"
                                placeholder="Optional internal note">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-warning w-100">
                    <i class="bi bi-box-arrow-up me-1"></i>Record stock out
                </button>
            </div>
        </div>
    </form>

    @push('scripts')
        @php
            $productOptions = $products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'category_id' => $p->category_id,
                'sub_category_id' => $p->sub_category_id,
                'unit' => $p->unit?->abbreviation ?? $p->unit?->name ?? '',
            ])->values();
        @endphp
        <style>
            #stock-out-product-suggestions {
                position: fixed;
                max-height: 260px;
                overflow-y: auto;
                z-index: 2000;
                display: none;
            }

            #stock-out-product-suggestions.is-open {
                display: block;
            }

            #stock-out-product-suggestions .suggestion-item {
                cursor: pointer;
                transition: background 0.15s ease-in-out;
            }

            #stock-out-product-suggestions .suggestion-item:hover,
            #stock-out-product-suggestions .suggestion-item.active {
                background-color: #f8f9fa;
            }

            #stock-out-product-suggestions .suggestion-item:last-child {
                border-bottom: none !important;
            }
        </style>
        @include('partials.infinite-suggestions')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const products = @json($productOptions);
                const stockCheckUrl = @json(url('/api/plants'));

                const plantSelect = document.getElementById('plant_id');
                const categorySelect = document.getElementById('category_id');
                const itemTypeSelect = document.getElementById('filter_sub_category_id');
                const searchInput = document.getElementById('product-search');
                const productIdInput = document.getElementById('product_id');
                const availableQty = document.getElementById('available-qty');
                const selectedMeta = document.getElementById('selected-product-meta');
                const quantityInput = document.getElementById('quantity');
                const vehicleField = document.getElementById('vehicle-field');
                const otherPurposeField = document.getElementById('other-purpose-field');
                const vehicleSelect = document.getElementById('vehicle_id');
                const reasonInput = document.getElementById('reason');
                const reasonRequiredMark = document.getElementById('reason-required-mark');

                const originalItemTypeOptions = Array.from(itemTypeSelect.options);
                const originalVehicleOptions = Array.from(vehicleSelect.options);

                const suggestionsBox = document.createElement('div');
                suggestionsBox.id = 'stock-out-product-suggestions';
                suggestionsBox.className = 'autocomplete-suggestions shadow border rounded-bottom bg-white';
                document.body.appendChild(suggestionsBox);

                let availableQuantity = null;

                const infinite = window.PanchmukhiInfiniteSuggestions.create(suggestionsBox, {
                    pageSize: 25,
                    renderItem: function (p) {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                        div.innerHTML = `<span class="fw-semibold d-block text-truncate">${p.name}</span><span class="text-muted small">${p.sku || 'No SKU'} · ${p.unit}</span>`;
                        div.addEventListener('mousedown', function (event) {
                            event.preventDefault();
                            selectProduct(p);
                        });
                        return div;
                    },
                });

                function hideSuggestions() {
                    infinite.reset();
                    suggestionsBox.classList.remove('is-open');
                }

                function positionSuggestions() {
                    const rect = searchInput.getBoundingClientRect();
                    suggestionsBox.style.top = (rect.bottom + 2) + 'px';
                    suggestionsBox.style.left = rect.left + 'px';
                    suggestionsBox.style.width = rect.width + 'px';
                }

                function filterSelectByCategory(select, originalOptions, categoryId, keepValue) {
                    const current = keepValue || select.value;
                    select.innerHTML = '';
                    originalOptions.forEach(option => {
                        if (!option.value) {
                            select.appendChild(option.cloneNode(true));
                            return;
                        }
                        if (!categoryId || option.getAttribute('data-category-id') === String(categoryId)) {
                            select.appendChild(option.cloneNode(true));
                        }
                    });
                    if (current && Array.from(select.options).some(o => o.value === String(current))) {
                        select.value = String(current);
                    } else {
                        select.value = '';
                    }
                }

                function filteredProducts() {
                    const categoryId = categorySelect.value;
                    const itemTypeId = itemTypeSelect.value;
                    const term = searchInput.value.trim().toLowerCase();

                    return products.filter(p => {
                        if (categoryId && String(p.category_id) !== String(categoryId)) {
                            return false;
                        }
                        if (itemTypeId && String(p.sub_category_id) !== String(itemTypeId)) {
                            return false;
                        }
                        if (!term) {
                            return true;
                        }
                        return p.name.toLowerCase().includes(term)
                            || (p.sku && p.sku.toLowerCase().includes(term));
                    });
                }

                function renderSuggestions(list) {
                    if (!infinite.setResults(list)) {
                        hideSuggestions();
                        return;
                    }

                    positionSuggestions();
                    suggestionsBox.classList.add('is-open');
                }

                async function fetchAvailableQty() {
                    const plantId = plantSelect.value;
                    const productId = productIdInput.value;

                    if (!plantId || !productId) {
                        availableQty.textContent = 'Select plant and product';
                        availableQuantity = null;
                        return;
                    }

                    availableQty.textContent = 'Loading...';

                    try {
                        const response = await fetch(`${stockCheckUrl}/${plantId}/products/${productId}/stock`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            throw new Error('Failed');
                        }

                        const data = await response.json();
                        availableQuantity = parseFloat(data.quantity);
                        const product = products.find(p => String(p.id) === String(productId));
                        const unit = product?.unit ? ` ${product.unit}` : '';
                        availableQty.textContent = `${availableQuantity}${unit}`;
                        availableQty.classList.toggle('text-danger', availableQuantity <= 0);
                        availableQty.classList.toggle('text-success', availableQuantity > 0);
                    } catch (e) {
                        availableQuantity = null;
                        availableQty.textContent = 'Unable to load stock';
                    }
                }

                function selectProduct(product) {
                    productIdInput.value = product.id;
                    searchInput.value = product.name;
                    selectedMeta.innerHTML = `SKU: <code>${product.sku || '—'}</code> · Unit: ${product.unit || '—'}`;
                    selectedMeta.classList.remove('d-none');
                    hideSuggestions();

                    if (product.category_id) {
                        categorySelect.value = String(product.category_id);
                        filterSelectByCategory(itemTypeSelect, originalItemTypeOptions, product.category_id, product.sub_category_id);
                        filterSelectByCategory(vehicleSelect, originalVehicleOptions, product.category_id, vehicleSelect.value);
                    }
                    if (product.sub_category_id) {
                        itemTypeSelect.value = String(product.sub_category_id);
                    }

                    fetchAvailableQty();
                }

                function clearProductSelection() {
                    productIdInput.value = '';
                    searchInput.value = '';
                    selectedMeta.classList.add('d-none');
                    availableQty.textContent = 'Select plant and product';
                    availableQuantity = null;
                }

                function syncUsageFields() {
                    const isVehicle = document.getElementById('usage_vehicle').checked;
                    vehicleField.classList.toggle('d-none', !isVehicle);
                    otherPurposeField.classList.toggle('d-none', isVehicle);
                    vehicleSelect.required = isVehicle;
                    reasonInput.required = !isVehicle;
                    reasonRequiredMark.classList.toggle('d-none', isVehicle);
                }

                function syncCategoryFilters() {
                    const categoryId = categorySelect.value;
                    filterSelectByCategory(itemTypeSelect, originalItemTypeOptions, categoryId);
                    filterSelectByCategory(vehicleSelect, originalVehicleOptions, categoryId);
                }

                searchInput.addEventListener('input', function () {
                    if (!productIdInput.value || searchInput.value !== products.find(p => String(p.id) === productIdInput.value)?.name) {
                        productIdInput.value = '';
                        selectedMeta.classList.add('d-none');
                        availableQty.textContent = 'Select plant and product';
                        availableQuantity = null;
                    }
                    renderSuggestions(filteredProducts());
                });

                searchInput.addEventListener('focus', function () {
                    renderSuggestions(filteredProducts());
                });

                categorySelect.addEventListener('change', function () {
                    clearProductSelection();
                    syncCategoryFilters();
                    renderSuggestions(filteredProducts());
                });

                itemTypeSelect.addEventListener('change', function () {
                    clearProductSelection();
                    renderSuggestions(filteredProducts());
                });

                plantSelect.addEventListener('change', function () {
                    const url = new URL(window.location.href);
                    url.searchParams.set('plant_id', plantSelect.value);
                    if (categorySelect.value) {
                        url.searchParams.set('category_id', categorySelect.value);
                    } else {
                        url.searchParams.delete('category_id');
                    }
                    if (itemTypeSelect.value) {
                        url.searchParams.set('sub_category_id', itemTypeSelect.value);
                    } else {
                        url.searchParams.delete('sub_category_id');
                    }
                    url.searchParams.delete('product_id');
                    window.location.href = url.toString();
                });

                document.getElementById('stock-out-form').addEventListener('submit', function (e) {
                    if (!productIdInput.value) {
                        e.preventDefault();
                        searchInput.classList.add('is-invalid');
                        return;
                    }

                    if (availableQuantity !== null && parseFloat(quantityInput.value) > availableQuantity) {
                        e.preventDefault();
                        quantityInput.classList.add('is-invalid');
                        alert(`Quantity cannot exceed available stock (${availableQuantity}).`);
                        return;
                    }

                    if (!document.getElementById('usage_vehicle').checked) {
                        const purpose = document.getElementById('other_purpose').value.trim();
                        if (purpose && !reasonInput.value.includes(purpose)) {
                            reasonInput.value = purpose + (reasonInput.value ? '\n' + reasonInput.value : '');
                        }
                    }
                });

                document.querySelectorAll('.usage-type-input').forEach(el => el.addEventListener('change', syncUsageFields));

                document.addEventListener('click', function (e) {
                    if (e.target === searchInput || suggestionsBox.contains(e.target)) {
                        return;
                    }
                    hideSuggestions();
                });

                window.addEventListener('scroll', function (e) {
                    if (!suggestionsBox.classList.contains('is-open')) {
                        return;
                    }
                    // Keep open while scrolling inside the suggestion list itself.
                    if (suggestionsBox.contains(e.target)) {
                        return;
                    }
                    positionSuggestions();
                }, true);

                window.addEventListener('resize', function () {
                    if (suggestionsBox.classList.contains('is-open')) {
                        positionSuggestions();
                    }
                });

                syncCategoryFilters();
                const initialItemTypeId = @json($selectedSubCategoryId ? (string) $selectedSubCategoryId : '');
                if (initialItemTypeId) {
                    itemTypeSelect.value = initialItemTypeId;
                }
                syncUsageFields();

                if (productIdInput.value) {
                    fetchAvailableQty();
                }
            });
        </script>
    @endpush
@endsection
