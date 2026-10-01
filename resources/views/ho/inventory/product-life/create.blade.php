@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Add Product Life')
@section('content')
    <x-admin-page-header title="Add Product Life Entry"
        subtitle="Pehle stock out hui vehicle entry select karo. Meter reading usi stock out par lagegi — naya stock out nahi banega." />

    @if($stockOuts->isEmpty())
        <div class="admin-card admin-card-body text-center py-5">
            <i class="bi bi-box-arrow-up fs-3 d-block mb-2 text-muted"></i>
            <p class="mb-3">Koi vehicle stock out product life ke liye pending nahi hai.</p>
            <a href="{{ route('ho.stocks.stock-out') }}" class="btn btn-primary btn-sm">Record Stock Out</a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                <form method="POST" action="{{ route('ho.product-life.store') }}" class="admin-card admin-card-body" id="product-life-form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Plant</label>
                            <select id="plant_filter" class="form-select">
                                <option value="">All plants</option>
                                @foreach($plants as $plant)
                                    <option value="{{ $plant->id }}">{{ $plant->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">List ko plant se narrow karne ke liye</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Vehicle</label>
                            <select id="vehicle_filter" class="form-select">
                                <option value="">All vehicles</option>
                                @foreach($vehicles as $vehicle)
                                    @php
                                        $plantIds = $stockOuts->where('vehicle_id', $vehicle->id)->pluck('plant_id')->unique()->implode(',');
                                    @endphp
                                    <option value="{{ $vehicle->id }}" data-plant-ids="{{ $plantIds }}">
                                        {{ $vehicle->displayLabel() }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Vehicle select karo to usi ke stock out, warna saare pending</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Stock out <span class="text-danger">*</span></label>
                            <select name="stock_movement_id" id="stock_movement_id"
                                class="form-select searchable-item-select @error('stock_movement_id') is-invalid @enderror" required>
                                <option value="">Search product, vehicle or plant</option>
                                @foreach($stockOuts as $movement)
                                    @php
                                        $itemType = $movement->subCategory?->name ?? $movement->product?->subCategory?->name;
                                        $subCategoryId = $movement->sub_category_id ?: $movement->product?->sub_category_id;
                                    @endphp
                                    <option value="{{ $movement->id }}"
                                        data-name="{{ $movement->product?->name }}"
                                        data-sku="{{ $movement->vehicle?->displayLabel() }}"
                                        data-category="{{ $movement->plant?->name }}"
                                        data-item-type="{{ $itemType }}"
                                        data-plant-id="{{ $movement->plant_id }}"
                                        data-vehicle-id="{{ $movement->vehicle_id }}"
                                        data-category-id="{{ $movement->product?->category_id }}"
                                        data-sub-category-id="{{ $subCategoryId }}"
                                        data-date="{{ $movement->moved_at?->format('d M Y') }}"
                                        data-plant="{{ $movement->plant?->name }}"
                                        data-vehicle="{{ $movement->vehicle?->displayLabel() }}"
                                        data-product="{{ $movement->product?->name }}"
                                        data-product-category="{{ $movement->product?->category?->name }}"
                                        data-qty="{{ number_format((float) $movement->quantity, 2) }}"
                                        data-reason="{{ $movement->reason }}"
                                        @selected(old('stock_movement_id') == $movement->id)>
                                        {{ $movement->moved_at?->format('d M Y') }} — {{ $movement->vehicle?->displayLabel() }} — {{ $movement->product?->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('stock_movement_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Sirf woh stock out jisme product life abhi nahi lagi</div>
                        </div>

                        <div class="col-12 d-none" id="stock_out_details">
                            <div class="border rounded p-3 bg-light">
                                <div class="row g-2 small">
                                    <div class="col-md-4"><span class="text-muted">Date</span><div class="fw-semibold" id="detail_date">—</div></div>
                                    <div class="col-md-4"><span class="text-muted">Plant</span><div class="fw-semibold" id="detail_plant">—</div></div>
                                    <div class="col-md-4"><span class="text-muted">Vehicle</span><div class="fw-semibold" id="detail_vehicle">—</div></div>
                                    <div class="col-md-4"><span class="text-muted">Product</span><div class="fw-semibold" id="detail_product">—</div></div>
                                    <div class="col-md-4"><span class="text-muted">Category</span><div class="fw-semibold" id="detail_category">—</div></div>
                                    <div class="col-md-4"><span class="text-muted">Item Type</span><div class="fw-semibold" id="detail_item_type">—</div></div>
                                    <div class="col-md-4"><span class="text-muted">Qty issued</span><div class="fw-semibold" id="detail_qty">—</div></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Meter type <span class="text-danger">*</span></label>
                            <select name="meter_type" id="meter_type" class="form-select" required>
                                <option value="hours" @selected(old('meter_type', 'hours') === 'hours')>Hours</option>
                                <option value="km" @selected(old('meter_type') === 'km')>KM</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Previous reading (pichli change)</label>
                            <input type="number" step="0.01" min="0" name="previous_reading" id="previous_reading"
                                value="{{ old('previous_reading') }}" class="form-control bg-light"
                                placeholder="Last change pe meter">
                            <div class="form-text" id="previous_reading_hint">
                                Last time isi type pe change kiya tha uska KM/hrs
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Current reading (abhi) <span class="text-danger">*</span>
                                <span class="text-muted" id="meter_unit_label">(hrs)</span>
                            </label>
                            <input type="number" step="0.01" min="0" name="current_reading" id="current_reading"
                                value="{{ old('current_reading') }}"
                                class="form-control @error('current_reading') is-invalid @enderror"
                                required placeholder="e.g. 45200">
                            @error('current_reading')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div id="life_used_hint" class="form-text text-primary fw-semibold d-none">
                                Previous item life used: <strong id="life_used_value">—</strong>
                                <span id="life_used_item" class="text-muted fw-normal"></span>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Optional remarks...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Save Entry
                        </button>
                        <a href="{{ route('ho.product-life.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div id="last_entry_card" class="admin-card admin-card-body d-none">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-clock-history text-primary fs-5"></i>
                        <h6 class="mb-0 fw-semibold">Pichli change (ye vehicle + item type)</h6>
                    </div>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Date</td>
                                <td id="ref_used_at" class="fw-semibold">—</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Us time pe fitted item</td>
                                <td id="ref_product" class="fw-semibold text-danger">—</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Meter</td>
                                <td id="ref_meter_type">—</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Us time ka reading</td>
                                <td id="ref_current_reading" class="fw-semibold">—</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Item Type</td>
                                <td id="ref_sub_category">—</td>
                            </tr>
                            <tr id="ref_notes_row" class="d-none">
                                <td class="text-muted">Notes</td>
                                <td id="ref_notes" class="text-muted small">—</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="small text-muted mt-3 mb-0">
                        Ab current reading daalo → <strong>current − previous = previous item kitna use hua</strong>.
                    </p>
                </div>
                <div id="last_entry_empty" class="admin-card admin-card-body text-muted text-center d-none">
                    <i class="bi bi-journal-x fs-3 d-block mb-2 opacity-50"></i>
                    Is vehicle pe is item type ki pehli change hai. Previous reading blank / 0 rakh sakte ho.
                </div>
                <div id="last_entry_loading" class="admin-card admin-card-body text-center text-muted d-none">
                    <div class="spinner-border spinner-border-sm me-2"></div>Loading previous change...
                </div>
                <div class="admin-card admin-card-body text-muted small" id="last_entry_placeholder">
                    <i class="bi bi-info-circle me-1"></i>Stock out select karo. Item type product se aa jayega.
                    <hr class="my-2">
                    <ul class="ps-3 mb-0" style="line-height:1.8;">
                        <li>Stock pehle <strong>Stock Out</strong> se vehicle par ja chuka hona chahiye</li>
                        <li>Wahi entry yahan select karke meter reading save hoti hai</li>
                        <li>Dobara quantity stock se nahi kategi</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <script>
        const lastEntryUrl = @json(route('ho.product-life.last-entry'));
        let lastEntry = null;

        const plantFilter = document.getElementById('plant_filter');
        const vehicleFilter = document.getElementById('vehicle_filter');
        const stockOutSelect = document.getElementById('stock_movement_id');
        const meterTypeSelect = document.getElementById('meter_type');
        const previousReadingInput = document.getElementById('previous_reading');
        const currentReadingInput = document.getElementById('current_reading');

        if (stockOutSelect) {
            const originalStockOutOptions = Array.from(stockOutSelect.options);
            const originalVehicleOptions = vehicleFilter ? Array.from(vehicleFilter.options) : [];

            function selectedStockOut() {
                return stockOutSelect.options[stockOutSelect.selectedIndex] || null;
            }

            function filterVehicles() {
                if (!vehicleFilter) return;
                const plantId = plantFilter.value;
                const current = vehicleFilter.value;
                vehicleFilter.innerHTML = '';
                originalVehicleOptions.forEach(function (option) {
                    if (!option.value) {
                        vehicleFilter.appendChild(option.cloneNode(true));
                        return;
                    }
                    const plantIds = (option.getAttribute('data-plant-ids') || '').split(',');
                    if (!plantId || plantIds.indexOf(String(plantId)) !== -1) {
                        vehicleFilter.appendChild(option.cloneNode(true));
                    }
                });
                if (current && Array.from(vehicleFilter.options).some(function (o) { return o.value === String(current); })) {
                    vehicleFilter.value = String(current);
                } else {
                    vehicleFilter.value = '';
                }
            }

            function filterStockOuts() {
                const plantId = plantFilter.value;
                const vehicleId = vehicleFilter ? vehicleFilter.value : '';
                const current = stockOutSelect.value;
                stockOutSelect.innerHTML = '';
                originalStockOutOptions.forEach(function (option) {
                    if (!option.value) {
                        stockOutSelect.appendChild(option.cloneNode(true));
                        return;
                    }
                    if (plantId && option.getAttribute('data-plant-id') !== String(plantId)) {
                        return;
                    }
                    if (vehicleId && option.getAttribute('data-vehicle-id') !== String(vehicleId)) {
                        return;
                    }
                    stockOutSelect.appendChild(option.cloneNode(true));
                });
                if (current && Array.from(stockOutSelect.options).some(function (o) { return o.value === String(current); })) {
                    stockOutSelect.value = String(current);
                } else {
                    stockOutSelect.value = '';
                }
                stockOutSelect.dispatchEvent(new Event('change'));
            }

            function meterUnit() {
                return meterTypeSelect.value === 'km' ? 'km' : 'hrs';
            }

            function updateMeterLabels() {
                document.getElementById('meter_unit_label').textContent = '(' + meterUnit() + ')';
                updateLifeUsedHint();
            }

            function updateLifeUsedHint() {
                const prev = parseFloat(previousReadingInput.value);
                const curr = parseFloat(currentReadingInput.value);
                const hint = document.getElementById('life_used_hint');
                const itemEl = document.getElementById('life_used_item');
                if (!isNaN(prev) && !isNaN(curr) && curr >= prev) {
                    document.getElementById('life_used_value').textContent =
                        (curr - prev).toFixed(2) + ' ' + meterUnit();
                    itemEl.textContent = lastEntry && lastEntry.product_name
                        ? ' (' + lastEntry.product_name + ')'
                        : '';
                    hint.classList.remove('d-none');
                } else {
                    hint.classList.add('d-none');
                }
            }

            function applyPreviousReadingFromLast(data) {
                if (data.current_reading !== null && data.current_reading !== undefined) {
                    previousReadingInput.value = data.current_reading;
                }
                if (data.meter_type) {
                    meterTypeSelect.value = data.meter_type;
                }
                const unit = data.meter_type === 'km' ? 'km' : 'hrs';
                document.getElementById('previous_reading_hint').textContent = (data.product_name || 'Previous item')
                    + ' fitted at ' + data.current_reading + ' ' + unit
                    + ' on ' + (data.used_at || '—');
                updateMeterLabels();
            }

            function loadLastEntry() {
                const option = selectedStockOut();
                const vehicleId = option && option.value ? option.getAttribute('data-vehicle-id') : '';
                const subCategoryId = option && option.value ? option.getAttribute('data-sub-category-id') : '';

                if (!vehicleId || !subCategoryId) {
                    if (!@json((bool) old('previous_reading'))) {
                        previousReadingInput.value = '';
                    }
                    document.getElementById('previous_reading_hint').textContent =
                        'Last time isi type pe change kiya tha uska KM/hrs';
                    showPlaceholder();
                    updateLifeUsedHint();
                    return;
                }

                showLoading();
                const params = new URLSearchParams({
                    vehicle_id: vehicleId,
                    sub_category_id: subCategoryId,
                });

                fetch(lastEntryUrl + '?' + params.toString())
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        lastEntry = data && data.used_at ? data : null;
                        if (lastEntry) {
                            showCard(data);
                            applyPreviousReadingFromLast(data);
                            updateLifeUsedHint();
                        } else {
                            previousReadingInput.value = '';
                            document.getElementById('previous_reading_hint').textContent =
                                'Pehli change — previous blank/0 rakh sakte ho';
                            showEmpty();
                            updateLifeUsedHint();
                        }
                    })
                    .catch(function () { showPlaceholder(); });
            }

            function showStockOutDetails(option) {
                const box = document.getElementById('stock_out_details');
                if (!option || !option.value) {
                    box.classList.add('d-none');
                    return;
                }
                document.getElementById('detail_date').textContent = option.getAttribute('data-date') || '—';
                document.getElementById('detail_plant').textContent = option.getAttribute('data-plant') || '—';
                document.getElementById('detail_vehicle').textContent = option.getAttribute('data-vehicle') || '—';
                document.getElementById('detail_product').textContent = option.getAttribute('data-product') || '—';
                document.getElementById('detail_category').textContent = option.getAttribute('data-product-category') || '—';
                document.getElementById('detail_item_type').textContent = option.getAttribute('data-item-type') || '—';
                document.getElementById('detail_qty').textContent = option.getAttribute('data-qty') || '—';
                box.classList.remove('d-none');
            }

            plantFilter.addEventListener('change', function () {
                filterVehicles();
                filterStockOuts();
            });
            if (vehicleFilter) {
                vehicleFilter.addEventListener('change', filterStockOuts);
            }
            stockOutSelect.addEventListener('change', function () {
                const option = selectedStockOut();
                showStockOutDetails(option);
                loadLastEntry();
            });
            meterTypeSelect.addEventListener('change', updateMeterLabels);
            previousReadingInput.addEventListener('input', updateLifeUsedHint);
            currentReadingInput.addEventListener('input', updateLifeUsedHint);

            function showCard(data) {
                document.getElementById('last_entry_card').classList.remove('d-none');
                document.getElementById('last_entry_empty').classList.add('d-none');
                document.getElementById('last_entry_loading').classList.add('d-none');
                document.getElementById('last_entry_placeholder').classList.add('d-none');

                const unit = data.meter_type === 'km' ? 'km' : 'hrs';
                document.getElementById('ref_used_at').textContent = data.used_at ?? '—';
                document.getElementById('ref_product').textContent = data.product_name ?? '—';
                document.getElementById('ref_meter_type').textContent = data.meter_type === 'km' ? 'KM' : 'Hours';
                document.getElementById('ref_current_reading').textContent =
                    (data.current_reading != null ? data.current_reading : '—')
                    + (data.current_reading != null ? ' ' + unit : '');
                document.getElementById('ref_sub_category').textContent = data.sub_category ?? '—';

                const notesRow = document.getElementById('ref_notes_row');
                if (data.notes) {
                    document.getElementById('ref_notes').textContent = data.notes;
                    notesRow.classList.remove('d-none');
                } else {
                    notesRow.classList.add('d-none');
                }
            }

            function showEmpty() {
                document.getElementById('last_entry_card').classList.add('d-none');
                document.getElementById('last_entry_empty').classList.remove('d-none');
                document.getElementById('last_entry_loading').classList.add('d-none');
                document.getElementById('last_entry_placeholder').classList.add('d-none');
            }

            function showLoading() {
                document.getElementById('last_entry_card').classList.add('d-none');
                document.getElementById('last_entry_empty').classList.add('d-none');
                document.getElementById('last_entry_loading').classList.remove('d-none');
                document.getElementById('last_entry_placeholder').classList.add('d-none');
            }

            function showPlaceholder() {
                document.getElementById('last_entry_card').classList.add('d-none');
                document.getElementById('last_entry_empty').classList.add('d-none');
                document.getElementById('last_entry_loading').classList.add('d-none');
                document.getElementById('last_entry_placeholder').classList.remove('d-none');
                lastEntry = null;
            }

            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(function () {
                    const input = stockOutSelect.closest('.searchable-item-select-wrap')
                        ?.querySelector('.searchable-item-select-input');
                    if (input) {
                        input.placeholder = 'Search product, vehicle or plant...';
                    }
                    if (stockOutSelect.value) {
                        stockOutSelect.dispatchEvent(new Event('change'));
                    }
                }, 0);
            });

            updateMeterLabels();
        }
    </script>
@endsection
