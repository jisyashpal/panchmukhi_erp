@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Stock Out')
@section('content')
    <x-admin-page-header title="Stock Out" subtitle="Issue multiple items against one vehicle / machine">
        <div class="d-flex gap-2 flex-wrap">
            <x-report-export-button />
            <a href="{{ route('ho.stocks.stock-out.bulk-upload') }}" class="btn btn-admin-outline btn-sm">
                <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Upload
            </a>
            <a href="{{ route('ho.stocks.index') }}" class="btn btn-admin-outline btn-sm">Back</a>
        </div>
    </x-admin-page-header>

    <x-report-filters
        :plants="$plants"
        :vehicles="$vehicles"
        :show-dates="true"
        search-placeholder="Product, SKU, plant, vehicle..."
        :clear-route="route('ho.stocks.stock-out')"
    />

    @include('partials.stock-movement-list', [
        'type' => 'out',
        'movements' => $movements,
    ])

    <form method="POST" action="{{ route('ho.stocks.stock-out.store') }}" enctype="multipart/form-data" id="stock-out-form">
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="admin-card mb-4">
                    <div class="admin-card-header">
                        <h2 class="h6 mb-0">Plant &amp; usage</h2>
                    </div>
                    <div class="admin-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="plant_id">Plant</label>
                                <select name="plant_id" id="plant_id" class="form-select form-select-sm" required>
                                    @foreach($plants as $s)
                                        <option value="{{ $s->id }}" @selected((int) old('plant_id', $plantId) === $s->id)>{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="moved_at">Issued date</label>
                                <input type="date" name="moved_at" id="moved_at"
                                    value="{{ old('moved_at', now()->toDateString()) }}"
                                    class="form-control form-control-sm @error('moved_at') is-invalid @enderror">
                                @error('moved_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label d-block">Used for</label>
                                <div class="d-flex flex-wrap gap-3 pt-1">
                                    <div class="form-check">
                                        <input type="radio" name="usage_type" value="vehicle" id="usage_vehicle"
                                            class="form-check-input usage-type-input" @checked(old('usage_type', 'vehicle') === 'vehicle')>
                                        <label class="form-check-label" for="usage_vehicle">Vehicle / Machine</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="usage_type" value="other" id="usage_other"
                                            class="form-check-input usage-type-input" @checked(old('usage_type') === 'other')>
                                        <label class="form-check-label" for="usage_other">Other / plant use</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6" id="vehicle-field">
                                <label class="form-label" for="vehicle_id">Vehicle / Machine <span class="text-danger">*</span></label>
                                <select name="vehicle_id" id="vehicle_id"
                                    class="form-select form-select-sm @error('vehicle_id') is-invalid @enderror">
                                    <option value="">— Select vehicle / machine —</option>
                                    @foreach($vehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>
                                            {{ $vehicle->displayLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vehicle_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    One machine can have multiple items in this stock out.
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

            <div class="col-12">
                <div class="admin-card admin-table-card mb-0">
                    <div class="admin-card-header d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0">Items</h2>
                        @error('items')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 stock-out-lines-table">
                            <thead>
                                <tr>
                                    <th class="stock-out-col-num">#</th>
                                    <th class="stock-out-col-filter">Category</th>
                                    <th class="stock-out-col-filter">Item Type</th>
                                    <th>Search item name</th>
                                    <th class="stock-out-col-avail">Available</th>
                                    <th class="stock-out-col-qty">Qty</th>
                                    <th class="stock-out-col-amount">Rate (₹)</th>
                                    <th class="stock-out-col-action"></th>
                                </tr>
                            </thead>
                            <tbody id="stock-out-lines"></tbody>
                        </table>
                    </div>
                    <div class="admin-card-body border-top py-2">
                        <button type="button" class="btn btn-sm btn-admin-outline" id="add-stock-out-line">
                            <i class="bi bi-plus-lg me-1"></i>Add product
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <template id="stock-out-line-template">
        <tr class="stock-out-line">
            <td class="stock-out-col-num text-muted small fw-semibold">
                <span class="line-number">1</span>
            </td>
            <td class="stock-out-col-filter">
                <select class="form-select form-select-sm line-category" aria-label="Category">
                    <option value="">All categories</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </td>
            <td class="stock-out-col-filter">
                <select class="form-select form-select-sm line-item-type" aria-label="Item Type">
                    <option value="">All item types</option>
                    @foreach($subCategories as $sc)
                        <option value="{{ $sc->id }}" data-category-id="{{ $sc->category_id }}">{{ $sc->name }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm line-search"
                    placeholder="Type item name or SKU..." autocomplete="off" required
                    aria-label="Search item name">
                <input type="hidden" name="items[__INDEX__][product_id]" class="line-product-id" value="">
                <div class="form-text line-product-meta d-none mb-0"></div>
                <div class="invalid-feedback d-block line-product-error"></div>
            </td>
            <td class="stock-out-col-avail">
                <div class="form-control form-control-sm bg-white line-available text-truncate">—</div>
            </td>
            <td class="stock-out-col-qty">
                <input type="number" step="0.01" min="0.01" name="items[__INDEX__][quantity]"
                    class="form-control form-control-sm line-quantity" required aria-label="Quantity to issue">
            </td>
            <td class="stock-out-col-amount">
                <input type="number" step="0.01" min="0" name="items[__INDEX__][unit_price]"
                    class="form-control form-control-sm line-amount" placeholder="Optional" aria-label="Rate per unit">
                <div class="form-text line-amount-total mb-0">Total: —</div>
            </td>
            <td class="stock-out-col-action text-end">
                <button type="button" class="btn btn-outline-danger btn-sm remove-line-btn" title="Remove item">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    </template>

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
            $oldItems = old('items');
        @endphp
        <style>
            #stock-out-product-suggestions {
                position: fixed;
                max-height: 260px;
                overflow-y: auto;
                overscroll-behavior: contain;
                z-index: 2000;
                display: none;
            }
            #stock-out-product-suggestions.is-open { display: block; }
            #stock-out-product-suggestions.is-above {
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
                border-top-left-radius: 0.375rem;
                border-top-right-radius: 0.375rem;
            }
            #stock-out-product-suggestions .suggestion-item { cursor: pointer; }
            #stock-out-product-suggestions .suggestion-item:hover,
            #stock-out-product-suggestions .suggestion-item.active { background-color: #f8f9fa; }
            .stock-out-lines-table { min-width: 48rem; width: 100%; table-layout: fixed; }
            .stock-out-lines-table .stock-out-col-num { width: 2.5rem; }
            .stock-out-lines-table .stock-out-col-filter { width: 16%; }
            .stock-out-lines-table .stock-out-col-avail { width: 7.5rem; }
            .stock-out-lines-table .stock-out-col-qty { width: 6.25rem; }
            .stock-out-lines-table .stock-out-col-amount { width: 8rem; }
            .stock-out-lines-table .stock-out-col-action { width: 2.75rem; }
            .stock-out-lines-table tbody td { vertical-align: top; padding-top: 0.55rem; padding-bottom: 0.55rem; }
            .stock-out-lines-table .line-search,
            .stock-out-lines-table .line-category,
            .stock-out-lines-table .line-item-type,
            .stock-out-lines-table .line-quantity,
            .stock-out-lines-table .line-amount { width: 100%; }
            .stock-out-lines-table .line-available {
                min-height: calc(1.5em + 0.5rem + 2px);
                display: flex;
                align-items: center;
            }
        </style>
        @include('partials.infinite-suggestions')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const products = @json($productOptions);
                const stockByPlant = @json($stockByPlant ?? []);
                const stockCheckUrl = @json(url('/api/plants'));
                const oldItems = @json($oldItems ?: []);
                const rateByProduct = @json($rateByProduct ?? []);
                const initialProductId = @json($selectedProductId ? (string) $selectedProductId : '');
                const itemErrors = @json($errors->getMessages());

                const plantSelect = document.getElementById('plant_id');
                const linesWrap = document.getElementById('stock-out-lines');
                const template = document.getElementById('stock-out-line-template').innerHTML;
                const vehicleField = document.getElementById('vehicle-field');
                const otherPurposeField = document.getElementById('other-purpose-field');
                const vehicleSelect = document.getElementById('vehicle_id');
                const reasonInput = document.getElementById('reason');
                const reasonRequiredMark = document.getElementById('reason-required-mark');
                const addBtn = document.getElementById('add-stock-out-line');

                const suggestionsBox = document.createElement('div');
                suggestionsBox.id = 'stock-out-product-suggestions';
                suggestionsBox.className = 'autocomplete-suggestions shadow border rounded-bottom bg-white';
                document.body.appendChild(suggestionsBox);

                let rowIndex = 0;
                let activeLine = null;

                const infinite = window.PanchmukhiInfiniteSuggestions.create(suggestionsBox, {
                    pageSize: 25,
                    renderItem: function (p) {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                        const available = availableFor(p.id);
                        div.innerHTML = `<span class="fw-semibold d-block text-truncate">${p.name}</span><span class="text-muted small">${p.sku || 'No SKU'} · ${p.unit || '—'} · Stock: ${available}</span>`;
                        div.addEventListener('mousedown', function (event) {
                            event.preventDefault();
                            if (activeLine) {
                                selectProduct(activeLine, p);
                            }
                        });
                        return div;
                    },
                });

                function hideSuggestions() {
                    infinite.reset();
                    suggestionsBox.classList.remove('is-open');
                }

                function positionSuggestions(input) {
                    const rect = input.getBoundingClientRect();
                    const gap = 2;
                    const edge = 8;
                    const preferred = 260;
                    const spaceBelow = window.innerHeight - rect.bottom - edge;
                    const spaceAbove = rect.top - edge;
                    const openAbove = spaceBelow < 160 && spaceAbove > spaceBelow;
                    const maxHeight = Math.max(80, Math.min(preferred, openAbove ? spaceAbove : spaceBelow));

                    suggestionsBox.style.left = rect.left + 'px';
                    suggestionsBox.style.width = rect.width + 'px';
                    suggestionsBox.style.maxHeight = maxHeight + 'px';
                    suggestionsBox.classList.toggle('is-above', openAbove);

                    if (openAbove) {
                        suggestionsBox.style.top = 'auto';
                        suggestionsBox.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
                    } else {
                        suggestionsBox.style.bottom = 'auto';
                        suggestionsBox.style.top = (rect.bottom + gap) + 'px';
                    }
                }

                function filterItemTypes(select, categoryId, keepValue) {
                    const original = select.originalOptions || Array.from(select.querySelectorAll('option'));
                    if (!select.originalOptions) {
                        select.originalOptions = original.map(o => o.cloneNode(true));
                    }
                    const current = keepValue || select.value;
                    select.innerHTML = '';
                    select.originalOptions.forEach(option => {
                        const clone = option.cloneNode(true);
                        if (!clone.value || !categoryId || clone.getAttribute('data-category-id') === String(categoryId)) {
                            select.appendChild(clone);
                        }
                    });
                    if (current && Array.from(select.options).some(o => o.value === String(current))) {
                        select.value = String(current);
                    } else {
                        select.value = '';
                    }
                }

                function availableFor(productId) {
                    const plantStock = stockByPlant[String(plantSelect.value)] || stockByPlant[plantSelect.value] || {};
                    const qty = plantStock[String(productId)] ?? plantStock[productId];
                    return qty === undefined || qty === null ? 0 : Number(qty);
                }

                function showAvailable(line, productId) {
                    const availableEl = line.querySelector('.line-available');
                    const product = products.find(p => String(p.id) === String(productId));
                    const qty = availableFor(productId);
                    const unit = product?.unit ? ` ${product.unit}` : '';
                    line.availableQuantity = qty;
                    availableEl.textContent = `${qty}${unit}`;
                    availableEl.classList.toggle('text-danger', qty <= 0);
                    availableEl.classList.toggle('text-success', qty > 0);
                }

                function filteredProducts(line) {
                    const categoryId = line.querySelector('.line-category').value;
                    const itemTypeId = line.querySelector('.line-item-type').value;
                    const term = line.querySelector('.line-search').value.trim().toLowerCase();

                    return products.filter(p => {
                        if (categoryId && String(p.category_id) !== String(categoryId)) return false;
                        if (itemTypeId && String(p.sub_category_id) !== String(itemTypeId)) return false;
                        if (!term) return true;
                        return p.name.toLowerCase().includes(term) || (p.sku && p.sku.toLowerCase().includes(term));
                    });
                }

                function renderSuggestions(line) {
                    const list = filteredProducts(line);
                    if (!infinite.setResults(list)) {
                        hideSuggestions();
                        return;
                    }
                    positionSuggestions(line.querySelector('.line-search'));
                    suggestionsBox.classList.add('is-open');
                }

                async function fetchAvailableQty(line) {
                    const plantId = plantSelect.value;
                    const productId = line.querySelector('.line-product-id').value;
                    const availableEl = line.querySelector('.line-available');

                    if (!plantId || !productId) {
                        availableEl.textContent = '—';
                        line.availableQuantity = null;
                        return;
                    }

                    showAvailable(line, productId);
                    try {
                        const response = await fetch(`${stockCheckUrl}/${plantId}/products/${productId}/stock`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error('Failed');
                        const data = await response.json();
                        line.availableQuantity = parseFloat(data.quantity);
                        const product = products.find(p => String(p.id) === String(productId));
                        const unit = product?.unit ? ` ${product.unit}` : '';
                        availableEl.textContent = `${line.availableQuantity}${unit}`;
                        availableEl.classList.toggle('text-danger', line.availableQuantity <= 0);
                        availableEl.classList.toggle('text-success', line.availableQuantity > 0);
                    } catch (e) {
                        showAvailable(line, productId);
                    }
                }

                function selectProduct(line, product) {
                    line.querySelector('.line-product-id').value = product.id;
                    line.querySelector('.line-search').value = product.name;
                    const meta = line.querySelector('.line-product-meta');
                    meta.innerHTML = `SKU: <code>${product.sku || '—'}</code> · Unit: ${product.unit || '—'}`;
                    meta.classList.remove('d-none');
                    hideSuggestions();

                    if (product.category_id) {
                        line.querySelector('.line-category').value = String(product.category_id);
                        filterItemTypes(line.querySelector('.line-item-type'), product.category_id, product.sub_category_id);
                    }
                    if (product.sub_category_id) {
                        line.querySelector('.line-item-type').value = String(product.sub_category_id);
                    }

                    fetchAvailableQty(line);
                    const amountInput = line.querySelector('.line-amount');
                    if (amountInput && amountInput.value === '') {
                        const rate = rateByProduct[String(product.id)] ?? rateByProduct[product.id];
                        if (rate !== undefined && rate !== null && Number(rate) > 0) {
                            amountInput.value = Number(rate).toFixed(2);
                        }
                    }
                    updateLineTotal(line);
                }

                function updateLineTotal(line) {
                    const totalEl = line.querySelector('.line-amount-total');
                    const qty = parseFloat(line.querySelector('.line-quantity').value);
                    const rate = parseFloat(line.querySelector('.line-amount').value);
                    if (!totalEl) return;
                    if (!Number.isFinite(qty) || !Number.isFinite(rate)) {
                        totalEl.textContent = 'Total: —';
                        return;
                    }
                    totalEl.textContent = 'Total: ₹' + (qty * rate).toFixed(2);
                }

                function clearProduct(line) {
                    line.querySelector('.line-product-id').value = '';
                    line.querySelector('.line-product-meta').classList.add('d-none');
                    line.querySelector('.line-available').textContent = '—';
                    line.availableQuantity = null;
                }

                function clearLineFilters(line) {
                    const categorySelect = line.querySelector('.line-category');
                    const itemTypeSelect = line.querySelector('.line-item-type');
                    if (categorySelect) {
                        categorySelect.value = '';
                    }
                    if (itemTypeSelect) {
                        filterItemTypes(itemTypeSelect, '');
                        itemTypeSelect.value = '';
                    }
                }

                function renumberLines() {
                    linesWrap.querySelectorAll('.stock-out-line').forEach((line, i) => {
                        line.querySelector('.line-number').textContent = String(i + 1);
                    });
                }

                function bindLine(line) {
                    const searchInput = line.querySelector('.line-search');
                    const categorySelect = line.querySelector('.line-category');
                    const itemTypeSelect = line.querySelector('.line-item-type');
                    filterItemTypes(itemTypeSelect, '');

                    line.querySelector('.line-quantity').addEventListener('input', function () {
                        updateLineTotal(line);
                    });
                    line.querySelector('.line-amount').addEventListener('input', function () {
                        updateLineTotal(line);
                    });
                    searchInput.addEventListener('input', function () {
                        const selected = products.find(p => String(p.id) === line.querySelector('.line-product-id').value);
                        if (!searchInput.value.trim()) {
                            clearProduct(line);
                            clearLineFilters(line);
                            line.querySelector('.line-amount').value = '';
                            updateLineTotal(line);
                        } else if (!selected || searchInput.value !== selected.name) {
                            clearProduct(line);
                        }
                        activeLine = line;
                        renderSuggestions(line);
                    });
                    searchInput.addEventListener('focus', function () {
                        activeLine = line;
                        renderSuggestions(line);
                    });
                    categorySelect.addEventListener('change', function () {
                        clearProduct(line);
                        searchInput.value = '';
                        filterItemTypes(itemTypeSelect, categorySelect.value);
                        activeLine = line;
                        renderSuggestions(line);
                    });
                    itemTypeSelect.addEventListener('change', function () {
                        clearProduct(line);
                        searchInput.value = '';
                        activeLine = line;
                        renderSuggestions(line);
                    });
                    line.querySelector('.remove-line-btn').addEventListener('click', function () {
                        const rows = linesWrap.querySelectorAll('.stock-out-line');
                        if (rows.length <= 1) {
                            alert('At least one item is required.');
                            return;
                        }
                        line.remove();
                        renumberLines();
                    });
                }

                function addLine(prefill) {
                    const html = template.replace(/__INDEX__/g, String(rowIndex));
                    const wrap = document.createElement('tbody');
                    wrap.innerHTML = html.trim();
                    const line = wrap.firstElementChild;
                    linesWrap.appendChild(line);
                    bindLine(line);
                    rowIndex++;
                    renumberLines();

                    if (prefill && prefill.product_id) {
                        const product = products.find(p => String(p.id) === String(prefill.product_id));
                        if (product) {
                            selectProduct(line, product);
                        }
                    }
                    if (prefill && prefill.quantity) {
                        line.querySelector('.line-quantity').value = prefill.quantity;
                    }
                    if (prefill && prefill.unit_price !== undefined && prefill.unit_price !== null && prefill.unit_price !== '') {
                        line.querySelector('.line-amount').value = prefill.unit_price;
                    }
                    updateLineTotal(line);
                    if (prefill && prefill.error) {
                        line.querySelector('.line-product-error').textContent = prefill.error;
                        line.querySelector('.line-search').classList.add('is-invalid');
                        line.querySelector('.line-quantity').classList.add('is-invalid');
                    }
                    return line;
                }

                function syncUsageFields() {
                    const isVehicle = document.getElementById('usage_vehicle').checked;
                    vehicleField.classList.toggle('d-none', !isVehicle);
                    otherPurposeField.classList.toggle('d-none', isVehicle);
                    vehicleSelect.required = isVehicle;
                    reasonInput.required = !isVehicle;
                    reasonRequiredMark.classList.toggle('d-none', isVehicle);
                }

                addBtn.addEventListener('click', function () {
                    addLine();
                });

                plantSelect.addEventListener('change', function () {
                    linesWrap.querySelectorAll('.stock-out-line').forEach(fetchAvailableQty);
                });

                document.getElementById('stock-out-form').addEventListener('submit', function (e) {
                    const lines = Array.from(linesWrap.querySelectorAll('.stock-out-line'));
                    const seen = new Set();
                    for (const line of lines) {
                        const productId = line.querySelector('.line-product-id').value;
                        const qty = parseFloat(line.querySelector('.line-quantity').value);
                        const searchInput = line.querySelector('.line-search');
                        const qtyInput = line.querySelector('.line-quantity');

                        if (!productId) {
                            e.preventDefault();
                            searchInput.classList.add('is-invalid');
                            return;
                        }
                        if (seen.has(productId)) {
                            e.preventDefault();
                            alert('The same product cannot be issued twice. Combine quantities on one line.');
                            return;
                        }
                        seen.add(productId);
                        if (line.availableQuantity !== null && qty > line.availableQuantity) {
                            e.preventDefault();
                            qtyInput.classList.add('is-invalid');
                            alert(`Quantity cannot exceed available stock (${line.availableQuantity}).`);
                            return;
                        }
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
                    if (e.target.closest('.line-search') || suggestionsBox.contains(e.target)) {
                        return;
                    }
                    hideSuggestions();
                });

                window.addEventListener('scroll', function (e) {
                    if (!suggestionsBox.classList.contains('is-open') || suggestionsBox.contains(e.target)) {
                        return;
                    }
                    if (activeLine) {
                        positionSuggestions(activeLine.querySelector('.line-search'));
                    }
                }, true);

                window.addEventListener('resize', function () {
                    if (suggestionsBox.classList.contains('is-open') && activeLine) {
                        positionSuggestions(activeLine.querySelector('.line-search'));
                    }
                });

                syncUsageFields();

                if (Array.isArray(oldItems) && oldItems.length) {
                    oldItems.forEach((item, i) => {
                        const error = itemErrors[`items.${i}.product_id`]?.[0]
                            || itemErrors[`items.${i}.quantity`]?.[0]
                            || '';
                        addLine({
                            product_id: item.product_id,
                            quantity: item.quantity,
                            unit_price: item.unit_price,
                            error,
                        });
                    });
                } else {
                    addLine(initialProductId ? { product_id: initialProductId } : null);
                }
            });
        </script>
    @endpush
@endsection
