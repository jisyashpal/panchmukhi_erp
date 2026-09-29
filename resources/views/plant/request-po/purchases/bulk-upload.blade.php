@extends($portalLayout ?? 'layouts.plant')
@section('title', 'Bulk Upload Purchases')
@section('content')
    @php
        $isHo = request()->routeIs('ho.*');
        $backRoute = $isHo ? route('ho.purchases.index') : route('plant.purchases.index');
        $storeRoute = $isHo ? route('ho.purchases.bulk-upload.store') : route('plant.purchases.bulk-upload.store');
        $sampleRoute = $isHo ? route('ho.purchases.bulk-upload.sample') : route('plant.purchases.bulk-upload.sample');
    @endphp

    <x-admin-page-header title="Bulk Upload Purchases" subtitle="Upload bulk Purchase Orders / Entries via CSV or Excel file">
        <a href="{{ $backRoute }}" class="btn btn-admin-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Purchases
        </a>
    </x-admin-page-header>

    @php
        $pendingRows = $pendingRows ?? [];
        $hasPending = count($pendingRows) > 0;
        $pendingErrorCount = collect($pendingRows)->filter(fn ($row) => ! empty($row['error']))->count();
    @endphp

    @if ($hasPending)
        <div class="admin-card mb-4">
            <div class="admin-card-body">
                <h5 class="card-title mb-2 fw-bold text-dark">
                    <i class="bi bi-pencil-square me-2 text-warning"></i>Fix product and supplier names before import
                </h5>
                <p class="text-muted small mb-3">
                    Nothing was saved. {{ $pendingErrorCount }} of {{ count($pendingRows) }} row(s) have errors.
                    Error rows: <strong>search and select</strong> the catalog product and existing supplier, then import the full file.
                </p>

                <form action="{{ $storeRoute }}" method="POST">
                    @csrf
                    <input type="hidden" name="plant_id" value="{{ old('plant_id', $selectedPlantId) }}">
                    @if($pendingAutoReceive ?? false)
                        <input type="hidden" name="auto_receive" value="1">
                    @endif
                    @if($pendingAutoCreateSuppliers ?? false)
                        <input type="hidden" name="auto_create_suppliers" value="1">
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle small mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Row</th>
                                    <th>PO / Ref</th>
                                    <th>Supplier</th>
                                    <th>Product SKU / Name</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingRows as $i => $row)
                                    @php
                                        $hasProductError = ! empty($row['product_error']);
                                        $hasSupplierError = ! empty($row['supplier_error']);
                                        $hasError = ! empty($row['error']) || $hasProductError || $hasSupplierError;
                                    @endphp
                                    <tr class="{{ $hasError ? 'table-danger' : '' }}">
                                        <td>{{ $row['row_num'] ?? '' }}</td>
                                        <td>{{ $row['reference_no'] ?? '' }}</td>
                                        <td style="min-width: 220px;">
                                            @if($hasSupplierError)
                                                <div class="position-relative bulk-supplier-picker">
                                                    <input type="hidden" name="pending_rows[{{ $i }}][supplier_id]" value="" class="bulk-supplier-id">
                                                    <input type="hidden" name="pending_rows[{{ $i }}][supplier]" value="{{ $row['supplier'] ?? '' }}" class="bulk-supplier-value">
                                                    <input type="text"
                                                           value="{{ $row['supplier'] ?? '' }}"
                                                           class="form-control form-control-sm is-invalid bulk-supplier-search"
                                                           placeholder="Search existing supplier..."
                                                           autocomplete="off">
                                                    <div class="small text-muted mt-1">File value: {{ $row['supplier'] ?? '' }}</div>
                                                </div>
                                            @else
                                                <input type="hidden" name="pending_rows[{{ $i }}][supplier]" value="{{ $row['supplier'] ?? '' }}">
                                                <input type="hidden" name="pending_rows[{{ $i }}][supplier_id]" value="{{ $row['supplier_id'] ?? '' }}">
                                                <span>{{ $row['supplier'] ?? '' }}</span>
                                            @endif
                                        </td>
                                        <td style="min-width: 280px;">
                                            @foreach(['row_num','reference_no','plant','document_type','order_date','invoice_no','invoice_date','interstate','quantity','unit_price','discount','gst_rate','notes'] as $field)
                                                <input type="hidden" name="pending_rows[{{ $i }}][{{ $field }}]" value="{{ $row[$field] ?? '' }}">
                                            @endforeach
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
                                        <td>{{ $row['quantity'] ?? '' }}</td>
                                        <td>{{ $row['unit_price'] ?? '' }}</td>
                                        <td class="text-danger">{{ $row['error'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-admin-primary">
                            <i class="bi bi-check2-circle me-1"></i>Import purchases
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <style>
            #bulk-upload-product-suggestions,
            #bulk-upload-supplier-suggestions {
                position: fixed;
                z-index: 1080;
                display: none;
                max-height: 260px;
                overflow-y: auto;
            }
            #bulk-upload-product-suggestions.is-open,
            #bulk-upload-supplier-suggestions.is-open { display: block; }
            #bulk-upload-product-suggestions .suggestion-item,
            #bulk-upload-supplier-suggestions .suggestion-item { cursor: pointer; }
            #bulk-upload-product-suggestions .suggestion-item:hover,
            #bulk-upload-supplier-suggestions .suggestion-item:hover { background-color: #f8f9fa; }
        </style>
        @push('scripts')
            @include('partials.infinite-suggestions')
            @php
                $catalogProductJson = ($catalogProducts ?? collect())->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'category' => $p->category?->name ?? '',
                    'item_type' => $p->subCategory?->name ?? '',
                ])->values();
                $catalogSupplierJson = ($catalogSuppliers ?? collect())->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'phone' => $s->phone,
                    'gstin' => $s->gstin,
                ])->values();
            @endphp
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof window.PanchmukhiInfiniteSuggestions === 'undefined') {
                        return;
                    }

                    function bindPicker(options) {
                        const inputs = document.querySelectorAll(options.inputSelector);
                        if (!inputs.length) {
                            return;
                        }

                        const box = document.createElement('div');
                        box.id = options.boxId;
                        box.className = 'autocomplete-suggestions shadow border rounded-bottom bg-white';
                        document.body.appendChild(box);

                        let activeInput = null;
                        const infinite = window.PanchmukhiInfiniteSuggestions.create(box, {
                            pageSize: 25,
                            itemLabel: options.itemLabel || 'product',
                            renderItem: function (item) {
                                const div = document.createElement('div');
                                div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                                div.innerHTML = options.renderHtml(item);
                                div.addEventListener('mousedown', function (event) {
                                    event.preventDefault();
                                    if (!activeInput) {
                                        return;
                                    }
                                    options.applySelection(activeInput, item);
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
                            const matches = options.filter(input.value.trim().toLowerCase());
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
                                options.onInput(input);
                                showMatches(input);
                            });
                            input.addEventListener('keydown', function (event) {
                                if (event.key === 'Escape') {
                                    hideSuggestions();
                                }
                            });
                        });

                        document.addEventListener('click', function (event) {
                            if (event.target.closest(options.wrapSelector) || event.target.closest('#' + options.boxId)) {
                                return;
                            }
                            hideSuggestions();
                        });

                        window.addEventListener('scroll', function () {
                            if (activeInput && box.classList.contains('is-open')) {
                                positionSuggestions(activeInput);
                            }
                        }, true);
                    }

                    const products = @json($catalogProductJson);
                    bindPicker({
                        inputSelector: '.bulk-product-search',
                        wrapSelector: '.bulk-product-picker',
                        boxId: 'bulk-upload-product-suggestions',
                        filter: function (term) {
                            return !term ? products.slice(0, 80) : products.filter(function (p) {
                                return (p.name || '').toLowerCase().includes(term)
                                    || (p.sku || '').toLowerCase().includes(term);
                            });
                        },
                        renderHtml: function (p) {
                            return '<span class="fw-semibold d-block text-truncate">' + (p.name || '') + '</span>'
                                + '<span class="text-muted small">' + ((p.category || 'No category') + ' / ' + (p.item_type || 'No item type') + ' · ' + (p.sku || 'No SKU')) + '</span>';
                        },
                        applySelection: function (input, p) {
                            const wrap = input.closest('.bulk-product-picker');
                            wrap.querySelector('.bulk-product-id').value = p.id;
                            wrap.querySelector('.bulk-product-value').value = p.sku || p.name;
                            input.value = p.name + (p.sku ? ' (' + p.sku + ')' : '');
                        },
                        onInput: function (input) {
                            const wrap = input.closest('.bulk-product-picker');
                            wrap.querySelector('.bulk-product-id').value = '';
                            wrap.querySelector('.bulk-product-value').value = input.value;
                        },
                    });

                    const suppliers = @json($catalogSupplierJson);
                    bindPicker({
                        inputSelector: '.bulk-supplier-search',
                        wrapSelector: '.bulk-supplier-picker',
                        boxId: 'bulk-upload-supplier-suggestions',
                        itemLabel: 'supplier',
                        filter: function (term) {
                            return !term ? suppliers.slice(0, 80) : suppliers.filter(function (s) {
                                return (s.name || '').toLowerCase().includes(term)
                                    || (s.phone || '').toLowerCase().includes(term)
                                    || (s.gstin || '').toLowerCase().includes(term);
                            });
                        },
                        renderHtml: function (s) {
                            const meta = [s.gstin ? ('GSTIN: ' + s.gstin) : '', s.phone || ''].filter(Boolean).join(' · ');
                            return '<span class="fw-semibold d-block text-truncate">' + (s.name || '') + '</span>'
                                + '<span class="text-muted small">' + (meta || 'Existing supplier') + '</span>';
                        },
                        applySelection: function (input, s) {
                            const wrap = input.closest('.bulk-supplier-picker');
                            wrap.querySelector('.bulk-supplier-id').value = s.id;
                            wrap.querySelector('.bulk-supplier-value').value = s.name;
                            input.value = s.name;
                        },
                        onInput: function (input) {
                            const wrap = input.closest('.bulk-supplier-picker');
                            wrap.querySelector('.bulk-supplier-id').value = '';
                            wrap.querySelector('.bulk-supplier-value').value = input.value;
                        },
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
                        <i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i>Upload Purchase File
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

                    @if (session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('warning') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ $storeRoute }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        {{-- Plant Selector --}}
                        <div class="mb-4">
                            <label for="plant_id" class="form-label fw-semibold text-secondary">
                                Default Plant / Site
                            </label>
                            <select name="plant_id" id="plant_id" class="form-select">
                                <option value="">-- Auto-detect from CSV or Select Plant --</option>
                                @foreach($plants as $p)
                                    <option value="{{ $p->id }}" @selected(old('plant_id', $selectedPlantId) == $p->id)>
                                        {{ $p->name }} ({{ $p->code }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted">
                                If your file has a <code>Plant Code / Name</code> column, it will override this selection.
                            </div>
                        </div>

                        {{-- Options --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-secondary mb-2">Upload Settings</label>
                            
                            <div class="card bg-light border-0 p-3 mb-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="auto_receive" id="auto_receive" value="1" @checked(old('auto_receive') == '1')>
                                    <label class="form-check-label fw-bold text-dark" for="auto_receive">
                                        Auto-Receive Stock into Inventory
                                    </label>
                                </div>
                                <div class="form-text text-muted small ms-4">
                                    When enabled, purchases will be automatically marked as <strong>Delivered / Received</strong> and stock balances will be credited to the plant.
                                </div>
                            </div>

                            <div class="card bg-light border-0 p-3">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="auto_create_suppliers" id="auto_create_suppliers" value="1" @checked(old('auto_create_suppliers'))>
                                    <label class="form-check-label fw-bold text-dark" for="auto_create_suppliers">
                                        Auto-Create Missing Suppliers
                                    </label>
                                </div>
                                <div class="form-text text-muted small ms-4">
                                    Leave unchecked to reuse existing suppliers (recommended). Unknown names are highlighted so you can pick the correct catalog supplier. Check this only when you want a new supplier record created.
                                </div>
                            </div>
                        </div>

                        {{-- File Input --}}
                        <div class="mb-4">
                            <label for="csv_file" class="form-label fw-semibold text-secondary">
                                Choose Excel (.xlsx / .xls) or CSV File <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" @required(! $hasPending)>
                            <div class="form-text mt-2 text-muted">
                                Supported formats: <code>.xlsx</code>, <code>.xls</code>, <code>.csv</code> (Max size: 10MB).
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex align-items-center justify-content-between pt-2">
                            <a href="{{ $sampleRoute }}" class="btn btn-outline-success btn-sm">
                                <i class="bi bi-download me-1"></i>Download Sample CSV
                            </a>
                            <button type="submit" class="btn btn-admin-primary">
                                <i class="bi bi-cloud-upload me-1"></i>Upload & Import Purchases
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Help / Instructions Side Column --}}
        <div class="col-lg-5 col-md-12">
            <div class="admin-card">
                <div class="admin-card-body">
                    <h5 class="card-title mb-3 fw-bold text-dark">
                        <i class="bi bi-info-circle me-2 text-info"></i>CSV Column Guide
                    </h5>

                    <p class="text-muted small">
                        Rows with the same <strong>Reference No</strong> (or Supplier Invoice No) will be automatically grouped into a single Purchase Order with multiple item lines.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle small">
                            <thead class="table-light">
                                <tr>
                                    <th>Column Name</th>
                                    <th>Req?</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>Reference No</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>PO Number (auto-generated if blank)</td>
                                </tr>
                                <tr>
                                    <td><code>Plant Code / Name</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>Target site (uses dropdown if blank)</td>
                                </tr>
                                <tr>
                                    <td><code>Supplier Name</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>Must match an existing supplier (spaces/punctuation ignored). Unknown names are shown as errors so you can pick from catalog.</td>
                                </tr>
                                <tr>
                                    <td><code>Document Type</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td><code>purchase_order</code> or quotation</td>
                                </tr>
                                <tr>
                                    <td><code>Order Date</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>YYYY-MM-DD (defaults to today)</td>
                                </tr>
                                <tr>
                                    <td><code>Supplier Invoice No</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>Supplier bill / invoice number</td>
                                </tr>
                                <tr>
                                    <td><code>Supplier Invoice Date</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>YYYY-MM-DD</td>
                                </tr>
                                <tr>
                                    <td><code>Is Interstate (1/0)</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>1 for IGST, 0 for CGST+SGST</td>
                                </tr>
                                <tr>
                                    <td><code>Product SKU / Name</code></td>
                                    <td><span class="badge bg-danger">Required</span></td>
                                    <td>SKU <strong>or</strong> exact product name. Combined <code>SKU / Name</code> also works if both match the same catalog item.</td>
                                </tr>
                                <tr>
                                    <td><code>Quantity</code></td>
                                    <td><span class="badge bg-danger">Required</span></td>
                                    <td>Purchase item quantity</td>
                                </tr>
                                <tr>
                                    <td><code>Unit Price</code></td>
                                    <td><span class="badge bg-danger">Required</span></td>
                                    <td>Price per unit</td>
                                </tr>
                                <tr>
                                    <td><code>Discount %</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>Percentage discount (0-100)</td>
                                </tr>
                                <tr>
                                    <td><code>GST Rate %</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>GST percentage e.g. 18, 12, 5</td>
                                </tr>
                                <tr>
                                    <td><code>Notes</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>Purchase notes or remarks</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-light border small mt-3 mb-0">
                        <i class="bi bi-lightbulb text-warning me-1"></i>
                        <strong>Tip:</strong> Keep sample headers unchanged. Product and supplier names must match the catalog (extra spaces and punctuation ignored). If a row does not match, nothing is saved until you pick the correct catalog value.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
