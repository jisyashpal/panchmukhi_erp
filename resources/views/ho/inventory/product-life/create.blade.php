@extends('layouts.ho')
@section('title', 'Add Product Life')
@section('content')
    <x-admin-page-header title="Add Product Life Entry"
        subtitle="Same vehicle + item type (e.g. Tyre) — purana KM aayega, previous item kitna use hua track hoga" />

    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('ho.product-life.store') }}" class="admin-card admin-card-body" id="product-life-form">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Plant <span class="text-danger">*</span></label>
                        <select name="plant_id" class="form-select @error('plant_id') is-invalid @enderror" required>
                            @foreach($plants as $s)
                                <option value="{{ $s->id }}" @selected(old('plant_id', $plantId) == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @error('plant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                            <option value="">— Select Category —</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Vehicle <span class="text-danger">*</span></label>
                        <select name="vehicle_id" id="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror" required>
                            <option value="">— Select vehicle —</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}"
                                    data-category-id="{{ $vehicle->category_id }}"
                                    @selected(old('vehicle_id') == $vehicle->id)>
                                    {{ $vehicle->displayLabel() }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Item Type <span class="text-danger">*</span></label>
                        <select name="sub_category_id" id="sub_category_id"
                            class="form-select @error('sub_category_id') is-invalid @enderror" required>
                            <option value="">— e.g. Tyre —</option>
                            @foreach($subCategories as $sc)
                                <option value="{{ $sc->id }}" data-category-id="{{ $sc->category_id }}"
                                    @selected(old('sub_category_id') == $sc->id)>
                                    {{ $sc->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('sub_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Same type pe last change ka KM/hrs previous reading me aayega</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">New item (ab laga rahe ho) <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                            <option value="">— Select Product —</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}"
                                    data-category-id="{{ $p->category_id }}"
                                    data-sub-category-id="{{ $p->sub_category_id }}"
                                    @selected(old('product_id') == $p->id)>
                                    {{ $p->name }}@if($p->category) ({{ $p->category->name }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="replaced_item_hint">Pichla fitted item save pe “replaced” me track hoga</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Used At <span class="text-danger">*</span></label>
                        <input type="date" name="used_at" value="{{ old('used_at', date('Y-m-d')) }}" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Meter type <span class="text-danger">*</span></label>
                        <select name="meter_type" id="meter_type" class="form-select" required>
                            <option value="hours" @selected(old('meter_type', 'hours') === 'hours')>Hours</option>
                            <option value="km" @selected(old('meter_type') === 'km')>KM</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Previous reading (pichli change)</label>
                        <input type="number" step="0.01" min="0" name="previous_reading" id="previous_reading"
                            value="{{ old('previous_reading') }}" class="form-control bg-light"
                            placeholder="Last change pe meter">
                        <div class="form-text" id="previous_reading_hint">
                            Last time isi type pe change kiya tha uska KM/hrs
                        </div>
                    </div>

                    <div class="col-md-4">
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

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Quantity Used <span class="text-danger">*</span>
                        </label>
                        <input type="number" step="0.01" min="0.01" name="quantity" id="quantity"
                            value="{{ old('quantity', '1') }}" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Usage Count</label>
                        <input type="number" min="1" name="usage_count" id="usage_count"
                            value="{{ old('usage_count', '1') }}" class="form-control">
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
                <i class="bi bi-info-circle me-1"></i>Vehicle + Item Type (jaise Tyre) select karo.
                <hr class="my-2">
                <ul class="ps-3 mb-0" style="line-height:1.8;">
                    <li><strong>Example:</strong> Tyre last change 40,000 km pe thi</li>
                    <li>Ab change 45,200 km pe → previous = 40,000, current = 45,200</li>
                    <li><strong>Previous tyre life used</strong> = 5,200 km</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        const lastEntryUrl = @json(route('ho.product-life.last-entry'));
        let lastEntry = null;

        const categorySelect = document.getElementById('category_id');
        const vehicleSelect = document.getElementById('vehicle_id');
        const subCategorySelect = document.getElementById('sub_category_id');
        const productSelect = document.getElementById('product_id');
        const meterTypeSelect = document.getElementById('meter_type');
        const previousReadingInput = document.getElementById('previous_reading');
        const currentReadingInput = document.getElementById('current_reading');

        const originalVehicleOptions = Array.from(vehicleSelect.options);
        const originalSubCategoryOptions = Array.from(subCategorySelect.options);
        const originalProductOptions = Array.from(productSelect.options);

        function filterOptions(select, originalOptions, matcher, keepValue) {
            const current = keepValue || select.value;
            select.innerHTML = '';
            originalOptions.forEach(function (option) {
                if (!option.value) {
                    select.appendChild(option.cloneNode(true));
                    return;
                }
                if (matcher(option)) {
                    select.appendChild(option.cloneNode(true));
                }
            });
            if (current && Array.from(select.options).some(function (o) { return o.value === String(current); })) {
                select.value = String(current);
            } else {
                select.value = '';
            }
        }

        function filterByCategory(select, originalOptions, categoryId, keepValue) {
            filterOptions(select, originalOptions, function (option) {
                return !categoryId || option.getAttribute('data-category-id') === String(categoryId);
            }, keepValue);
        }

        function filterProducts() {
            const categoryId = categorySelect.value;
            const subCategoryId = subCategorySelect.value;
            filterOptions(productSelect, originalProductOptions, function (option) {
                if (categoryId && option.getAttribute('data-category-id') !== String(categoryId)) {
                    return false;
                }
                if (subCategoryId && option.getAttribute('data-sub-category-id')
                    && option.getAttribute('data-sub-category-id') !== String(subCategoryId)) {
                    return false;
                }
                return true;
            });
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
                // Always overwrite when slot (vehicle + item type) changes
                previousReadingInput.value = data.current_reading;
            }
            if (data.meter_type) {
                meterTypeSelect.value = data.meter_type;
            }
            const hint = document.getElementById('previous_reading_hint');
            const unit = data.meter_type === 'km' ? 'km' : 'hrs';
            hint.textContent = (data.product_name || 'Previous item')
                + ' fitted at ' + data.current_reading + ' ' + unit
                + ' on ' + (data.used_at || '—');
            document.getElementById('replaced_item_hint').textContent =
                'Replaced item: ' + (data.product_name || '—')
                + ' — iska use = current reading − ' + data.current_reading + ' ' + unit;
            updateMeterLabels();
        }

        function loadLastEntry() {
            const vehicleId = vehicleSelect.value;
            const subCategoryId = subCategorySelect.value;

            if (!vehicleId || !subCategoryId) {
                previousReadingInput.value = '';
                document.getElementById('previous_reading_hint').textContent =
                    'Last time isi type pe change kiya tha uska KM/hrs';
                document.getElementById('replaced_item_hint').textContent =
                    'Pichla fitted item save pe “replaced” me track hoga';
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
                    lastEntry = data;
                    if (data) {
                        showCard(data);
                        applyPreviousReadingFromLast(data);
                        updateLifeUsedHint();
                    } else {
                        previousReadingInput.value = '';
                        document.getElementById('previous_reading_hint').textContent =
                            'Pehli change — previous blank/0 rakh sakte ho';
                        document.getElementById('replaced_item_hint').textContent =
                            'Is type pe pehli fitment — koi replaced item nahi';
                        showEmpty();
                        updateLifeUsedHint();
                    }
                })
                .catch(function () { showPlaceholder(); });
        }

        categorySelect.addEventListener('change', function () {
            const categoryId = categorySelect.value;
            filterByCategory(vehicleSelect, originalVehicleOptions, categoryId);
            filterByCategory(subCategorySelect, originalSubCategoryOptions, categoryId);
            filterProducts();
            loadLastEntry();
        });

        vehicleSelect.addEventListener('change', loadLastEntry);
        subCategorySelect.addEventListener('change', function () {
            filterProducts();
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

        if (categorySelect.value) {
            categorySelect.dispatchEvent(new Event('change'));
        } else {
            updateMeterLabels();
        }
    </script>
@endsection
