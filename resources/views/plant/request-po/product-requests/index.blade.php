@extends('layouts.plant')
@section('title', 'Product Requests')
@section('content')
    @php
        $activeStatus = $activeStatus ?? request('status');
    @endphp
    <x-admin-page-header title="Product Requests" :subtitle="$isPurchaser ? 'Product-wise list — select same plant to export or add quotation' : 'Request products for your plant'">
        @if(auth()->user()->canCreateProductRequests())
            <a href="{{ route('plant.product-requests.create') }}" class="btn btn-admin-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>New Request
            </a>
        @endif
    </x-admin-page-header>

    <div class="admin-filters mb-3">
        @include('partials.status-tabs', [
            'route' => 'plant.product-requests.index',
            'param' => 'status',
            'tabs' => [
                ['label' => 'PENDING', 'key' => 'pending'],
                ['label' => 'Add Quotation', 'key' => 'add_quotation'],
                ['label' => 'Create PO', 'key' => 'create_po'],
                ['label' => 'Dispatch', 'key' => 'dispatch'],
                ['label' => 'All', 'key' => 'all'],
            ],
            'active' => $activeStatus === '' ? 'all' : $activeStatus,
        ])
        <form method="GET" class="row g-3 mb-3 align-items-end">
            @if($activeStatus !== null && $activeStatus !== '')
                <input type="hidden" name="status" value="{{ $activeStatus }}">
            @elseif(request('status') === 'all')
                <input type="hidden" name="status" value="all">
            @endif
            @if($isPurchaser)
                <div class="col-12 col-md-2">
                    <label class="form-label small">Plant / Site</label>
                    <select name="plant_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All plants</option>
                        @foreach($plants as $plant)
                            <option value="{{ $plant->id }}" @selected(request('plant_id') == $plant->id)>{{ $plant->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-3 position-relative">
                <label class="form-label small" for="product-search">Search Item Name / SKU</label>
                <input type="text" name="search" id="product-search" value="{{ request('search') }}"
                    class="form-control form-control-sm" placeholder="Enter item name or SKU..." autocomplete="off">
                <div id="autocomplete-suggestions"
                    class="autocomplete-suggestions d-none shadow border rounded-bottom bg-white"></div>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small">Filter by Product</label>
                <select name="product_id" id="product-id-filter" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All products</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }} — {{ $p->category?->name ?? 'No category' }} / {{ $p->subCategory?->name ?? 'No item type' }} ({{ $p->sku }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small">Priority</label>
                <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All priorities</option>
                    @foreach(\App\Enums\PriorityLevel::cases() as $pLevel)
                        <option value="{{ $pLevel->value }}" @selected(request('priority') === $pLevel->value)>
                            {{ $pLevel->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm"
                    onchange="this.form.submit()">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm"
                    onchange="this.form.submit()">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-admin-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
                @if(request()->anyFilled(['plant_id', 'product_id', 'search', 'priority', 'status', 'from_date', 'to_date']))
                    <a href="{{ route('plant.product-requests.index') }}" class="btn btn-admin-outline btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>

    @if($isPurchaser)
        <form method="POST" action="{{ route('plant.product-requests.bulk-convert') }}" id="bulk-quotation-form">
            @csrf
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 sticky-selection-bar">
                <div class="d-inline-flex align-items-center gap-1">
                    <select name="supplier_id" class="form-select form-select-sm" id="export-supplier-select" style="min-width: 210px;" title="Select Supplier for Inquiry Export">
                        <option value="">-- Select Supplier * --</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }} {{ $supplier->city ? '('.$supplier->city.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-danger btn-sm" id="bulk-export-btn"
                    formaction="{{ route('plant.product-requests.bulk-export-supplier') }}" disabled>
                    <i class="bi bi-file-earmark-excel me-1"></i>Export for Supplier
                </button>
                @if($activeStatus !== 'pending')
                    <button type="submit" class="btn btn-warning btn-sm" id="bulk-generate-btn" disabled>
                        <i class="bi bi-file-earmark-text me-1"></i>Add Quotation (selected)
                    </button>
                @endif
                <span class="small text-muted" id="bulk-selection-hint">
                    @if($activeStatus === 'pending')
                        Select products to export for supplier. Exporting moves items to Add Quotation tab.
                    @else
                        Select products. Export for supplier first, then add quotation. Same plant only.
                    @endif
                </span>
            </div>
    @endif

        @php
            $isQuotationPoView = in_array($activeStatus, ['add_quotation', 'create_po', 'dispatch']);
        @endphp

        <div class="admin-card admin-table-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        @if($isQuotationPoView)
                            <tr>
                                <th>{{ $activeStatus === 'dispatch' ? 'PO No.' : ($activeStatus === 'create_po' ? 'Quotation No.' : 'Inquiry / Ref No.') }}</th>
                                <th>Products</th>
                                <th>Supplier Name</th>
                                <th>Date of Request</th>
                                <th>Site (Plant)</th>
                                <th>{{ $activeStatus === 'add_quotation' ? 'Export Date' : 'Create Date' }}</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        @else
                            <tr>
                                @if($isPurchaser)
                                    <th style="width: 2.5rem">
                                        <input type="checkbox" class="form-check-input" id="select-all-requests"
                                            title="Select all eligible products">
                                    </th>
                                @endif
                                <th>#</th>
                                @if($isPurchaser)
                                    <th>Plant</th>
                                @endif
                                <th>Product</th>
                                <th>Size</th>
                                <th class="text-end">Qty</th>
                                <th>Unit</th>
                                {{-- <th>Requested By</th> --}}
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Vech No / Mac No</th>
                                <th>Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        @if(in_array($activeStatus, ['add_quotation', 'create_po', 'dispatch']))
                            @php
                                $groupedItems = $items->getCollection()->groupBy(function($item) use ($activeStatus) {
                                    $quotations = $item->relatedQuotations();
                                    $purchaseOrders = $item->relatedPurchaseOrders();
                                    $primaryQuotation = $quotations->first();
                                    $primaryPurchase = $purchaseOrders->first();

                                    if ($activeStatus === 'dispatch' && $primaryPurchase) {
                                        return 'po_' . $primaryPurchase->id;
                                    }
                                    if ($primaryQuotation) {
                                        return 'quote_' . $primaryQuotation->id;
                                    }
                                    if ($item->supplier_id) {
                                        return 'supplier_' . $item->supplier_id . '_plant_' . ($item->productRequest?->plant_id ?? 0);
                                    }
                                    return 'supplier_0_pr_' . $item->product_request_id;
                                });
                            @endphp
                            @forelse($groupedItems as $groupKey => $groupItems)
                                @php
                                    if ($activeStatus === 'add_quotation') {
                                        $groupItems = $groupItems->filter(fn($g) => $g->workflowStatus() === \App\Enums\ProductRequestItemWorkflowStatus::AddQuotation);
                                    } elseif ($activeStatus === 'create_po') {
                                        $groupItems = $groupItems->filter(fn($g) => $g->workflowStatus() === \App\Enums\ProductRequestItemWorkflowStatus::CreatePo);
                                    } elseif ($activeStatus === 'dispatch') {
                                        $groupItems = $groupItems->filter(fn($g) => in_array($g->workflowStatus(), [
                                            \App\Enums\ProductRequestItemWorkflowStatus::PoCreated,
                                            \App\Enums\ProductRequestItemWorkflowStatus::Dispatch,
                                        ], true));
                                    }
                                @endphp
                                @if($groupItems->isEmpty())
                                    @continue
                                @endif
                                @php
                                    $firstItem = $groupItems->first();
                                    $pr = $firstItem->productRequest;
                                    $quotations = $firstItem->relatedQuotations();
                                    $purchaseOrders = $firstItem->relatedPurchaseOrders();
                                    $primaryQuotation = $quotations->first();
                                    $primaryPurchase = $purchaseOrders->first();
                                    $pendingDispatch = $purchaseOrders->first(fn($purchase) => $purchase->dispatched_at === null) ?? $primaryPurchase;
                                    $doc = $activeStatus === 'dispatch' ? ($primaryPurchase ?? $primaryQuotation) : ($primaryQuotation ?? $primaryPurchase);
                                    $refNo = $doc?->reference_no ?? ('Inquiry (#' . $pr->id . ')');
                                    $supplierName = $doc?->supplier?->name ?? $firstItem->supplier?->name ?? '—';
                                    $createDate = $firstItem->exported_at ?? $doc?->ordered_at ?? $doc?->created_at ?? $pr->created_at;
                                @endphp
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-primary">{{ $refNo }}</span>
                                        @if($activeStatus === 'dispatch' && $primaryQuotation && $primaryQuotation->reference_no !== $refNo)
                                            <span class="d-block small text-muted">Quote: {{ $primaryQuotation->reference_no }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="product-group-list">
                                            @foreach($groupItems as $gItem)
                                                <div class="py-1 d-flex align-items-center gap-2 {{ !$loop->last ? 'border-bottom border-light-subtle' : '' }}">
                                                    @if($isPurchaser && $activeStatus === 'add_quotation' && $gItem->canSelectForPo())
                                                        <input type="checkbox" class="form-check-input request-select"
                                                            name="product_request_item_ids[]" value="{{ $gItem->id }}"
                                                            data-plant-id="{{ $pr->plant_id }}" data-plant-name="{{ $pr->plant->name }}"
                                                            data-has-product="{{ $gItem->product_id ? 1 : 0 }}">
                                                    @endif
                                                    <div>
                                                        <span class="fw-medium text-dark">{{ $gItem->displayName() }}</span>
                                                        <span class="d-inline-block small text-muted ms-1">
                                                            (Qty: {{ number_format((float)$gItem->quantity, 2) }} {{ $gItem->displayUnit() }}{{ $gItem->size ? ' | Size: '.$gItem->size : '' }})
                                                        </span>
                                                        @if(!$gItem->product_id && auth()->user()->canManagePlantProducts())
                                                            <a href="{{ route('plant.products.create', [
                                                                'name' => $gItem->displayName(),
                                                                'size' => $gItem->size,
                                                                'category_id' => $gItem->subCategory?->category_id ?? $gItem->product?->category_id,
                                                                'sub_category_id' => $gItem->sub_category_id,
                                                                'product_request_id' => $pr->id,
                                                                'product_request_item_id' => $gItem->id,
                                                            ]) }}" class="btn btn-xs btn-outline-primary ms-1 py-0 px-2 d-inline-flex align-items-center gap-1" style="font-size: 0.725rem;" title="Create product in catalog">
                                                                <i class="bi bi-plus-circle"></i>Create Product
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-medium text-dark">{{ $supplierName }}</span>
                                    </td>
                                    <td class="small text-muted">{{ $pr->created_at->format('d M Y') }}</td>
                                    <td>{{ $pr->plant->name }}</td>
                                    <td class="small text-muted">{{ $createDate?->format('d M Y') ?? '—' }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 align-items-center justify-content-end flex-wrap">
                                            @if(!in_array($activeStatus, ['add_quotation', 'create_po']))
                                                <a href="{{ route('plant.product-requests.show', [$pr, 'item_ids' => $groupItems->pluck('id')->toArray()]) }}"
                                                    class="btn btn-sm btn-admin-outline" title="View Request">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                            @endif
                                            @if($doc)
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-print-purchase"
                                                    data-pdf-url="{{ route('plant.purchases.pdf', $doc) }}"
                                                    data-reference="{{ $doc->reference_no }}"
                                                    data-default-name="{{ ($pdfDefaults['company_name'] ?? '') ?: $pr->plant->name }}"
                                                    data-default-address="{{ ($pdfDefaults['company_address'] ?? '') ?: ($pr->plant->address ?? '') }}"
                                                    data-default-gstin="{{ $pdfDefaults['company_gstin'] ?? '' }}">
                                                    <i class="bi bi-printer me-1"></i>Print
                                                </button>
                                            @endif
                                            @if($activeStatus === 'add_quotation' && $isPurchaser)
                                                @if($primaryQuotation)
                                                    @if($primaryQuotation->canBeEdited())
                                                        <a href="{{ route('plant.purchases.edit', $primaryQuotation) }}"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil me-1"></i>Edit Quote
                                                        </a>
                                                    @endif
                                                @else
                                                    <button type="submit" form="quote-form-{{ $groupKey }}" class="btn btn-sm btn-warning">
                                                        <i class="bi bi-file-earmark-text me-1"></i>Add Quotation
                                                    </button>
                                                @endif
                                            @endif
                                            @if($activeStatus === 'create_po' && $isPurchaser && $primaryQuotation)
                                                <a href="{{ route('plant.purchases.create', ['quotation_id' => $primaryQuotation->id]) }}"
                                                    class="btn btn-sm btn-warning">
                                                    <i class="bi bi-cart-plus me-1"></i>Create PO
                                                </a>
                                                @if($primaryQuotation->canBeEdited())
                                                    <a href="{{ route('plant.purchases.edit', $primaryQuotation) }}"
                                                        class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil me-1"></i>Edit
                                                    </a>
                                                @endif
                                            @endif
                                            @if(($activeStatus === 'dispatch' || $primaryPurchase) && $pendingDispatch)
                                                @if($isPurchaser && $pendingDispatch->canBeEdited())
                                                    <a href="{{ route('plant.purchases.edit', $pendingDispatch) }}"
                                                        class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil me-1"></i>Edit PO
                                                    </a>
                                                @endif
                                                @if(auth()->user()->canDispatchOrders())
                                                    <a href="{{ route('plant.dispatch.show', $pendingDispatch) }}"
                                                        class="btn btn-sm {{ $pendingDispatch->isDispatched() ? 'btn-admin-outline' : 'btn-success' }}">
                                                        <i class="bi bi-truck me-1"></i>{{ $pendingDispatch->isDispatched() ? 'View Dispatch' : 'Dispatch' }}
                                                    </a>
                                                @endif
                                            @endif
                                            @if(($isPurchaser || auth()->user()->canManagePurchaseRequests() || auth()->user()->canManagePlantPurchases()) && in_array($pr->status->value, ['submitted', 'in_progress']) && $groupItems->contains(fn($gItem) => $gItem->canSelectForPo()))
                                                <a href="{{ route('plant.product-requests.edit', [$pr, 'item_ids' => $groupItems->pluck('id')->toArray()]) }}"
                                                    class="btn btn-sm btn-outline-primary" title="Edit Inquiry Items">
                                                    <i class="bi bi-pencil me-1"></i>Edit PR
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                @include('partials.table-empty', ['colspan' => 7, 'icon' => 'bi-clipboard-check', 'message' => 'No product requests'])
                            @endforelse
                        @else
                            @forelse($items as $item)
                                @php
                                    $pr = $item->productRequest;
                                    $workflow = $item->workflowStatus();
                                    $canSelect = $isPurchaser && $item->canSelectForPo();
                                    $quotations = $item->relatedQuotations();
                                    $purchaseOrders = $item->relatedPurchaseOrders();
                                    $primaryQuotation = $quotations->first();
                                    $primaryPurchase = $purchaseOrders->first();
                                    $pendingDispatch = $purchaseOrders->first(fn($purchase) => $purchase->dispatched_at === null) ?? $primaryPurchase;
                                    $doc = $primaryPurchase ?? $primaryQuotation;
                                    $refNo = $doc?->reference_no ?? ('#' . $pr->id);
                                    $supplierName = $primaryPurchase?->supplier?->name ?? $primaryQuotation?->supplier?->name ?? $item->supplier?->name ?? '—';
                                    $createDate = $primaryPurchase?->ordered_at ?? $primaryPurchase?->created_at ?? $primaryQuotation?->created_at ?? $pr->created_at;
                                @endphp
                                <tr>
                                    @if($isPurchaser && ($activeStatus === 'add_quotation' || !$isQuotationPoView))
                                        <td>
                                            @if($canSelect)
                                                <input type="checkbox" class="form-check-input request-select"
                                                    name="product_request_item_ids[]" value="{{ $item->id }}"
                                                    data-plant-id="{{ $pr->plant_id }}" data-plant-name="{{ $pr->plant->name }}"
                                                    data-has-product="{{ $item->product_id ? 1 : 0 }}">
                                            @endif
                                        </td>
                                    @endif
                                    <td>{{ $isQuotationPoView ? $refNo : '#' . $pr->id }}</td>
                                    @if($isPurchaser && !$isQuotationPoView)
                                        <td class="fw-semibold">{{ $pr->plant->name }}</td>
                                    @endif
                                    <td>
                                        <span class="fw-semibold">{{ $item->displayName() }}</span>
                                        @if($isQuotationPoView)
                                            <span class="d-block small text-muted">Qty: {{ $item->quantity }} {{ $item->displayUnit() }}</span>
                                        @endif
                                        @if(!$item->product_id)
                                            <div class="mt-1 d-flex align-items-center gap-1 flex-wrap">
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle py-1 px-2" style="font-size: 0.725rem;">Product Required</span>
                                                @if(auth()->user()->canManagePlantProducts())
                                                    <a href="{{ route('plant.products.create', [
                                                        'name' => $item->displayName(),
                                                        'size' => $item->size,
                                                        'category_id' => $item->subCategory?->category_id ?? $item->product?->category_id,
                                                        'sub_category_id' => $item->sub_category_id,
                                                        'product_request_id' => $pr->id,
                                                        'product_request_item_id' => $item->id,
                                                    ]) }}" class="btn btn-xs btn-outline-primary py-0 px-2 d-inline-flex align-items-center gap-1" style="font-size: 0.725rem;" title="Create product in catalog">
                                                        <i class="bi bi-plus-circle"></i>Create Product
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    @if($isQuotationPoView)
                                        <td>{{ $supplierName }}</td>
                                        <td class="small text-muted">{{ $pr->created_at->format('d M Y') }}</td>
                                        <td>{{ $pr->plant->name }}</td>
                                        <td class="small text-muted">{{ $createDate?->format('d M Y') ?? '—' }}</td>
                                    @else
                                        <td>{{ $item->size ?? '—' }}</td>
                                        <td class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                                        <td>{{ $item->displayUnit() }}</td>
                                        {{-- <td>{{ $pr->displayRequesterName() }}</td> --}}
                                        <td><x-status-badge :status="$item->priority" /></td>
                                        <td><x-status-badge :status="$workflow" /></td>
                                        <td>{{ $item->remarks ?: '—' }}</td>
                                        <td>{{ $pr->created_at->format('d M Y') }}</td>
                                    @endif
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 align-items-center justify-content-end flex-wrap">
                                            <a href="{{ route('plant.product-requests.show', $pr) }}"
                                                class="btn btn-sm btn-admin-outline" title="View Request">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                            @if($doc)
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-print-purchase"
                                                    data-pdf-url="{{ route('plant.purchases.pdf', $doc) }}"
                                                    data-reference="{{ $doc->reference_no }}"
                                                    data-default-name="{{ ($pdfDefaults['company_name'] ?? '') ?: $pr->plant->name }}"
                                                    data-default-address="{{ ($pdfDefaults['company_address'] ?? '') ?: ($pr->plant->address ?? '') }}"
                                                    data-default-gstin="{{ $pdfDefaults['company_gstin'] ?? '' }}">
                                                    <i class="bi bi-printer me-1"></i>Print
                                                </button>
                                            @endif
                                            @if($isPurchaser)
                                                @if($workflow === \App\Enums\ProductRequestItemWorkflowStatus::Pending)
                                                    <button type="submit" class="btn btn-sm btn-admin-outline js-single-product"
                                                        formaction="{{ route('plant.product-requests.bulk-export-supplier') }}"
                                                        data-item-id="{{ $item->id }}" title="Export this product for supplier">
                                                        <i class="bi bi-file-earmark-excel me-1"></i>Export
                                                    </button>
                                                @elseif($workflow === \App\Enums\ProductRequestItemWorkflowStatus::AddQuotation)
                                                    <button type="submit" class="btn btn-sm btn-admin-outline js-single-product"
                                                        formaction="{{ route('plant.product-requests.bulk-export-supplier') }}"
                                                        data-item-id="{{ $item->id }}" title="Export this product for supplier">
                                                        <i class="bi bi-file-earmark-excel me-1"></i>Export
                                                    </button>
                                                    <button type="submit" class="btn btn-sm btn-warning js-single-product"
                                                        data-item-id="{{ $item->id }}">
                                                        <i class="bi bi-file-earmark-text me-1"></i>Add Quotation
                                                    </button>
                                                @elseif($workflow === \App\Enums\ProductRequestItemWorkflowStatus::CreatePo && $primaryQuotation)
                                                    <a href="{{ route('plant.purchases.create', ['quotation_id' => $primaryQuotation->id]) }}"
                                                        class="btn btn-sm btn-warning">
                                                        <i class="bi bi-cart-plus me-1"></i>Create PO
                                                    </a>
                                                    @if($primaryQuotation->canBeEdited())
                                                        <a href="{{ route('plant.purchases.edit', $primaryQuotation) }}"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil me-1"></i>Edit
                                                        </a>
                                                    @endif
                                                @elseif($primaryPurchase)
                                                    @if($primaryPurchase->canBeEdited())
                                                        <a href="{{ route('plant.purchases.edit', $primaryPurchase) }}"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil me-1"></i>Edit
                                                        </a>
                                                    @endif
                                                @endif
                                            @endif
                                            @if(auth()->user()->canDispatchOrders() && $pendingDispatch)
                                                <a href="{{ route('plant.dispatch.show', $pendingDispatch) }}"
                                                    class="btn btn-sm {{ $workflow === \App\Enums\ProductRequestItemWorkflowStatus::Dispatch ? 'btn-admin-outline' : 'btn-success' }}">
                                                    <i class="bi bi-truck me-1"></i>{{ $workflow === \App\Enums\ProductRequestItemWorkflowStatus::Dispatch ? 'View Dispatch' : 'Dispatch' }}
                                                </a>
                                            @endif
                                            @if(($isPurchaser || auth()->user()->canManagePurchaseRequests() || auth()->user()->canManagePlantPurchases()) && in_array($pr->status->value, ['submitted', 'in_progress']) && $item->canSelectForPo())
                                                <a href="{{ route('plant.product-requests.edit', [$pr, 'item_id' => $item->id]) }}"
                                                    class="btn btn-sm btn-outline-primary" title="Edit Request Item">
                                                    <i class="bi bi-pencil me-1"></i>Edit
                                                </a>
                                            @endif
                                            @if($isPurchaser && $workflow === \App\Enums\ProductRequestItemWorkflowStatus::Pending)
                                                <button type="submit" form="delete-pr-item-{{ $item->id }}"
                                                    class="btn btn-sm btn-outline-danger" title="Delete" aria-label="Delete">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                 @include('partials.table-empty', ['colspan' => $isQuotationPoView ? 7 : ($isPurchaser ? 12 : 10), 'icon' => 'bi-clipboard-check', 'message' => 'No product requests'])
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @if($isPurchaser)
            </form>
        @endif

        @if($isPurchaser && ! $isQuotationPoView)
            @foreach($items as $deleteItem)
                @if($deleteItem->workflowStatus() === \App\Enums\ProductRequestItemWorkflowStatus::Pending)
                    <form id="delete-pr-item-{{ $deleteItem->id }}" method="POST"
                        action="{{ route('plant.product-requests.items.destroy', $deleteItem) }}"
                        class="d-none" onsubmit="return confirm(@js('Delete this product request item?'))">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            @endforeach
        @endif

        @if(in_array($activeStatus, ['add_quotation', 'create_po', 'dispatch']) && isset($groupedItems) && $isPurchaser)
            @foreach($groupedItems as $groupKey => $groupItems)
                @php
                    $firstGroupItem = $groupItems->first();
                @endphp
                <form method="POST" action="{{ route('plant.product-requests.bulk-convert') }}" id="quote-form-{{ $groupKey }}">
                    @csrf
                    @foreach($groupItems as $gItem)
                        @if($gItem->canSelectForPo())
                            <input type="hidden" name="product_request_item_ids[]" value="{{ $gItem->id }}">
                        @endif
                    @endforeach
                    @if($firstGroupItem->supplier_id)
                        <input type="hidden" name="supplier_id" value="{{ $firstGroupItem->supplier_id }}">
                    @endif
                </form>
            @endforeach
        @endif
    <div class="mt-3">{{ $items->links() }}</div>

    <div class="modal fade" id="purchasePrintModal" tabindex="-1" aria-labelledby="purchasePrintModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="purchasePrintModalLabel">Print document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Enter letterhead details for <strong
                            id="print_modal_reference">—</strong> PDF.</p>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="print_company_name">Company name</label>
                        <input type="text" id="print_company_name" class="form-control form-control-sm" required
                            maxlength="200">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="print_company_address">Address</label>
                        <textarea id="print_company_address" class="form-control form-control-sm" rows="3"
                            maxlength="1000"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small mb-1" for="print_company_gstin">GSTIN</label>
                        <input type="text" id="print_company_gstin" class="form-control form-control-sm" maxlength="20"
                            placeholder="e.g. 22AAAAA0000A1Z5">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-admin-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn_confirm_print">
                        <i class="bi bi-printer me-1"></i>Print PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@if($isPurchaser)
    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('bulk-quotation-form');
                if (!form) return;

                const selectAll = document.getElementById('select-all-requests');
                const boxes = () => Array.from(document.querySelectorAll('.request-select'));
                const btn = document.getElementById('bulk-generate-btn');
                const exportBtn = document.getElementById('bulk-export-btn');
                const hint = document.getElementById('bulk-selection-hint');

                const STORAGE_KEY = 'pr_selected_items';
                const LAST_STATUS_KEY = 'pr_last_status';
                const currentStatus = '{{ $activeStatus ?? "" }}';

                const lastStatus = sessionStorage.getItem(LAST_STATUS_KEY);
                if (lastStatus !== null && lastStatus !== currentStatus) {
                    sessionStorage.removeItem(STORAGE_KEY);
                }
                sessionStorage.setItem(LAST_STATUS_KEY, currentStatus);

                function getStoredItems() {
                    try {
                        return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '{}');
                    } catch (e) {
                        return {};
                    }
                }

                function saveStoredItems(items) {
                    try {
                        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(items));
                    } catch (e) {}
                }

                function restoreDomCheckboxes() {
                    const stored = getStoredItems();
                    boxes().forEach((el) => {
                        if (stored[el.value]) {
                            el.checked = true;
                        }
                    });
                }

                function updateStorageFromDomItem(el) {
                    const stored = getStoredItems();
                    if (el.checked) {
                        stored[el.value] = {
                            id: String(el.value),
                            plantId: String(el.dataset.plantId || ''),
                            plantName: String(el.dataset.plantName || '')
                        };
                    } else {
                        delete stored[el.value];
                    }
                    saveStoredItems(stored);
                }

                function clearAllSelection() {
                    saveStoredItems({});
                    boxes().forEach((el) => { el.checked = false; });
                    sync();
                }

                function sync() {
                    const stored = getStoredItems();
                    const allSelected = Object.values(stored);
                    const plantIds = [...new Set(allSelected.map((el) => el.plantId).filter(Boolean))];
                    const samePlant = plantIds.length <= 1;

                    if (btn) btn.disabled = allSelected.length === 0 || !samePlant;
                    if (exportBtn) exportBtn.disabled = allSelected.length === 0;

                    if (allSelected.length === 0) {
                        hint.innerHTML = 'Select products. Export for supplier first, then add quotation. Same plant only.';
                        hint.classList.remove('text-danger');
                    } else if (!samePlant) {
                        hint.innerHTML = 'Adding quotation requires products from 1 plant/site. Use "Plant / Site" filter at top to select requests for one plant (or click Export for Supplier). <a href="#" id="js-clear-selection" class="ms-2 text-danger text-decoration-underline small">Clear selection</a>';
                        hint.classList.add('text-danger');
                    } else {
                        hint.classList.remove('text-danger');
                        const name = allSelected[0]?.plantName || 'site';
                        const label = allSelected.length === 1 ? '1 product' : `${allSelected.length} products`;
                        const visibleCheckedCount = boxes().filter((el) => el.checked).length;
                        const pageNote = allSelected.length > visibleCheckedCount ? ' (across pages)' : '';
                        hint.innerHTML = `${label}${pageNote} selected from ${name} — Export for supplier, then Add Quotation <a href="#" id="js-clear-selection" class="btn btn-sm btn-secondary ms-2">Clear selection</a>`;
                    }

                    const clearBtn = document.getElementById('js-clear-selection');
                    if (clearBtn) {
                        clearBtn.onclick = (e) => {
                            e.preventDefault();
                            clearAllSelection();
                        };
                    }

                    if (selectAll) {
                        const enabled = boxes();
                        const checkedOnPage = enabled.filter((el) => el.checked);
                        selectAll.checked = enabled.length > 0 && checkedOnPage.length === enabled.length;
                        selectAll.indeterminate = (checkedOnPage.length > 0 && checkedOnPage.length < enabled.length) || (checkedOnPage.length === 0 && allSelected.length > 0);
                    }
                }

                restoreDomCheckboxes();

                selectAll?.addEventListener('change', () => {
                    const enabled = boxes();
                    const stored = getStoredItems();
                    if (selectAll.checked && enabled.length) {
                        const firstPlant = enabled[0].dataset.plantId;
                        enabled.forEach((el) => {
                            if (el.dataset.plantId === firstPlant) {
                                el.checked = true;
                                stored[el.value] = {
                                    id: String(el.value),
                                    plantId: String(el.dataset.plantId || ''),
                                    plantName: String(el.dataset.plantName || '')
                                };
                            }
                        });
                    } else {
                        enabled.forEach((el) => {
                            el.checked = false;
                            delete stored[el.value];
                        });
                    }
                    saveStoredItems(stored);
                    sync();
                });

                document.addEventListener('change', (e) => {
                    if (e.target.classList.contains('request-select')) {
                        updateStorageFromDomItem(e.target);
                        sync();
                    }
                });

                form.addEventListener('click', (e) => {
                    const single = e.target.closest('.js-single-product');
                    if (!single || !form.contains(single)) return;
                    const id = String(single.dataset.itemId || '');
                    const targetBox = boxes().find((el) => el.value === id);
                    const stored = {};
                    boxes().forEach((el) => { el.checked = el.value === id; });
                    if (targetBox) {
                        stored[id] = {
                            id: id,
                            plantId: String(targetBox.dataset.plantId || ''),
                            plantName: String(targetBox.dataset.plantName || '')
                        };
                    }
                    saveStoredItems(stored);
                    sync();
                });

                form.addEventListener('submit', (e) => {
                    const stored = getStoredItems();
                    const allSelected = Object.values(stored);
                    const plantIds = [...new Set(allSelected.map((el) => el.plantId).filter(Boolean))];

                    if (allSelected.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one product.');
                        return;
                    }

                    const unlinkedBoxes = boxes().filter((box) => box.checked && box.dataset.hasProduct === '0');
                    if (unlinkedBoxes.length > 0) {
                        e.preventDefault();
                        alert('One or more selected items do not have a catalog product assigned. Please click "+ Create Product" to link the product first.');
                        return;
                    }

                    const action = (e.submitter && e.submitter.getAttribute('formaction')) || form.action;
                    const isExport = action.includes('export-supplier');

                    if (isExport) {
                        const supplierSelect = document.getElementById('export-supplier-select');
                        if (!supplierSelect || !supplierSelect.value) {
                            e.preventDefault();
                            alert('Please select a supplier before exporting inquiry.');
                            return;
                        }
                    }

                    if (!isExport && plantIds.length > 1) {
                        e.preventDefault();
                        alert('Select products from one plant/site only.');
                        return;
                    }

                    if (!isExport) {
                        const label = allSelected.length === 1 ? '1 product' : `${allSelected.length} products`;
                        if (!confirm(`Add quotation for ${label}?`)) {
                            e.preventDefault();
                            return;
                        }
                    }

                    form.querySelectorAll('.js-hidden-request-item').forEach((el) => el.remove());
                    boxes().forEach((el) => el.removeAttribute('name'));

                    allSelected.forEach((item) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'product_request_item_ids[]';
                        input.value = item.id;
                        input.className = 'js-hidden-request-item';
                        form.appendChild(input);
                    });

                    if (!isExport) {
                        sessionStorage.removeItem(STORAGE_KEY);
                    } else {
                        sessionStorage.removeItem(STORAGE_KEY);
                        setTimeout(() => {
                            window.location.href = "{{ route('plant.product-requests.index', ['status' => 'add_quotation']) }}";
                        }, 1200);
                    }
                });

                sync();
            })();

            (function () {
                const modalEl = document.getElementById('purchasePrintModal');
                if (!modalEl) return;

                const modal = new bootstrap.Modal(modalEl);
                let activePdfUrl = '';

                document.querySelectorAll('.btn-print-purchase').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        activePdfUrl = btn.dataset.pdfUrl || '';
                        document.getElementById('print_modal_reference').textContent = btn.dataset.reference || '—';
                        document.getElementById('print_company_name').value = btn.dataset.defaultName || '';
                        document.getElementById('print_company_address').value = btn.dataset.defaultAddress || '';
                        document.getElementById('print_company_gstin').value = btn.dataset.defaultGstin || '';
                        modal.show();
                    });
                });

                document.getElementById('btn_confirm_print')?.addEventListener('click', function () {
                    const name = document.getElementById('print_company_name').value.trim();
                    if (!name || !activePdfUrl) return;
                    const params = new URLSearchParams({
                        company_name: name,
                        company_address: document.getElementById('print_company_address').value.trim(),
                        company_gstin: document.getElementById('print_company_gstin').value.trim(),
                    });
                    window.open(activePdfUrl + (activePdfUrl.includes('?') ? '&' : '?') + params.toString(), '_blank');
                    modal.hide();
                });
            })();
        </script>
    @endpush
@endif

@push('scripts')
    <style>
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1050;
            margin-top: 2px;
        }

        .suggestion-item {
            cursor: pointer;
            transition: background 0.15s ease-in-out;
        }

        .suggestion-item:hover {
            background-color: #f8f9fa;
        }

        .suggestion-item:last-child {
            border-bottom: none !important;
        }
    </style>
    @include('partials.infinite-suggestions')
    @php
        $productSearchData = $products->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku ?? '',
            'category' => $p->category?->name ?? '',
            'item_type' => $p->subCategory?->name ?? '',
        ])->values();
    @endphp
    <script type="application/json" id="products-search-data">@json($productSearchData)</script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('product-search');
            const suggestionsBox = document.getElementById('autocomplete-suggestions');
            const productsDataEl = document.getElementById('products-search-data');
            if (!searchInput || !suggestionsBox || !productsDataEl) {
                return;
            }

            const products = JSON.parse(productsDataEl.textContent);

            const infinite = window.PanchmukhiInfiniteSuggestions.create(suggestionsBox, {
                pageSize: 25,
                renderItem: function (p) {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item px-3 py-2 border-bottom text-start';
                    div.innerHTML = `<span class="fw-semibold text-dark d-block text-truncate" style="max-width:100%">${p.name}</span> <span class="text-muted small">${p.category || 'No category'} / ${p.item_type || 'No item type'} · ${p.sku || 'No SKU'}</span>`;
                    div.addEventListener('click', function () {
                        const productSelect = document.getElementById('product-id-filter');
                        searchInput.value = '';
                        if (productSelect) {
                            productSelect.value = String(p.id);
                        }
                        infinite.reset();
                        suggestionsBox.classList.add('d-none');
                        searchInput.form.submit();
                    });
                    return div;
                },
            });

            searchInput.addEventListener('input', function () {
                const val = this.value.trim().toLowerCase();

                if (!val) {
                    infinite.reset();
                    suggestionsBox.classList.add('d-none');
                    return;
                }

                const filtered = products.filter(function (p) {
                    return p.name.toLowerCase().includes(val) ||
                        (p.sku && p.sku.toLowerCase().includes(val));
                });

                if (!infinite.setResults(filtered)) {
                    suggestionsBox.classList.add('d-none');
                    return;
                }

                suggestionsBox.classList.remove('d-none');
            });

            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                    infinite.reset();
                    suggestionsBox.classList.add('d-none');
                }
            });
        });
    </script>
@endpush