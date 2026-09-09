@extends('layouts.plant')
@section('title', 'Product Requests')
@section('content')
    @php
        $activeStatus = $activeStatus ?? request('status');
    @endphp
    <x-admin-page-header title="Product Requests" :subtitle="$isPurchaser ? 'Product-wise list — select same plant to export or create quotation' : 'Request products for your plant'">
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
                ['label' => 'All', 'key' => 'all'],
                ['label' => 'Send Quotation', 'key' => 'send_quotation'],
                ['label' => 'Create PO', 'key' => 'create_po'],
                ['label' => 'Dispatch', 'key' => 'dispatch'],
                ['label' => 'PENDING', 'key' => 'pending'],
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
                <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All products</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
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
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <button type="submit" class="btn btn-danger btn-sm" id="bulk-export-btn"
                    formaction="{{ route('plant.product-requests.bulk-export-supplier') }}" disabled>
                    <i class="bi bi-file-earmark-excel me-1"></i>Export for Supplier
                </button>
                <button type="submit" class="btn btn-warning btn-sm" id="bulk-generate-btn" disabled>
                    <i class="bi bi-file-earmark-text me-1"></i>Create Quotation (selected)
                </button>
                <span class="small text-muted" id="bulk-selection-hint">Select products. Export for supplier first, then create
                    quotation. Same plant only.</span>
            </div>
    @endif

        @php
            $isQuotationPoView = in_array($activeStatus, ['send_quotation', 'create_po', 'dispatch']);
        @endphp

        <div class="admin-card admin-table-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        @if($isQuotationPoView)
                            <tr>
                                @if($isPurchaser && $activeStatus === 'send_quotation')
                                    <th style="width: 2.5rem">
                                        <input type="checkbox" class="form-check-input" id="select-all-requests"
                                            title="Select all eligible products">
                                    </th>
                                @endif
                                <th>Quotation / PO</th>
                                <th>Product</th>
                                <th>Supplier Name</th>
                                <th>Date of Request</th>
                                <th>Site (Plant)</th>
                                <th>Create Date</th>
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
                                <th>Requested By</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody>
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
                            @if($isQuotationPoView)
                                <tr>
                                    @if($isPurchaser && $activeStatus === 'send_quotation')
                                        <td>
                                            @if($canSelect)
                                                <input type="checkbox" class="form-check-input request-select"
                                                    name="product_request_item_ids[]" value="{{ $item->id }}"
                                                    data-plant-id="{{ $pr->plant_id }}" data-plant-name="{{ $pr->plant->name }}">
                                            @endif
                                        </td>
                                    @endif
                                    <td>
                                        <span class="fw-semibold">{{ $refNo }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-medium text-dark">{{ $item->displayName() }}</span>
                                        @if($item->size)
                                            <span class="d-block small text-muted">Size: {{ $item->size }} | Qty: {{ $item->quantity }}
                                                {{ $item->displayUnit() }}</span>
                                        @else
                                            <span class="d-block small text-muted">Qty: {{ $item->quantity }}
                                                {{ $item->displayUnit() }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $supplierName }}</td>
                                    <td class="small text-muted">{{ $pr->created_at->format('d M Y') }}</td>
                                    <td>{{ $pr->plant->name }}</td>
                                    <td class="small text-muted">{{ $createDate?->format('d M Y') ?? '—' }}</td>
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
                                                @if($workflow === \App\Enums\ProductRequestItemWorkflowStatus::SendQuotation)
                                                    <button type="submit" class="btn btn-sm btn-admin-outline js-single-product"
                                                        formaction="{{ route('plant.product-requests.bulk-export-supplier') }}"
                                                        data-item-id="{{ $item->id }}" title="Export this product for supplier">
                                                        <i class="bi bi-file-earmark-excel me-1"></i>Export
                                                    </button>
                                                    <button type="submit" class="btn btn-sm btn-warning js-single-product"
                                                        data-item-id="{{ $item->id }}">
                                                        <i class="bi bi-file-earmark-text me-1"></i>Create Quotation
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
                                                    @if(auth()->user()->canDispatchOrders() && $pendingDispatch)
                                                        <a href="{{ route('plant.dispatch.show', $pendingDispatch) }}"
                                                            class="btn btn-sm {{ $workflow === \App\Enums\ProductRequestItemWorkflowStatus::Dispatch ? 'btn-admin-outline' : 'btn-success' }}">
                                                            <i
                                                                class="bi bi-truck me-1"></i>{{ $workflow === \App\Enums\ProductRequestItemWorkflowStatus::Dispatch ? 'View' : 'Dispatch' }}
                                                        </a>
                                                    @endif
                                                @endif
                                                @if(in_array($pr->status->value, ['submitted', 'in_progress']) && $item->canSelectForPo())
                                                    <a href="{{ route('plant.product-requests.edit', [$pr, 'item_id' => $item->id]) }}"
                                                        class="btn btn-sm btn-primary">
                                                        Edit
                                                    </a>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    @if($isPurchaser)
                                        <td>
                                            @if($canSelect)
                                                <input type="checkbox" class="form-check-input request-select"
                                                    name="product_request_item_ids[]" value="{{ $item->id }}"
                                                    data-plant-id="{{ $pr->plant_id }}" data-plant-name="{{ $pr->plant->name }}">
                                            @endif
                                        </td>
                                    @endif
                                    <td>#{{ $pr->id }}</td>
                                    @if($isPurchaser)
                                        <td class="fw-semibold">{{ $pr->plant->name }}</td>
                                    @endif
                                    <td>
                                        <span class="fw-semibold">{{ $item->displayName() }}</span>
                                        @if($item->subCategory?->name || $item->product?->subCategory?->name)
                                            <span
                                                class="d-block small text-muted">{{ $item->subCategory?->name ?? $item->product?->subCategory?->name }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->size ?? $item->product?->size ?? '—' }}</td>
                                    <td class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td>{{ $item->displayUnit() }}</td>
                                    <td>{{ $pr->displayRequesterName() }}</td>
                                    <td><x-status-badge :status="$item->priority" /></td>
                                    <td><x-status-badge :status="$workflow" /></td>
                                    <td>{{ $pr->created_at->format('d M Y') }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 align-items-center justify-content-end flex-wrap">
                                            @if($isPurchaser)
                                                @if($workflow === \App\Enums\ProductRequestItemWorkflowStatus::SendQuotation)
                                                    <button type="submit" class="btn btn-sm btn-admin-outline js-single-product"
                                                        formaction="{{ route('plant.product-requests.bulk-export-supplier') }}"
                                                        data-item-id="{{ $item->id }}" title="Export this product for supplier">
                                                        <i class="bi bi-file-earmark-excel me-1"></i>Export
                                                    </button>
                                                    <button type="submit" class="btn btn-sm btn-warning js-single-product"
                                                        data-item-id="{{ $item->id }}">
                                                        <i class="bi bi-file-earmark-text me-1"></i>Create Quotation
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
                                                    @if($purchaseOrders->count() === 1)
                                                        <a href="{{ route('plant.purchases.show', $primaryPurchase) }}"
                                                            class="btn btn-sm btn-admin-outline" title="View PO">
                                                            <i class="bi bi-receipt me-1"></i>PO
                                                        </a>
                                                    @else
                                                        <a href="{{ route('plant.purchases.index', ['purchase_request_id' => $item->purchaseRequestItem?->purchase_request_id]) }}"
                                                            class="btn btn-sm btn-admin-outline" title="View purchase orders">
                                                            <i class="bi bi-receipt me-1"></i>{{ $purchaseOrders->count() }} POs
                                                        </a>
                                                    @endif
                                                    @if($primaryPurchase->canBeEdited())
                                                        <a href="{{ route('plant.purchases.edit', $primaryPurchase) }}"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil me-1"></i>Edit
                                                        </a>
                                                    @endif
                                                    @if(auth()->user()->canDispatchOrders() && $pendingDispatch)
                                                        <a href="{{ route('plant.dispatch.show', $pendingDispatch) }}"
                                                            class="btn btn-sm {{ $workflow === \App\Enums\ProductRequestItemWorkflowStatus::Dispatch ? 'btn-admin-outline' : 'btn-success' }}">
                                                            <i
                                                                class="bi bi-truck me-1"></i>{{ $workflow === \App\Enums\ProductRequestItemWorkflowStatus::Dispatch ? 'View' : 'Dispatch' }}
                                                        </a>
                                                    @endif
                                                @endif
                                                @if(in_array($pr->status->value, ['submitted', 'in_progress']) && $item->canSelectForPo())
                                                    <a href="{{ route('plant.product-requests.edit', [$pr, 'item_id' => $item->id]) }}"
                                                        class="btn btn-sm btn-primary">
                                                        Edit
                                                    </a>
                                                @endif
                                            @endif

                                            <a href="{{ route('plant.product-requests.show', $pr) }}"
                                                class="btn btn-sm btn-admin-outline" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            @include('partials.table-empty', ['colspan' => $isQuotationPoView ? 7 : ($isPurchaser ? 12 : 10), 'icon' => 'bi-clipboard-check', 'message' => 'No product requests'])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($isPurchaser)
            </form>
        @endif
    <div class="mt-3">{{ $items->links() }}</div>

    @if($isPurchaser)
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
    @endif
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

                function sync() {
                    const checked = boxes().filter((el) => el.checked);
                    const plantIds = [...new Set(checked.map((el) => el.dataset.plantId))];
                    const samePlant = plantIds.length <= 1;
                    if (btn) btn.disabled = checked.length === 0 || !samePlant;
                    if (exportBtn) exportBtn.disabled = checked.length === 0;

                    if (checked.length === 0) {
                        hint.textContent = 'Select products. Export for supplier first, then create quotation. Same plant only.';
                        hint.classList.remove('text-danger');
                    } else if (!samePlant) {
                        hint.textContent = 'Creating quotation requires products from 1 plant/site. Use "Plant / Site" filter at top to select requests for one plant (or click Export for Supplier).';
                        hint.classList.add('text-danger');
                    } else {
                        hint.classList.remove('text-danger');
                        const name = checked[0].dataset.plantName || 'site';
                        const label = checked.length === 1 ? '1 product' : `${checked.length} products`;
                        hint.textContent = `${label} selected from ${name} — Export for supplier, then Create Quotation`;
                    }

                    if (selectAll) {
                        const enabled = boxes();
                        selectAll.checked = enabled.length > 0 && enabled.every((el) => el.checked);
                        selectAll.indeterminate = checked.length > 0 && checked.length < enabled.length;
                    }
                }

                selectAll?.addEventListener('change', () => {
                    const enabled = boxes();
                    if (selectAll.checked && enabled.length) {
                        const firstPlant = enabled[0].dataset.plantId;
                        enabled.forEach((el) => {
                            el.checked = el.dataset.plantId === firstPlant;
                        });
                    } else {
                        enabled.forEach((el) => { el.checked = false; });
                    }
                    sync();
                });

                document.addEventListener('change', (e) => {
                    if (e.target.classList.contains('request-select')) sync();
                });

                form.addEventListener('click', (e) => {
                    const single = e.target.closest('.js-single-product');
                    if (!single || !form.contains(single)) return;
                    const id = String(single.dataset.itemId || '');
                    boxes().forEach((el) => { el.checked = el.value === id; });
                    sync();
                });

                form.addEventListener('submit', (e) => {
                    const checked = boxes().filter((el) => el.checked);
                    const plantIds = [...new Set(checked.map((el) => el.dataset.plantId))];
                    if (checked.length === 0) {
                        e.preventDefault();
                        return;
                    }
                    if (plantIds.length > 1) {
                        e.preventDefault();
                        alert('Select products from one plant/site only.');
                        return;
                    }

                    const action = (e.submitter && e.submitter.getAttribute('formaction')) || form.action;
                    const isExport = action.includes('export-supplier');
                    if (isExport) {
                        return;
                    }

                    const label = checked.length === 1 ? '1 product' : `${checked.length} products`;
                    if (!confirm(`Create quotation for ${label}?`)) {
                        e.preventDefault();
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
                    div.innerHTML = `<span class="fw-semibold text-dark d-block text-truncate" style="max-width:100%">${p.name}</span> <span class="text-muted small">${p.sku}</span>`;
                    div.addEventListener('click', function () {
                        searchInput.value = p.name;
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