<?php

namespace App\Http\Controllers\Plant\RequestPo;

use App\Enums\ProductRequestItemWorkflowStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Plant;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Services\NotificationService;
use App\Support\PermissionSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductRequestController extends Controller
{
    use AuthorizesPermissions, BelongsToUserPlant;

    public function __construct(
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $isPurchaser = $this->purchaserSeesAllPlants();

        $status = $request->string('status')->toString();
        if ($isPurchaser && $status === '') {
            $status = ProductRequestItemWorkflowStatus::Pending->value;
        }
        if ($status === 'all') {
            $status = '';
        }

        $query = ProductRequestItem::query()
            ->select('product_request_items.*')
            ->join('product_requests', 'product_requests.id', '=', 'product_request_items.product_request_id')
            ->with([
                'product.subCategory',
                'product.unit',
                'subCategory',
                'productRequest.plant',
                'productRequest.requester',
                'purchaseRequestItem.purchaseRequest.purchases.items',
            ])
            ->when(! $isPurchaser, fn ($q) => $q->where('product_requests.plant_id', $this->plantId()))
            ->when($isPurchaser && $request->filled('plant_id'), fn ($q) => $q->where('product_requests.plant_id', $request->integer('plant_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_request_items.product_id', $request->integer('product_id')))
            ->when($request->filled('search') && ! $request->filled('product_id'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(function ($q) use ($search) {
                    $q->where('product_request_items.item_name', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', '%'.$search.'%')
                            ->orWhere('sku', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('priority'), fn ($q) => $q->where('product_request_items.priority', $request->string('priority')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('product_requests.created_at', '>=', $request->date('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('product_requests.created_at', '<=', $request->date('to_date')));

        $this->applyItemWorkflowStatusFilter($query, $status);

        $query
            ->orderByDesc('product_requests.created_at')
            ->orderByDesc('product_requests.id')
            ->orderByDesc('product_request_items.id');

        return view('plant.request-po.product-requests.index', [
            'items' => $query->paginate(20)->withQueryString(),
            'products' => Product::with(['category:id,name', 'subCategory:id,name'])->orderBy('name')->get(['id', 'name', 'sku', 'category_id', 'sub_category_id']),
            'plants' => $isPurchaser ? Plant::orderBy('name')->get() : collect(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'isPurchaser' => $isPurchaser,
            'activeStatus' => $status,
            'pdfDefaults' => [
                'company_name' => config('company.name', ''),
                'company_address' => config('company.address', ''),
                'company_gstin' => config('company.gstin', ''),
            ],
        ]);
    }

    public function create(): View
    {
        return view('plant.request-po.product-requests.create', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'subCategories' => SubCategory::where('is_active', true)->orderBy('name')->get(),
            'categories' => \App\Models\Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requester_name' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.unit' => ['nullable', 'string', 'max:100'],
            'items.*.category_id' => ['nullable', 'exists:categories,id'],
            'items.*.sub_category_id' => ['nullable', 'exists:sub_categories,id'],
            'items.*.size' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.image' => ['nullable', 'image', 'max:5120'],
        ]);

        $plantId = $this->plantId();

        $productRequest = ProductRequest::create([
            'plant_id' => $plantId,
            'requested_by' => auth()->id(),
            'requester_name' => trim($data['requester_name']),
            'remarks' => $data['remarks'] ?? null,
            'status' => RequestStatus::Submitted,
        ]);

        foreach ($data['items'] as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $itemName = trim($item['item_name']);
            $size = isset($item['size']) ? trim((string) $item['size']) : null;
            $unit = isset($item['unit']) ? trim((string) $item['unit']) : null;
            $subCategoryId = $item['sub_category_id'] ?? null;

            if ($productId) {
                $product = Product::find($productId);
                if ($product) {
                    $itemName = $product->name;
                    $size = $size ?: $product->size;
                    $unit = $unit ?: ($product->unit?->abbreviation ?: $product->unit?->name);
                    $subCategoryId = $subCategoryId ?: $product->sub_category_id;
                }
            }

            $imagePath = null;
            if ($request->hasFile("items.{$index}.image")) {
                $file = $request->file("items.{$index}.image");
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads'), $fileName);
                $imagePath = 'uploads/' . $fileName;
            }

            ProductRequestItem::create([
                'product_request_id' => $productRequest->id,
                'product_id' => $productId,
                'item_name' => $productId ? null : $itemName,
                'size' => $size ?: null,
                'unit' => $unit ?: null,
                'sub_category_id' => $subCategoryId,
                'quantity' => $item['quantity'],
                'priority' => $item['priority'] ?? \App\Enums\PriorityLevel::Medium->value,
                'remarks' => $item['remarks'] ?? null,
                'image_path' => $imagePath,
            ]);
        }

        $this->notifications->notifyProductRequestSubmitted($productRequest);

        return redirect()->route('plant.product-requests.show', $productRequest)
            ->with('success', 'Product request submitted.');
    }

    public function show(Request $request, ProductRequest $productRequest): View
    {
        $this->authorizePlantAccess($productRequest);
        $productRequest->load(['plant', 'requester', 'updater', 'items.product', 'items.subCategory', 'items.supplier', 'items.purchaseRequestItem.purchaseRequest.purchases.items', 'purchaseRequest.purchases']);

        if ($request->filled('item_ids') || $request->filled('item_id')) {
            $rawIds = $request->input('item_ids', $request->input('item_id'));
            $itemIds = is_array($rawIds) ? $rawIds : explode(',', (string) $rawIds);
            $itemIds = array_filter(array_map('intval', $itemIds));
            if (! empty($itemIds)) {
                $filteredItems = $productRequest->items->filter(fn ($item) => in_array($item->id, $itemIds, true));
                if ($filteredItems->isNotEmpty()) {
                    $productRequest->setRelation('items', $filteredItems);
                }
            }
        }

        return view('plant.request-po.product-requests.show', compact('productRequest'));
    }

    public function edit(Request $request, ProductRequest $productRequest): View|RedirectResponse
    {
        $this->authorizePlantAccess($productRequest);
        if (! auth()->user()->canManagePurchaseRequests() && ! auth()->user()->canManagePlantPurchases()) {
            $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASE_REQUESTS);
        }

        if (in_array($productRequest->status, [RequestStatus::Completed, RequestStatus::Cancelled])) {
            return redirect()->route('plant.product-requests.index')
                ->with('error', 'Completed or cancelled product requests cannot be edited.');
        }

        $query = $productRequest->items()->with(['product']);
        if ($request->filled('item_ids')) {
            $rawIds = $request->input('item_ids');
            $itemIds = is_array($rawIds) ? $rawIds : explode(',', (string) $rawIds);
            $itemIds = array_filter(array_map('intval', $itemIds));
            if (! empty($itemIds)) {
                $query->whereIn('id', $itemIds);
            }
        } elseif ($request->filled('item_id')) {
            $query->where('id', $request->integer('item_id'));
        }
        $items = $query->get();

        if ($items->isEmpty()) {
            return redirect()->route('plant.product-requests.index')
                ->with('error', 'The requested product item was not found.');
        }

        $editableItems = $items->filter(fn ($item) => $item->canSelectForPo());
        if ($editableItems->isEmpty()) {
            return redirect()->route('plant.product-requests.index')
                ->with('error', 'Cannot edit product item after a quotation or PO has been created.');
        }

        $productRequest->setRelation('items', $editableItems);
        $products = Product::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('plant.request-po.product-requests.edit', compact('productRequest', 'products', 'suppliers'));
    }

    public function update(Request $request, ProductRequest $productRequest): RedirectResponse
    {
        $this->authorizePlantAccess($productRequest);
        if (! auth()->user()->canManagePurchaseRequests() && ! auth()->user()->canManagePlantPurchases()) {
            $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASE_REQUESTS);
        }

        if (in_array($productRequest->status, [RequestStatus::Completed, RequestStatus::Cancelled])) {
            return redirect()->route('plant.product-requests.index')
                ->with('error', 'Completed or cancelled product requests cannot be edited.');
        }

        $data = $request->validate([
            'requester_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:product_request_items,id'],
            'items.*.item_name' => ['nullable', 'string', 'max:255'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.image' => ['nullable', 'image', 'max:5120'],
        ]);

        foreach ($data['items'] as $itemData) {
            $item = $productRequest->items()->find($itemData['id']);
            if ($item && ! $item->canSelectForPo()) {
                return redirect()->route('plant.product-requests.index')
                    ->with('error', 'Cannot edit product item "'.$item->displayName().'" after a quotation or PO has been created.');
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($productRequest, $data, $request) {
            $updatePayload = [
                'remarks' => $data['remarks'] ?? null,
                'updated_by' => auth()->id(),
            ];

            if (isset($data['requester_name']) && trim($data['requester_name']) !== '') {
                $updatePayload['requester_name'] = trim($data['requester_name']);
            }

            $productRequest->update($updatePayload);

            foreach ($data['items'] as $index => $itemData) {
                $item = $productRequest->items()->find($itemData['id']);
                if ($item) {
                    $updateData = [
                        'quantity' => $itemData['quantity'],
                        'priority' => $itemData['priority'] ?? $item->priority,
                        'remarks' => $itemData['remarks'] ?? null,
                    ];

                    if (isset($itemData['item_name'])) {
                        $updateData['item_name'] = $itemData['item_name'];
                    }
                    if (array_key_exists('product_id', $itemData)) {
                        $updateData['product_id'] = $itemData['product_id'] ?: null;
                    }
                    if (array_key_exists('supplier_id', $itemData)) {
                        $updateData['supplier_id'] = $itemData['supplier_id'] ?: null;
                    }

                    if ($request->hasFile("items.{$index}.image")) {
                        $file = $request->file("items.{$index}.image");
                        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                        $file->move(public_path('uploads'), $fileName);
                        $updateData['image_path'] = 'uploads/' . $fileName;
                    }

                    $item->update($updateData);
                }
            }
        });

        return redirect()->route('plant.product-requests.show', $productRequest)
            ->with('success', 'Product request updated successfully.');
    }

    public function exportForSupplier(Request $request, ProductRequest $productRequest): StreamedResponse|RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASES);
        $this->authorizePlantAccess($productRequest);

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
        ], [
            'supplier_id.required' => 'Please select a supplier before exporting inquiry.',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);

        $productRequest->load([
            'plant',
            'items.product.unit',
            'items.product.subCategory',
            'items.subCategory',
            'items.purchaseRequestItem.purchaseRequest.purchases',
        ]);

        $items = $productRequest->items->filter(
            fn (ProductRequestItem $item) => $item->canSelectForPo()
        );

        if ($items->contains(fn (ProductRequestItem $item) => ! $item->product_id)) {
            return back()->with('error', 'One or more items do not have a catalog product assigned. Please click "+ Create Product" to link the product first.');
        }

        return $this->streamSupplierExport($items->isNotEmpty() ? $items : $productRequest->items, $supplier);
    }

    public function bulkExportForSupplier(Request $request): StreamedResponse|RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASES);

        $items = $this->loadSelectedProductRequestItems($request);

        if ($items instanceof RedirectResponse) {
            return $items;
        }

        if ($items->contains(fn (ProductRequestItem $item) => ! $item->product_id)) {
            return back()->with('error', 'One or more selected items do not have a catalog product assigned. Please click "+ Create Product" to link the product first.');
        }

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
        ], [
            'supplier_id.required' => 'Please select a supplier before exporting inquiry.',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);

        return $this->streamSupplierExport($items, $supplier);
    }

    /**
     * @param  Collection<int, ProductRequestItem>  $items
     */
    protected function streamSupplierExport(Collection $items, ?Supplier $supplier = null): StreamedResponse
    {
        $updateData = ['exported_at' => now()];
        if ($supplier) {
            $updateData['supplier_id'] = $supplier->id;
        }

        ProductRequestItem::whereIn('id', $items->pluck('id'))->update($updateData);

        $plants = $items->map(fn ($item) => $item->productRequest?->plant?->name)->filter()->unique()->values();
        $requestIds = $items->pluck('product_request_id')->unique()->sort()->values();
        $ids = $requestIds->implode('-');
        $plantSlug = $plants->count() === 1
            ? strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $plants->first()))
            : 'multi-plant';
        $supplierSlug = $supplier ? '-'.strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $supplier->name)) : '';
        $filename = 'supplier-inquiry'.$supplierSlug.'-'.$plantSlug.'-'.$ids.'.csv';

        return response()->streamDownload(function () use ($items, $plants, $requestIds, $supplier) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Supplier Inquiry / Quotation Request']);
            if ($supplier) {
                fputcsv($out, ['To (Supplier)', $supplier->name]);
                if ($supplier->contact_person) {
                    fputcsv($out, ['Contact Person', $supplier->contact_person]);
                }
                if ($supplier->phone || $supplier->email) {
                    fputcsv($out, ['Contact Info', trim(($supplier->phone ?? '').' '.($supplier->email ?? ''))]);
                }
                if ($supplier->gstin) {
                    fputcsv($out, ['GSTIN', $supplier->gstin]);
                }
            }
            fputcsv($out, ['Plant / Site', $plants->implode(', ')]);
            fputcsv($out, ['Date', now()->format('d-m-Y')]);
            fputcsv($out, ['Product Request #', $requestIds->map(fn ($id) => '#'.$id)->implode(', ')]);
            fputcsv($out, ['Requested By', $items->map(fn (ProductRequestItem $item) => $item->productRequest?->displayRequesterName())->filter()->unique()->implode(', ')]);
            fputcsv($out, []);
            fputcsv($out, ['#', 'Plant / Site', 'Product', 'Type', 'Size', 'Qty', 'Unit', 'Remarks', 'Rate', 'GST%', 'Amount']);

            foreach ($items->values() as $index => $item) {
                fputcsv($out, [
                    $index + 1,
                    $item->productRequest?->plant?->name ?? '',
                    $item->displayName(),
                    $item->subCategory?->name ?? $item->product?->subCategory?->name ?? '',
                    $item->size ?? $item->product?->size ?? '',
                    number_format((float) $item->quantity, 2, '.', ''),
                    $item->unit ?: ($item->product?->unit?->name ?? ''),
                    $item->remarks ?? '',
                    '',
                    '',
                    '',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Please fill Rate, GST% and Amount and return this sheet.']);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function convertToPurchaseRequest(ProductRequest $productRequest): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASES);
        $this->authorizePlantAccess($productRequest);

        if ($productRequest->status === RequestStatus::Cancelled) {
            return redirect()->route('plant.product-requests.index')
                ->with('error', 'Cancelled product requests cannot be converted.');
        }

        $productRequest->load(['items.purchaseRequestItem.purchaseRequest.purchases.items']);

        $unconverted = $productRequest->items->filter(
            fn (ProductRequestItem $item) => ! $item->purchaseRequestItem
        );

        if ($unconverted->isNotEmpty()) {
            return $this->convertOrResumeProductRequestItems($unconverted);
        }

        $awaitingPo = $productRequest->items->filter(
            fn (ProductRequestItem $item) => $item->relatedPurchases()->isEmpty()
        );

        if ($awaitingPo->isNotEmpty()) {
            return $this->convertOrResumeProductRequestItems($awaitingPo);
        }

        $purchase = $productRequest->items
            ->map(fn (ProductRequestItem $item) => $item->relatedPurchases()->first())
            ->filter()
            ->first();

        if ($purchase) {
            return redirect()->route('plant.purchases.show', $purchase)
                ->with('info', 'Purchase order already exists.');
        }

        return redirect()->route('plant.product-requests.index')
            ->with('error', 'No products left to convert.');
    }

    public function bulkConvertToPurchaseRequest(Request $request): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASES);

        $items = $this->loadSelectedProductRequestItems($request);

        if ($items instanceof RedirectResponse) {
            return $items;
        }

        $createType = $request->string('create_type')->toString();

        return $this->convertOrResumeProductRequestItems($items, $createType);
    }

    /**
     * @param  Collection<int, ProductRequestItem>  $items
     */
    protected function convertOrResumeProductRequestItems(Collection $items, string $createType = ''): RedirectResponse
    {
        if ($items->isEmpty()) {
            return back()->with('error', 'Select at least one product.');
        }

        if ($items->contains(fn (ProductRequestItem $item) => ! $item->product_id)) {
            return back()->with('error', 'One or more selected items do not have a catalog product assigned. Please click "+ Create Product" to link the product first.');
        }

        if ($items->contains(fn (ProductRequestItem $item) => $item->productRequest?->status === RequestStatus::Cancelled)) {
            return back()->with('error', 'Cancelled product requests cannot be included.');
        }

        if ($items->contains(fn (ProductRequestItem $item) => $item->relatedPurchases()->isNotEmpty())) {
            return back()->with('error', 'One or more selected products already have a quotation or PO.');
        }

        if ($items->map(fn (ProductRequestItem $item) => $item->productRequest?->plant_id)->unique()->count() > 1) {
            return back()->with('error', 'Select products from one plant/site only.');
        }

        $purchaseRequest = $this->consolidateItemsOntoPurchaseRequest($items);
        $count = $items->count();
        $plantName = $items->first()->productRequest?->plant?->name ?? 'site';

        $inquiryIds = $items
            ->map(fn (ProductRequestItem $item) => $item->product_request_id)
            ->filter()
            ->unique()
            ->values();

        $inquiryId = $inquiryIds->isNotEmpty()
            ? $inquiryIds->implode(',')
            : ($purchaseRequest->product_request_id ?? $purchaseRequest->id);

        $params = ['inquiry_id' => $inquiryId];
        if ($createType === 'po') {
            $params['document_type'] = 'purchase_order';
        }

        return redirect()->route('plant.purchases.create', $params)
            ->with('success', $count === 1
                ? 'Product converted. Complete the details below.'
                : "{$count} products from {$plantName} merged. Complete the details below.");
    }

    /**
     * Put the selected products on a single purchase request so one PO can be created.
     *
     * @param  Collection<int, ProductRequestItem>  $items
     */
    protected function consolidateItemsOntoPurchaseRequest(Collection $items): PurchaseRequest
    {
        return DB::transaction(function () use ($items) {
            $items->each(fn (ProductRequestItem $item) => $item->loadMissing([
                'productRequest',
                'purchaseRequestItem.purchaseRequest',
            ]));

            $existingPurchaseRequests = $items
                ->map(fn (ProductRequestItem $item) => $item->purchaseRequestItem?->purchaseRequest)
                ->filter()
                ->unique('id')
                ->values();

            if ($existingPurchaseRequests->count() === 1) {
                $existing = $existingPurchaseRequests->first();
                $existingItemIds = $existing->items()->pluck('product_request_item_id')->map(fn ($id) => (int) $id)->sort()->values();
                $selectedIds = $items->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();

                if ($existingItemIds->all() === $selectedIds->all() && $existing->quotations()->doesntExist() && $existing->purchaseOrders()->doesntExist()) {
                    return $existing;
                }
            }

            $purchaseRequest = $this->createPurchaseRequestForItems($items);

            foreach ($items as $item) {
                if ($item->purchaseRequestItem) {
                    $item->purchaseRequestItem->update([
                        'purchase_request_id' => $purchaseRequest->id,
                        'supplier_id' => $item->supplier_id ?? $item->purchaseRequestItem->supplier_id,
                    ]);

                    continue;
                }

                PurchaseRequestItem::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'product_request_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'item_name' => $item->product_id ? null : $item->displayName(),
                    'size' => $item->size,
                    'sub_category_id' => $item->sub_category_id,
                    'supplier_id' => $item->supplier_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price ?? 0,
                ]);
            }

            foreach ($existingPurchaseRequests as $old) {
                if ((int) $old->id === (int) $purchaseRequest->id) {
                    continue;
                }

                if ($old->items()->exists() || $old->purchases()->exists()) {
                    continue;
                }

                $old->delete();
            }

            $productRequests = $items->map->productRequest->filter()->unique('id');
            foreach ($productRequests as $originRequest) {
                $stillOpen = $originRequest->items()->whereDoesntHave('purchaseRequestItem')->exists();

                $originRequest->update([
                    'status' => $stillOpen ? RequestStatus::InProgress : RequestStatus::Completed,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);
            }

            return $purchaseRequest;
        });
    }

    /**
     * @param  Collection<int, ProductRequestItem>  $items
     */
    protected function createPurchaseRequestForItems(Collection $items): PurchaseRequest
    {
        $productRequests = $items->map->productRequest->filter()->unique('id');
        $primary = $productRequests->first();
        $remarks = $productRequests
            ->pluck('remarks')
            ->filter()
            ->unique()
            ->implode(' | ');

        if ($productRequests->count() > 1) {
            $ids = $productRequests->pluck('id')->implode(', ');
            $remarks = trim(($remarks ? $remarks.' | ' : '').'Merged product requests #'.$ids);
        }

        return PurchaseRequest::create([
            'plant_id' => $primary->plant_id,
            'product_request_id' => $primary->id,
            'requested_by' => auth()->id(),
            'plant_status' => RequestStatus::Completed,
            'status' => RequestStatus::Submitted,
            'remarks' => $remarks ?: null,
        ]);
    }

    /**
     * @return Collection<int, ProductRequestItem>|RedirectResponse
     */
    protected function loadSelectedProductRequestItems(Request $request): Collection|RedirectResponse
    {
        $data = $request->validate([
            'product_request_item_ids' => ['required', 'array', 'min:1'],
            'product_request_item_ids.*' => ['integer', 'exists:product_request_items,id'],
        ]);

        $itemIds = collect($data['product_request_item_ids'])->unique()->values()->all();

        $items = ProductRequestItem::with([
            'product.unit',
            'product.subCategory',
            'subCategory',
            'productRequest.plant',
            'productRequest.requester',
            'purchaseRequestItem.purchaseRequest.purchases.items',
        ])
            ->whereIn('id', $itemIds)
            ->orderBy('id')
            ->get();

        if ($items->count() !== count($itemIds)) {
            return back()->with('error', 'One or more selected products were not found.');
        }

        foreach ($items as $item) {
            $this->authorizePlantAccess($item->productRequest);
        }

        return $items;
    }

    protected function applyItemWorkflowStatusFilter($query, string $status): void
    {
        $hasQuotation = fn ($q) => $q->where('document_type', \App\Enums\PurchaseDocumentType::Quotation);
        $hasPurchaseOrder = fn ($q) => $q->where('document_type', \App\Enums\PurchaseDocumentType::PurchaseOrder);

        match ($status) {
            ProductRequestItemWorkflowStatus::Pending->value => $query
                ->whereNot('product_requests.status', RequestStatus::Cancelled)
                ->whereNull('product_request_items.exported_at')
                ->whereDoesntHave('purchaseRequestItem.purchaseRequest.purchases'),
            ProductRequestItemWorkflowStatus::AddQuotation->value => $query
                ->whereNot('product_requests.status', RequestStatus::Cancelled)
                ->whereNotNull('product_request_items.exported_at')
                ->whereDoesntHave('purchaseRequestItem.purchaseRequest.purchases'),
            ProductRequestItemWorkflowStatus::CreatePo->value => $query
                ->whereHas('purchaseRequestItem.purchaseRequest.purchases', $hasQuotation)
                ->whereDoesntHave('purchaseRequestItem.purchaseRequest.purchases', $hasPurchaseOrder),
            ProductRequestItemWorkflowStatus::PoCreated->value => $query
                ->whereHas('purchaseRequestItem.purchaseRequest.purchases', fn ($q) => $hasPurchaseOrder($q)->whereNull('dispatched_at'))
                ->whereDoesntHave('purchaseRequestItem.purchaseRequest.purchases', fn ($q) => $hasPurchaseOrder($q)->whereNotNull('dispatched_at')),
            ProductRequestItemWorkflowStatus::Dispatch->value => $query
                ->whereHas('purchaseRequestItem.purchaseRequest.purchases', $hasPurchaseOrder),
            default => null,
        };
    }
}
