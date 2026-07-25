@extends('layouts.ho')
@section('title', 'Import Products')
@section('content')
    <x-admin-page-header title="Import Products" subtitle="Bulk import products using a CSV file">
        <a href="{{ route('ho.products.create') }}" class="btn btn-admin-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </x-admin-page-header>

    <div class="row">
        <div class="col-md-8">
            <div class="admin-card mb-4">
                <div class="admin-card-body">
                    <h5 class="card-title mb-4">Upload CSV File</h5>

                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h6 class="alert-heading fw-bold mb-2">Import Failed / Validation Errors:</h6>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ route('ho.products.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label for="csv_file" class="form-label fw-semibold text-secondary">Choose CSV File</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,text/csv" required>
                            <div class="form-text mt-2 text-muted">
                                Please ensure the file size is under 4MB and matches the format of the downloadable template.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('ho.products.import-template') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download me-1"></i>Download Format Template
                            </a>
                            <button type="submit" class="btn btn-admin-primary">
                                <i class="bi bi-upload me-1"></i>Start Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="admin-card bg-light border-0">
                <div class="admin-card-body">
                    <h5 class="card-title mb-3 text-dark fw-bold">Instructions & Format</h5>
                    <p class="text-secondary small">
                        To successfully import products, your CSV file must contain the following headers and rules:
                    </p>
                    <hr>
                    <ul class="text-secondary small ps-3 mb-0">
                        <li class="mb-2"><strong>Category</strong> <span class="text-danger">*</span>: The name of the category (e.g., <em>HYVA</em>). Created if not exists.</li>
                        <li class="mb-2"><strong>SubCategory</strong>: The name of the item type subcategory (e.g., <em>BOLT</em>). Created if not exists.</li>
                        <li class="mb-2"><strong>Product Name</strong> <span class="text-danger">*</span>: The full part name/item name (e.g., <em>BOLT NUT 12X40</em>).</li>
                        <li class="mb-2"><strong>Size/Part</strong>: Any size, dimension, or part number specification (e.g., <em>12X40</em>).</li>
                        <li class="mb-2"><strong>Unit</strong> <span class="text-danger">*</span>: Standard unit code (e.g., <em>nos</em>, <em>kg</em>, <em>L</em>, <em>set</em>). Automatically created if missing.</li>
                        <li class="mb-2"><strong>Brand</strong>: Brand name (defaults to <em>Generic</em> if blank).</li>
                        <li class="mb-2"><strong>Reorder Level</strong>: Minimum quantity count to trigger low-stock alerts (defaults to <em>10</em>).</li>
                    </ul>
                    <div class="alert alert-warning small mt-3 mb-0 py-2 border-0">
                        <i class="bi bi-exclamation-triangle me-1"></i> <strong>SKU Generation:</strong> The system automatically creates clean SKUs in the format <code>Category_SubCategory_Size_Brand_ItemName</code> (first 3 of brand, last 3 of name) and ensures they are unique.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
