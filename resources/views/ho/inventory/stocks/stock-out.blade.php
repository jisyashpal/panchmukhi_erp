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
                            <div class="col-md-6">
                                <label class="form-label" for="plant_id">Plant</label>
                                <select name="plant_id" id="plant_id" class="form-select form-select-sm" required>
                                    @foreach($plants as $s)
                                        <option value="{{ $s->id }}" @selected((int) $plantId === $s->id)>{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
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
                            <div class="col-12 position-relative">
                                <label class="form-label" for="product-search">Search item name</label>
                                <input type="text" id="product-search"
                                    class="form-control form-control-sm @error('product_id') is-invalid @enderror"
                                    value="{{ $selectedProduct?->name ?? old('product_search') }}"
                                    placeholder="Type item name or SKU..." autocomplete="off" required>
                                <input type="hidden" name="product_id" id="product_id" value="{{ $selectedProductId }}">
                                <div id="product-suggestions"
                                    class="autocomplete-suggestions d-none shadow border rounded-bottom bg-white"></div>
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
                                        <input type="radio" name="usage_type" value="subcategory" id="usage_subcategory"
                                            class="form-check-input usage-type-input" @checked(old('usage_type', 'subcategory') === 'subcategory')>
                                        <label class="form-check-label" for="usage_subcategory">Item Type</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="usage_type" value="other" id="usage_other"
                                            class="form-check-input usage-type-input" @checked(old('usage_type') === 'other')>
                                        <label class="form-check-label" for="usage_other">Other / plant use</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6" id="subcategory-field">
                                <label class="form-label" for="sub_category_id">Item Type <span
                                        class="text-danger">*</span></label>
                                <select name="sub_category_id" id="sub_category_id"
                                    class="form-select form-select-sm @error('sub_category_id') is-invalid @enderror">
                                    <option value="">— Select subcategory —</option>
                                    @foreach($subCategories as $sc)
                                        <option value="{{ $sc->id }}" @selected(old('sub_category_id') == $sc->id)>{{ $sc->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sub_category_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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
                'unit' => $p->unit?->abbreviation ?? $p->unit?->name ?? '',
            ])->values();
        @endphp
        <style>
            .autocomplete-suggestions {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                z-index: 1050;
                max-height: 260px;
                overflow-y: auto;
                margin-top: 2px;
            }

            .suggestion-item {
                cursor: pointer;
                transition: background 0.15s ease-in-out;
            }

            .suggestion-item:hover,
            .suggestion-item.active {
                background-color: #f8f9fa;
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const products = @json($productOptions);
                const stockCheckUrl = @json(url('/api/plants'));

                const plantSelect = document.getElementById('plant_id');
                const categorySelect = document.getElementById('category_id');
                const searchInput = document.getElementById('product-search');
                const productIdInput = document.getElementById('product_id');
                const suggestionsBox = document.getElementById('product-suggestions');
                const availableQty = document.getElementById('available-qty');
                const selectedMeta = document.getElementById('selected-product-meta');
                const quantityInput = document.getElementById('quantity');
                const subcategoryField = document.getElementById('subcategory-field');
                const otherPurposeField = document.getElementById('other-purpose-field');
                const subcategorySelect = document.getElementById('sub_category_id');
                const reasonInput = document.getElementById('reason');
                const reasonRequiredMark = document.getElementById('reason-required-mark');

                let availableQuantity = null;

                function filteredProducts() {
                    const categoryId = categorySelect.value;
                    const term = searchInput.value.trim().toLowerCase();

                    return products.filter(p => {
                        if (categoryId && String(p.category_id) !== String(categoryId)) {
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
                    suggestionsBox.innerHTML = '';
                    if (list.length === 0) {
                        suggestionsBox.classList.add('d-none');
                        return;
                    }

                    list.slice(0, 12).forEach(p => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                        div.innerHTML = `<span class="fw-semibold d-block text-truncate">${p.name}</span><span class="text-muted small">${p.sku || 'No SKU'} · ${p.unit}</span>`;
                        div.addEventListener('click', () => selectProduct(p));
                        suggestionsBox.appendChild(div);
                    });
                    suggestionsBox.classList.remove('d-none');
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
                    suggestionsBox.classList.add('d-none');
                    fetchAvailableQty();
                }

                function syncUsageFields() {
                    const isSubcategory = document.getElementById('usage_subcategory').checked;
                    subcategoryField.classList.toggle('d-none', !isSubcategory);
                    otherPurposeField.classList.toggle('d-none', isSubcategory);
                    subcategorySelect.required = isSubcategory;
                    reasonInput.required = !isSubcategory;
                    reasonRequiredMark.classList.toggle('d-none', isSubcategory);
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
                    productIdInput.value = '';
                    searchInput.value = '';
                    selectedMeta.classList.add('d-none');
                    availableQty.textContent = 'Select plant and product';
                    availableQuantity = null;
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

                    if (!document.getElementById('usage_subcategory').checked) {
                        const purpose = document.getElementById('other_purpose').value.trim();
                        if (purpose && !reasonInput.value.includes(purpose)) {
                            reasonInput.value = purpose + (reasonInput.value ? '\n' + reasonInput.value : '');
                        }
                    }
                });

                document.querySelectorAll('.usage-type-input').forEach(el => el.addEventListener('change', syncUsageFields));

                document.addEventListener('click', function (e) {
                    if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                        suggestionsBox.classList.add('d-none');
                    }
                });

                syncUsageFields();

                if (productIdInput.value) {
                    fetchAvailableQty();
                }
            });
        </script>
    @endpush
@endsection