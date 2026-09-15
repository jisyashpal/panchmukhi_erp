@extends($portalLayout ?? 'layouts.ho')
@section('title', 'Bulk Upload Stock')
@section('content')
    <x-admin-page-header title="Bulk Upload Stock" subtitle="Select plant and upload CSV file to update stock balances">
        <a href="{{ route('ho.stocks.index') }}" class="btn btn-admin-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Stocks
        </a>
    </x-admin-page-header>

    <div class="row">
        <div class="col-lg-7 col-md-12 mb-4">
            <div class="admin-card">
                <div class="admin-card-body">
                    <h5 class="card-title mb-4 fw-bold text-dark">
                        <i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Upload Stock CSV
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

                    <form action="{{ route('ho.stocks.bulk-upload.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        {{-- Plant Selector --}}
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
                            <div class="form-text">Choose the plant where stock is being updated or uploaded.</div>
                        </div>

                        {{-- Update Mode --}}
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

                        {{-- Auto-create products checkbox --}}
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_create_products" id="auto_create_products" value="1" @checked(old('auto_create_products', '1') == '1')>
                                <label class="form-check-label fw-semibold text-dark" for="auto_create_products">
                                    Auto-create missing items
                                </label>
                            </div>
                            <div class="form-text text-muted">
                                If checked, products not found in the database will be automatically created using Category, SubCategory, Brand, and Unit from the CSV file.
                            </div>
                        </div>

                        {{-- File Input --}}
                        <div class="mb-4">
                            <label for="csv_file" class="form-label fw-semibold text-secondary">
                                Choose Excel (.xlsx / .xls) or CSV File <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" required>
                            <div class="form-text mt-2 text-muted">
                                File can be Excel format (.xlsx / .xls) or CSV format (max 10MB). You can download a sample template below.
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
                            <a href="{{ route('ho.stocks.bulk-upload.sample') }}" id="download_sample_btn" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download me-1"></i>Download Sample CSV
                            </a>
                            <button type="submit" class="btn btn-admin-primary">
                                <i class="bi bi-upload me-1"></i>Upload & Process Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Format & Instructions Panel --}}
        <div class="col-lg-5 col-md-12">
            <div class="admin-card bg-light border-0 mb-4">
                <div class="admin-card-body">
                    <h5 class="card-title mb-3 text-dark fw-bold">
                        <i class="bi bi-info-circle me-1 text-info"></i>Supported CSV Format
                    </h5>
                    <p class="text-secondary small mb-3">
                        Your CSV file can directly use your standard spreadsheet column headers. For historical FY uploads, an optional <strong>Date</strong> column is supported and will be used as the stock movement date.
                    </p>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered bg-white small mb-0">
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
                                <tr>
                                    <td>General</td>
                                    <td>Tape</td>
                                    <td>Black Tape</td>
                                    <td>6013</td>
                                    <td>GENERIC</td>
                                    <td>30</td>
                                    <td>NOS</td>
                                    <td>2024-06-02</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="fw-bold text-dark mb-2 small">Column Details:</h6>
                    <ul class="text-secondary small ps-3 mb-3">
                        <li class="mb-1"><strong>Cat. (Category)</strong>: Category name (e.g. <em>Hyva</em>, <em>JCB 3DX</em>, <em>General</em>).</li>
                        <li class="mb-1"><strong>Item Type (SubCategory)</strong>: Item type / subcategory (e.g. <em>Valve</em>, <em>Bearing</em>, <em>Light</em>).</li>
                        <li class="mb-1"><strong>Item Description</strong> <span class="text-danger">*</span>: Full product name (e.g. <em>Dual Brake Valve</em>).</li>
                        <li class="mb-1"><strong>Part Number</strong>: Size, dimension, or part number code.</li>
                        <li class="mb-1"><strong>COMPANY NAME</strong>: Brand name (e.g. <em>TATA</em>, <em>HELLA</em>, <em>GOLD</em>).</li>
                        <li class="mb-1"><strong>Qty</strong> <span class="text-danger">*</span>: Stock quantity (must be a positive number).</li>
                        <li class="mb-1"><strong>UNIT</strong>: Unit code (e.g. <em>NOS</em>, <em>SET</em>, <em>PKT</em>, <em>KG</em>).</li>
                    </ul>

                    <div class="alert alert-info small mb-0 py-2 border-0">
                        <i class="bi bi-lightbulb me-1"></i> <strong>Tip:</strong> Select a plant from the dropdown before clicking <em>Download Sample CSV</em> to export existing items for that plant!
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
    const baseUrl = "{{ route('ho.stocks.bulk-upload.sample') }}";

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
