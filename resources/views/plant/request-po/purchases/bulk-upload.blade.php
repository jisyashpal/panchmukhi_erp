@extends($portalLayout ?? 'layouts.plant')
@section('title', 'Bulk Upload Purchases')
@section('content')
    @php
        $isHo = request()->is('ho*');
        $backRoute = $isHo ? route('ho.purchases.index') : route('plant.purchases.index');
        $storeRoute = $isHo ? route('ho.purchases.bulk-upload.store') : route('plant.purchases.bulk-upload.store');
        $sampleRoute = $isHo ? route('ho.purchases.bulk-upload.sample') : route('plant.purchases.bulk-upload.sample');
    @endphp

    <x-admin-page-header title="Bulk Upload Purchases" subtitle="Upload bulk Purchase Orders / Entries via CSV or Excel file">
        <a href="{{ $backRoute }}" class="btn btn-admin-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Purchases
        </a>
    </x-admin-page-header>

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
                                    <input class="form-check-input" type="checkbox" name="auto_create_suppliers" id="auto_create_suppliers" value="1" @checked(old('auto_create_suppliers', '1') == '1')>
                                    <label class="form-check-label fw-bold text-dark" for="auto_create_suppliers">
                                        Auto-Create Missing Suppliers
                                    </label>
                                </div>
                                <div class="form-text text-muted small ms-4">
                                    If checked, supplier names in the CSV that do not exist in the database will be created automatically.
                                </div>
                            </div>
                        </div>

                        {{-- File Input --}}
                        <div class="mb-4">
                            <label for="csv_file" class="form-label fw-semibold text-secondary">
                                Choose Excel (.xlsx / .xls) or CSV File <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" required>
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
                                    <td>Supplier name or code</td>
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
                                    <td><code>Is Interstate</code></td>
                                    <td><span class="badge bg-secondary">Opt</span></td>
                                    <td>1 for IGST, 0 for CGST+SGST</td>
                                </tr>
                                <tr>
                                    <td><code>Product SKU / Name</code></td>
                                    <td><span class="badge bg-danger">Required</span></td>
                                    <td>Item SKU or exact product name</td>
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
                        <strong>Tip:</strong> Download the sample file to see the recommended header structure and format.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
