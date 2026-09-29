@extends($portalLayout ?? 'layouts.ho')
@php
    $isStockOut = ($direction ?? 'in') === 'out';
    $pageTitle = $isStockOut ? 'Bulk Stock Out' : 'Bulk Stock In';
    $storeRoute = $isStockOut ? route('ho.stocks.stock-out.bulk-upload.store') : route('ho.stocks.stock-in.bulk-upload.store');
    $sampleRoute = $isStockOut ? route('ho.stocks.stock-out.bulk-upload.sample') : route('ho.stocks.stock-in.bulk-upload.sample');
    $backRoute = $isStockOut ? route('ho.stocks.stock-out') : route('ho.stocks.stock-in');
@endphp
@section('title', $pageTitle)
@section('content')
    <x-admin-page-header :title="$pageTitle" :subtitle="$isStockOut ? 'Upload CSV to issue multiple items from a plant' : 'Select plant and upload CSV file to update stock balances'">
        <a href="{{ $backRoute }}" class="btn btn-admin-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </x-admin-page-header>

    @php
        $pendingRows = $pendingRows ?? [];
        $hasPending = count($pendingRows) > 0;
        $pendingErrorCount = collect($pendingRows)->filter(fn ($row) => ! empty($row['error']))->count();
        $vehicles = $vehicles ?? collect();
    @endphp

    @if ($hasPending)
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h5 class="card-title mb-2 fw-bold text-dark">
                    <i class="bi bi-pencil-square me-2 text-warning"></i>
                    {{ $isStockOut ? 'Fix products and machines before import' : 'Fix product names before import' }}
                </h5>
                <p class="text-muted small mb-3">
                    Nothing was saved. {{ $pendingErrorCount }} of {{ count($pendingRows) }} row(s) have errors.
                    @if ($isStockOut)
                        Error rows: select the catalog <strong>product</strong> and <strong>Vehicle / Machine</strong> from the list, then import.
                    @else
                        Error rows: <strong>search and select</strong> the catalog product, then import the full file.
                    @endif
                </p>

                <form action="{{ $storeRoute }}" method="POST">
                    @csrf
                    <input type="hidden" name="plant_id" value="{{ old('plant_id', $selectedPlantId) }}">
                    @if ($isStockOut)
                        <input type="hidden" name="reason" value="{{ old('reason', $pendingReason ?? '') }}">
                        <input type="hidden" name="out_mode" value="{{ old('out_mode', $pendingOutMode ?? 'adjust') }}">
                        @if($pendingAutoCreateVehicles ?? false)
                            <input type="hidden" name="auto_create_vehicles" value="1">
                        @endif
                    @else
                        <input type="hidden" name="update_mode" value="{{ old('update_mode', $pendingUpdateMode ?? 'add') }}">
                        @if($pendingAutoCreateProducts ?? false)
                            <input type="hidden" name="auto_create_products" value="1">
                        @endif
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle small mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Row</th>
                                    @if ($isStockOut)
                                        <th>Date</th>
                                        <th>Vehicle / Machine</th>
                                        <th>FOR</th>
                                        <th>NAME OF PARTS</th>
                                    @else
                                        <th>Category</th>
                                        <th>Item Description</th>
                                        <th>Part</th>
                                    @endif
                                    <th>Qty</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingRows as $i => $row)
                                    @php
                                        $hasProductError = ! empty($row['product_error']);
                                        $hasVehicleError = $isStockOut && ! empty($row['vehicle_error']);
                                        $hasError = ! empty($row['error']) || $hasProductError || $hasVehicleError;
                                    @endphp
                                    <tr class="{{ $hasError ? 'table-danger' : '' }}">
                                        <td>{{ $row['row_num'] ?? '' }}</td>
                                        @if ($isStockOut)
                                            <td>{{ $row['date'] ?? '' }}</td>
                                            <td style="min-width: 220px;">
                                                @foreach(['row_num','category','subcategory','part_no','sku','brand','quantity','unit','date','remarks','vehicle_for','for_label'] as $field)
                                                    <input type="hidden" name="pending_rows[{{ $i }}][{{ $field }}]" value="{{ $row[$field] ?? '' }}">
                                                @endforeach
                                                @if($hasVehicleError)
                                                    <select name="pending_rows[{{ $i }}][vehicle_id]" class="form-select form-select-sm is-invalid">
                                                        <option value="">— Select vehicle / machine —</option>
                                                        @foreach($vehicles as $vehicle)
                                                            <option value="{{ $vehicle->id }}" @selected((int) ($row['vehicle_id'] ?? 0) === (int) $vehicle->id)>
                                                                {{ $vehicle->displayLabel() }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div class="small text-muted mt-1">File Vehicle / Machine: {{ $row['vehicle_for'] ?? '' }}</div>
                                                @else
                                                    <input type="hidden" name="pending_rows[{{ $i }}][vehicle_id]" value="{{ $row['vehicle_id'] ?? '' }}">
                                                    <span>{{ $row['vehicle_for'] ?: ($vehicles->firstWhere('id', (int) ($row['vehicle_id'] ?? 0))?->displayLabel() ?? '') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $row['for_label'] ?? '' }}</td>
                                            <td style="min-width: 280px;">
                                        @else
                                            <td>{{ $row['category'] ?? '' }}</td>
                                            <td style="min-width: 280px;">
                                                @foreach(['row_num','category','subcategory','part_no','sku','brand','quantity','unit','date'] as $field)
                                                    <input type="hidden" name="pending_rows[{{ $i }}][{{ $field }}]" value="{{ $row[$field] ?? '' }}">
                                                @endforeach
                                        @endif
                                            @if($hasProductError)
                                                <div class="position-relative bulk-product-picker">
                                                    <input type="hidden" name="pending_rows[{{ $i }}][product_id]" value="" class="bulk-product-id">
                                                    <input type="hidden" name="pending_rows[{{ $i }}][product]" value="{{ $row['product'] ?? '' }}" class="bulk-product-value">
                                                    <input type="text"
                                                           value="{{ $row['product'] ?? '' }}"
                                                           class="form-control form-control-sm is-invalid bulk-product-search"
                                                           placeholder="Search item name or SKU..."
                                                           autocomplete="off">
                                                    <div class="small text-muted mt-1">File value: {{ $row['product'] ?? '' }}</div>
                                                </div>
                                            @else
                                                <input type="hidden" name="pending_rows[{{ $i }}][product]" value="{{ $row['product'] ?? '' }}">
                                                <input type="hidden" name="pending_rows[{{ $i }}][product_id]" value="{{ $row['product_id'] ?? '' }}">
                                                <span>{{ $row['product'] ?? '' }}</span>
                                            @endif
                                        </td>
                                        @if (! $isStockOut)
                                            <td>{{ $row['part_no'] ?? '' }}</td>
                                        @endif
                                        <td>{{ $row['quantity'] ?? '' }}</td>
                                        <td class="text-danger">{{ $row['error'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn {{ $isStockOut ? 'btn-warning' : 'btn-admin-primary' }}">
                            <i class="bi bi-check2-circle me-1"></i>{{ $isStockOut ? 'Import stock out' : 'Import stock' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <style>
            #bulk-upload-product-suggestions {
                position: fixed;
                z-index: 1080;
                display: none;
                max-height: 260px;
                overflow-y: auto;
            }
            #bulk-upload-product-suggestions.is-open { display: block; }
            #bulk-upload-product-suggestions .suggestion-item { cursor: pointer; }
            #bulk-upload-product-suggestions .suggestion-item:hover { background-color: #f8f9fa; }
        </style>
        @push('scripts')
            @include('partials.infinite-suggestions')
            @php
                $catalogProductJson = ($catalogProducts ?? collect())->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                ])->values();
            @endphp
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const products = @json($catalogProductJson);
                    const inputs = document.querySelectorAll('.bulk-product-search');
                    if (!inputs.length || typeof window.PanchmukhiInfiniteSuggestions === 'undefined') {
                        return;
                    }

                    const box = document.createElement('div');
                    box.id = 'bulk-upload-product-suggestions';
                    box.className = 'autocomplete-suggestions shadow border rounded-bottom bg-white';
                    document.body.appendChild(box);

                    let activeInput = null;
                    const infinite = window.PanchmukhiInfiniteSuggestions.create(box, {
                        pageSize: 25,
                        renderItem: function (p) {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                            div.innerHTML = '<span class="fw-semibold d-block text-truncate">' + (p.name || '') + '</span>'
                                + '<span class="text-muted small">' + (p.sku || 'No SKU') + '</span>';
                            div.addEventListener('mousedown', function (event) {
                                event.preventDefault();
                                if (!activeInput) {
                                    return;
                                }
                                const wrap = activeInput.closest('.bulk-product-picker');
                                wrap.querySelector('.bulk-product-id').value = p.id;
                                wrap.querySelector('.bulk-product-value').value = p.sku || p.name;
                                activeInput.value = p.name + (p.sku ? ' (' + p.sku + ')' : '');
                                activeInput.classList.remove('is-invalid');
                                hideSuggestions();
                            });
                            return div;
                        },
                    });

                    function hideSuggestions() {
                        infinite.reset();
                        box.classList.remove('is-open');
                        activeInput = null;
                    }

                    function positionSuggestions(input) {
                        const rect = input.getBoundingClientRect();
                        box.style.top = (rect.bottom + 2) + 'px';
                        box.style.left = rect.left + 'px';
                        box.style.width = Math.max(rect.width, 280) + 'px';
                    }

                    function showMatches(input) {
                        const term = input.value.trim().toLowerCase();
                        const matches = !term ? products.slice(0, 80) : products.filter(function (p) {
                            return (p.name || '').toLowerCase().includes(term)
                                || (p.sku || '').toLowerCase().includes(term);
                        });
                        activeInput = input;
                        positionSuggestions(input);
                        if (infinite.setResults(matches)) {
                            box.classList.add('is-open');
                        } else {
                            hideSuggestions();
                        }
                    }

                    inputs.forEach(function (input) {
                        input.addEventListener('focus', function () {
                            showMatches(input);
                        });
                        input.addEventListener('input', function () {
                            const wrap = input.closest('.bulk-product-picker');
                            wrap.querySelector('.bulk-product-id').value = '';
                            wrap.querySelector('.bulk-product-value').value = input.value;
                            showMatches(input);
                        });
                        input.addEventListener('keydown', function (event) {
                            if (event.key === 'Escape') {
                                hideSuggestions();
                            }
                        });
                    });

                    document.addEventListener('click', function (event) {
                        if (event.target.closest('.bulk-product-picker') || event.target.closest('#bulk-upload-product-suggestions')) {
                            return;
                        }
                        hideSuggestions();
                    });
                });
            </script>
        @endpush
    @endif

    <div class="row">
        <div class="col-lg-7 col-md-12 mb-4">
            <div class="admin-card">
                <div class="admin-card-body">
                    <h5 class="card-title mb-4 fw-bold text-dark">
                        <i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>
                        {{ $isStockOut ? 'Upload Stock Out CSV' : 'Upload Stock CSV' }}
                    </h5>

                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h6 class="alert-heading fw-bold mb-2">Import Validation Errors:</h6>
                            <ul class="mb-0 ps-3 small">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ $storeRoute }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-4">
                            <label for="plant_id" class="form-label fw-semibold text-secondary">
                                Select Plant <span class="text-danger">*</span>
                            </label>
                            <select name="plant_id" id="plant_id" class="form-select" required>
                                <option value="">-- Choose Plant / Warehouse --</option>
                                @foreach($plants as $p)
                                    <option value="{{ $p->id }}" @selected(old('plant_id', $selectedPlantId) == $p->id)>
                                        {{ $p->name }} ({{ $p->code }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                {{ $isStockOut ? 'Plant from which stock will be issued.' : 'Choose the plant where stock is being updated or uploaded.' }}
                            </div>
                        </div>

                        @if ($isStockOut)
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary mb-2">
                                    Stock Out Mode <span class="text-danger">*</span>
                                </label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="form-check custom-option-card border rounded p-3 bg-light">
                                            <input class="form-check-input" type="radio" name="out_mode" id="out_mode_adjust" value="adjust" @checked(old('out_mode', $pendingOutMode ?? 'adjust') === 'adjust')>
                                            <label class="form-check-label ms-2" for="out_mode_adjust">
                                                <strong class="d-block text-dark">Adjust current stock</strong>
                                                <span class="text-muted small">Deducts quantity from current stock. Upload stops if stock is short.</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check custom-option-card border rounded p-3 bg-light">
                                            <input class="form-check-input" type="radio" name="out_mode" id="out_mode_record" value="record" @checked(old('out_mode', $pendingOutMode ?? 'adjust') === 'record')>
                                            <label class="form-check-label ms-2" for="out_mode_record">
                                                <strong class="d-block text-dark">Simple upload</strong>
                                                <span class="text-muted small">Saves the stock-out rows only. Current stock is not checked or reduced.</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_create_vehicles" id="auto_create_vehicles" value="1" @checked(old('auto_create_vehicles', ($pendingAutoCreateVehicles ?? false) ? '1' : '') == '1')>
                                    <label class="form-check-label fw-semibold text-dark" for="auto_create_vehicles">
                                        Auto-create missing Vehicle / Machine
                                    </label>
                                </div>
                                <div class="form-text text-muted">
                                    If checked, a Vehicle / Machine name that is not in the list is created and used for that row.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="reason" class="form-label fw-semibold text-secondary">Notes / reason</label>
                                <textarea name="reason" id="reason" rows="2" class="form-control" placeholder="Optional reason for this stock out">{{ old('reason', $pendingReason ?? '') }}</textarea>
                            </div>
                        @else
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary mb-2">
                                    Stock Update Mode <span class="text-danger">*</span>
                                </label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="form-check custom-option-card border rounded p-3 bg-light">
                                            <input class="form-check-input" type="radio" name="update_mode" id="mode_add" value="add" @checked(old('update_mode', 'add') === 'add')>
                                            <label class="form-check-label ms-2" for="mode_add">
                                                <strong class="d-block text-dark">Add to Current Stock</strong>
                                                <span class="text-muted small">Increments existing stock quantity (Stock In).</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check custom-option-card border rounded p-3 bg-light">
                                            <input class="form-check-input" type="radio" name="update_mode" id="mode_set" value="set" @checked(old('update_mode') === 'set')>
                                            <label class="form-check-label ms-2" for="mode_set">
                                                <strong class="d-block text-dark">Set Exact Stock Balance</strong>
                                                <span class="text-muted small">Overwrites total balance (Adjusts +/- delta).</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_create_products" id="auto_create_products" value="1" @checked(old('auto_create_products', ($pendingAutoCreateProducts ?? false) ? '1' : '') == '1')>
                                    <label class="form-check-label fw-semibold text-dark" for="auto_create_products">
                                        Auto-create missing items
                                    </label>
                                </div>
                                <div class="form-text text-muted">
                                    If checked, products not found in the database will be automatically created using Category, SubCategory, Brand, and Unit from the CSV file.
                                </div>
                            </div>
                        @endif

                        <div class="mb-4">
                            <label for="csv_file" class="form-label fw-semibold text-secondary">
                                Choose Excel (.xlsx / .xls) or CSV File <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" required>
                            <div class="form-text mt-2 text-muted">
                                File can be Excel format (.xlsx / .xls) or CSV format (max 10MB). You can download a sample template below.
                                @if (! $isStockOut)
                                    A purchase sheet (Reference No, Product SKU / Name) is sent to
                                    <a href="{{ route('ho.purchases.bulk-upload') }}">Purchases → Bulk Upload</a>,
                                    where wrong item names can be edited before import.
                                @endif
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
                            <a href="{{ $sampleRoute }}" id="download_sample_btn" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download me-1"></i>Download Sample CSV
                            </a>
                            <button type="submit" class="btn {{ $isStockOut ? 'btn-warning' : 'btn-admin-primary' }}">
                                <i class="bi bi-upload me-1"></i>{{ $isStockOut ? 'Upload & Issue Stock' : 'Upload & Process Stock' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5 col-md-12">
            <div class="admin-card bg-light border-0 mb-4">
                <div class="admin-card-body">
                    <h5 class="card-title mb-3 text-dark fw-bold">
                        <i class="bi bi-info-circle me-1 text-info"></i>Supported CSV Format
                    </h5>
                    <p class="text-secondary small mb-3">
                        @if ($isStockOut)
                            Qty is the <strong>issue quantity</strong> to subtract from current stock.
                            Each row has <strong>Vehicle / Machine</strong> (same as Stock Out) and <strong>FOR</strong>.
                            Leave Vehicle / Machine empty for other / plant use — no vehicle check.
                            If it has a value, it must match the Vehicles list.
                            If a machine or product is missing, you can fix it on this page before import.
                        @else
                            Your CSV file can directly use your standard spreadsheet column headers. For historical FY uploads, an optional <strong>Date</strong> column is supported and will be used as the stock movement date.
                        @endif
                    </p>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered bg-white small mb-0">
                            @if ($isStockOut)
                                <thead class="table-primary text-white">
                                    <tr>
                                        <th>MONTH</th>
                                        <th>DATE</th>
                                        <th>Vehicle / Machine</th>
                                        <th>FOR</th>
                                        <th>ITEM TYPE</th>
                                        <th>NAME OF PARTS</th>
                                        <th>QTY</th>
                                        <th>UNIT</th>
                                        <th>RATE</th>
                                        <th>AMOUNT</th>
                                        <th>REMARKS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Apr-26</td>
                                        <td>1-04-2026</td>
                                        <td>CONVEYOR</td>
                                        <td>CONVEYOR</td>
                                        <td>BEARING</td>
                                        <td>BEARING (6206)</td>
                                        <td>1</td>
                                        <td>NOS</td>
                                        <td>675</td>
                                        <td>675</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td>Apr-26</td>
                                        <td>2-04-2026</td>
                                        <td>LOADER</td>
                                        <td>LOADER</td>
                                        <td>OIL</td>
                                        <td>GEAR OIL</td>
                                        <td>10</td>
                                        <td>LTR</td>
                                        <td>202.96</td>
                                        <td>2029.6</td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            @else
                                <thead class="table-primary text-white">
                                    <tr>
                                        <th>Cat.</th>
                                        <th>Item Type</th>
                                        <th>Item Description</th>
                                        <th>Part Number</th>
                                        <th>COMPANY NAME</th>
                                        <th>Qty</th>
                                        <th>UNIT</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Hyva</td>
                                        <td>Valve</td>
                                        <td>Dual Brake Valve</td>
                                        <td>601130</td>
                                        <td>TATA</td>
                                        <td>2</td>
                                        <td>NOS</td>
                                        <td>2024-04-15</td>
                                    </tr>
                                    <tr>
                                        <td>Hyva</td>
                                        <td>Bearing</td>
                                        <td>Bearing (33111)</td>
                                        <td>33111 / 3107</td>
                                        <td>TATA</td>
                                        <td>3</td>
                                        <td>NOS</td>
                                        <td>2024-05-10</td>
                                    </tr>
                                </tbody>
                            @endif
                        </table>
                    </div>

                    <h6 class="fw-bold text-dark mb-2 small">Column Details:</h6>
                    <ul class="text-secondary small ps-3 mb-3">
                        @if ($isStockOut)
                            <li class="mb-1"><strong>MONTH</strong>: Optional (ignored — taken from DATE).</li>
                            <li class="mb-1"><strong>DATE</strong>: Issue date per row (e.g. <em>1-04-2026</em>).</li>
                            <li class="mb-1"><strong>Vehicle / Machine</strong>: Optional, same as Stock Out. Leave empty for other / plant use (no check). If filled, the name must exist in the Vehicles list.</li>
                            <li class="mb-1"><strong>FOR</strong>: Optional register value on the same row. Not checked against the Vehicles list.</li>
                            <li class="mb-1"><strong>ITEM TYPE</strong>: Optional filter when two products share the same name.</li>
                            <li class="mb-1"><strong>NAME OF PARTS</strong> <span class="text-danger">*</span>: Product name in the catalog (e.g. <em>BEARING (6206)</em>).</li>
                            <li class="mb-1"><strong>QTY</strong> <span class="text-danger">*</span>: Quantity to issue (must be greater than 0).</li>
                            <li class="mb-1"><strong>UNIT</strong>: Unit code (e.g. <em>NOS</em>, <em>LTR</em>, <em>SET</em>).</li>
                            <li class="mb-1"><strong>RATE / AMOUNT</strong>: Optional; not applied to stock.</li>
                            <li class="mb-1"><strong>REMARKS</strong>: Optional reason / note for that row.</li>
                        @else
                            <li class="mb-1"><strong>Cat. (Category)</strong>: Category name (e.g. <em>Hyva</em>, <em>JCB 3DX</em>, <em>General</em>).</li>
                            <li class="mb-1"><strong>Item Type (SubCategory)</strong>: Item type / subcategory (e.g. <em>Valve</em>, <em>Bearing</em>, <em>Light</em>).</li>
                            <li class="mb-1"><strong>Item Description</strong> <span class="text-danger">*</span>: Full product name (e.g. <em>Dual Brake Valve</em>).</li>
                            <li class="mb-1"><strong>Part Number</strong>: Size, dimension, or part number code.</li>
                            <li class="mb-1"><strong>COMPANY NAME</strong>: Brand name (e.g. <em>TATA</em>, <em>HELLA</em>, <em>GOLD</em>).</li>
                            <li class="mb-1"><strong>Qty</strong> <span class="text-danger">*</span>: Stock quantity (must be a positive number).</li>
                            <li class="mb-1"><strong>UNIT</strong>: Unit code (e.g. <em>NOS</em>, <em>SET</em>, <em>PKT</em>, <em>KG</em>).</li>
                        @endif
                    </ul>

                    <div class="alert alert-info small mb-0 py-2 border-0">
                        <i class="bi bi-lightbulb me-1"></i>
                        @if ($isStockOut)
                            <strong>Tip:</strong> Same as Stock Out: empty Vehicle / Machine is allowed. A filled name must exist in the Vehicles list. FOR is only extra row text.
                        @else
                            <strong>Tip:</strong> Select a plant from the dropdown before clicking <em>Download Sample CSV</em> to export existing items for that plant!
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const plantSelect = document.getElementById('plant_id');
    const sampleBtn = document.getElementById('download_sample_btn');
    const baseUrl = @json($sampleRoute);

    function updateSampleUrl() {
        const plantId = plantSelect ? plantSelect.value : '';
        if (plantId) {
            sampleBtn.href = baseUrl + '?plant_id=' + encodeURIComponent(plantId);
        } else {
            sampleBtn.href = baseUrl;
        }
    }

    if (plantSelect && sampleBtn) {
        plantSelect.addEventListener('change', updateSampleUrl);
        updateSampleUrl();
    }
});
</script>
@endpush
