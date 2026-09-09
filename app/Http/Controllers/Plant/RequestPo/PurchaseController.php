<?php

namespace App\Http\Controllers\Plant\RequestPo;

use App\Enums\PurchaseDocumentType;
use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Concerns\CreatesPurchaseOrders;
use App\Http\Controllers\Concerns\StreamsPurchasePdf;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Plant;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\ErpDatabaseNotification;
use App\Services\NotificationService;
use App\Services\PlantDocumentNumberService;
use App\Services\PurchasePricingService;
use App\Services\StockService;
use App\Support\PermissionSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PurchaseController extends Controller
{
    use AuthorizesPermissions, BelongsToUserPlant, CreatesPurchaseOrders, StreamsPurchasePdf;

    public function __construct(
        protected StockService $stockService,
        protected PurchasePricingService $pricing,
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $isPurchaser = $this->purchaserSeesAllPlants();

        $baseQuery = function () use ($request, $isPurchaser) {
            return Purchase::with([
                'supplier',
                'plant',
                'items.product',
                'purchaseRequest.productRequest',
                'convertedOrders',
            ])
                ->when(! $isPurchaser, fn ($q) => $q->where('plant_id', $this->plantId()))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $search = $request->string('search');
                    $q->where(function ($q) use ($search) {
                        $q->where('reference_no', 'like', '%'.$search.'%')
                            ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('items.product', fn ($pq) => $pq->where('name', 'like', '%'.$search.'%')
                                ->orWhere('sku', 'like', '%'.$search.'%'));
                    });
                })
                ->when($request->filled('from_date'), fn ($q) => $q->whereDate('ordered_at', '>=', $request->date('from_date')))
                ->when($request->filled('to_date'), fn ($q) => $q->whereDate('ordered_at', '<=', $request->date('to_date')))
                ->when($request->filled('purchase_request_id'), fn ($q) => $q->where('purchase_request_id', $request->integer('purchase_request_id')))
                ->latest('ordered_at');
        };

        if ($isPurchaser) {
            $quotations = $baseQuery()->quotations()->paginate(20, ['*'], 'quotations_page')->withQueryString();
            $purchaseOrders = $baseQuery()->purchaseOrders()->paginate(20, ['*'], 'po_page')->withQueryString();

            return view('plant.request-po.purchases.index', [
                'quotations' => $quotations,
                'purchaseOrders' => $purchaseOrders,
                'purchases' => $purchaseOrders,
                'isPurchaser' => $isPurchaser,
                'pdfDefaults' => [
                    'company_name' => config('company.name', ''),
                    'company_address' => config('company.address', ''),
                    'company_gstin' => config('company.gstin', ''),
                ],
            ]);
        }

        $purchases = $baseQuery()->purchaseOrders()->paginate(20)->withQueryString();

        return view('plant.request-po.purchases.index', [
            'quotations' => collect(),
            'purchaseOrders' => $purchases,
            'purchases' => $purchases,
            'isPurchaser' => $isPurchaser,
            'pdfDefaults' => [
                'company_name' => config('company.name', ''),
                'company_address' => config('company.address', ''),
                'company_gstin' => config('company.gstin', ''),
            ],
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASES);

        $sourceQuotation = $request->filled('quotation_id')
            ? Purchase::with(['items.product', 'supplier', 'plant', 'purchaseRequest.items.product', 'purchaseRequest.items.productRequestItem.product', 'purchaseRequest.items.subCategory', 'purchaseRequest.items.supplier', 'convertedOrders'])
                ->quotations()
                ->findOrFail($request->integer('quotation_id'))
            : null;

        if ($sourceQuotation) {
            $this->authorizePlantAccess($sourceQuotation);

            if ($sourceQuotation->hasConvertedPurchaseOrder()) {
                $orders = $sourceQuotation->convertedOrders;

                if ($orders->count() === 1) {
                    return redirect()->route('plant.purchases.show', $orders->first())
                        ->with('info', 'Purchase order already exists for this quotation.');
                }

                return redirect()->route('plant.purchases.index')
                    ->with('info', 'Purchase order(s) already exist for this quotation.');
            }
        }

        $purchaseRequest = $sourceQuotation?->purchaseRequest
            ?? ($request->filled('purchase_request_id')
                ? PurchaseRequest::with([
                    'items.product',
                    'items.productRequestItem.product',
                    'items.subCategory',
                    'items.supplier',
                    'plant',
                    'productRequest.items',
                ])->findOrFail($request->integer('purchase_request_id'))
                : null);

        if ($purchaseRequest && ! $sourceQuotation) {
            $this->syncPurchaseRequestItemsFromCatalog($purchaseRequest);
            $purchaseRequest->load([
                'items.product',
                'items.productRequestItem.product',
                'items.subCategory',
                'items.supplier',
            ]);

            if ($purchaseRequest->purchaseOrders()->exists()) {
                $orders = $purchaseRequest->purchaseOrders()->latest()->get();

                if ($orders->count() === 1) {
                    return redirect()->route('plant.purchases.show', $orders->first())
                        ->with('info', 'Purchase order(s) already exist for this request.');
                }

                return redirect()->route('plant.purchases.index', ['purchase_request_id' => $purchaseRequest->id])
                    ->with('info', 'Purchase order(s) already exist for this request.');
            }

            if ($purchaseRequest->quotations()->exists()) {
                $quotation = $purchaseRequest->quotations()->latest()->first();

                return redirect()->route('plant.purchases.create', ['quotation_id' => $quotation->id])
                    ->with('info', 'Quotation already exists. Create the PO from it.');
            }
        }

        $requestedDocType = $request->string('document_type')->toString();
        $documentType = $requestedDocType === 'purchase_order'
            ? PurchaseDocumentType::PurchaseOrder
            : ($sourceQuotation
                ? PurchaseDocumentType::PurchaseOrder
                : ($purchaseRequest ? PurchaseDocumentType::Quotation : PurchaseDocumentType::PurchaseOrder));

        $plantId = $sourceQuotation?->plant_id ?? $purchaseRequest?->plant_id;

        return view('plant.request-po.purchases.create', [
            'plants' => Plant::orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'purchaseRequest' => $purchaseRequest,
            'sourceQuotation' => $sourceQuotation,
            'documentType' => $documentType,
            'nextQuotationNo' => $plantId
                ? app(PlantDocumentNumberService::class)->peekNextReferenceNo((int) $plantId)
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_MANAGE_PURCHASES);

        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
            'quotation_id' => ['nullable', 'exists:purchases,id'],
            'document_type' => ['nullable', Rule::enum(PurchaseDocumentType::class)],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'is_interstate' => ['boolean'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.supplier_id' => ['required', 'exists:suppliers,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $sourceQuotation = null;
        if ($data['quotation_id'] ?? null) {
            $sourceQuotation = Purchase::quotations()->findOrFail($data['quotation_id']);
            $this->authorizePlantAccess($sourceQuotation);

            if ($sourceQuotation->hasConvertedPurchaseOrder()) {
                return back()->with('error', 'Purchase order(s) already exist for this quotation.');
            }

            $data['purchase_request_id'] = $sourceQuotation->purchase_request_id;
            $data['plant_id'] = $sourceQuotation->plant_id;
        }

        $documentType = $sourceQuotation
            ? PurchaseDocumentType::PurchaseOrder
            : PurchaseDocumentType::tryFrom((string) ($data['document_type'] ?? ''))
                ?? (($data['purchase_request_id'] ?? null)
                    ? PurchaseDocumentType::Quotation
                    : PurchaseDocumentType::PurchaseOrder);

        if (($data['purchase_request_id'] ?? null) && ! $sourceQuotation) {
            $purchaseRequest = PurchaseRequest::findOrFail($data['purchase_request_id']);
            if ($purchaseRequest->purchases()->exists()) {
                return back()->with('error', 'Quotation or purchase order already exists for this request.');
            }
        }

        $isInterstate = $request->boolean('is_interstate');
        $itemsBySupplier = collect($data['items'])->groupBy('supplier_id');
        $createdPurchases = [];

        foreach ($itemsBySupplier as $supplierId => $items) {
            $createdPurchases[] = $this->createPurchaseForSupplier(
                pricing: $this->pricing,
                plantId: (int) $data['plant_id'],
                supplierId: (int) $supplierId,
                items: $items,
                isInterstate: $isInterstate,
                purchaseRequestId: $data['purchase_request_id'] ?? null,
                referencePrefix: $data['reference_no'] ?? null,
                supplierInvoiceNo: $data['supplier_invoice_no'] ?? null,
                supplierInvoiceDate: $data['supplier_invoice_date'] ?? null,
                notes: $data['notes'] ?? null,
                multiSupplier: $itemsBySupplier->count() > 1,
                documentType: $documentType,
                quotationId: $sourceQuotation?->id,
            );
        }

        if ($documentType === PurchaseDocumentType::PurchaseOrder && ($data['purchase_request_id'] ?? null)) {
            PurchaseRequest::where('id', $data['purchase_request_id'])->update([
                'status' => RequestStatus::Completed,
                'plant_status' => RequestStatus::Completed,
            ]);
        }

        foreach ($createdPurchases as $purchase) {
            $isQuote = $purchase->isQuotation();
            $this->notifications->notifyUsers(
                User::query()
                    ->where('plant_id', $purchase->plant_id)
                    ->where('is_active', true)
                    ->whereHas('role', fn ($q) => $q->whereIn('slug', [
                        Role::SLUG_PLANT_IN_CHARGE,
                        Role::SLUG_PURCHASER,
                    ]))
                    ->get(),
                new ErpDatabaseNotification(
                    type: $isQuote ? 'quotation_created' : 'purchase_ordered',
                    title: $isQuote ? 'Quotation created' : 'Purchase order placed',
                    body: $isQuote
                        ? "Quotation {$purchase->reference_no} created — create PO when ready."
                        : "PO {$purchase->reference_no} ordered — awaiting dispatch.",
                    url: route('plant.purchases.index'),
                    plantId: $purchase->plant_id,
                )
            );
        }

        $label = $documentType === PurchaseDocumentType::Quotation ? 'quotation' : 'purchase order';

        if (count($createdPurchases) === 1) {
            return redirect()->route('plant.purchases.show', $createdPurchases[0])
                ->with('success', ucfirst($label).' created.');
        }

        $refs = collect($createdPurchases)->pluck('reference_no')->implode(', ');

        return redirect()->route('plant.purchases.index', [
            'purchase_request_id' => $data['purchase_request_id'] ?? null,
        ])->with('success', count($createdPurchases)." {$label}s created: {$refs}");
    }

    public function show(Purchase $purchase): View
    {
        $this->authorizePlantAccess($purchase);
        $purchase->load(['plant', 'supplier', 'items.product', 'purchaseRequest', 'receiver', 'dispatcher']);

        return view('plant.request-po.purchases.show', [
            'purchase' => $purchase,
            'pdfDefaults' => $this->purchasePdfDefaults($purchase),
            'pdfRoute' => route('plant.purchases.pdf', $purchase),
            'excelRoute' => route('plant.purchases.excel', $purchase),
            'wordRoute' => route('plant.purchases.word', $purchase),
            'emailRoute' => route('plant.purchases.email', $purchase),
            'shareLinksRoute' => route('plant.purchases.share-links', $purchase),
            'pdfFilename' => $this->purchasePdfFilename($purchase),
            'supplier' => $purchase->supplier,
        ]);
    }

    public function pdf(Request $request, Purchase $purchase): Response
    {
        $this->authorizePlantAccess($purchase);

        return $this->streamPurchasePdf($request, $purchase);
    }

    public function excel(Request $request, Purchase $purchase): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizePlantAccess($purchase);

        return $this->streamPurchaseExcel($request, $purchase);
    }

    public function word(Request $request, Purchase $purchase): Response
    {
        $this->authorizePlantAccess($purchase);

        return $this->streamPurchaseWord($request, $purchase);
    }

    public function sharedPdf(Request $request, Purchase $purchase): Response
    {
        return $this->streamPurchasePdf($request, $purchase);
    }

    public function shareLinks(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizePlantAccess($purchase);

        return $this->purchaseShareLinksResponse($request, $purchase, 'plant.purchases.shared-pdf');
    }

    public function email(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizePlantAccess($purchase);

        return $this->emailPurchasePdfToSupplier($request, $purchase);
    }

    public function edit(Purchase $purchase): View|RedirectResponse
    {
        $this->authorizePlantAccess($purchase);
        $this->authorizePurchaseEdit();

        if (! $purchase->canBeEdited()) {
            return redirect()->route('plant.purchases.index')
                ->with('error', $purchase->isQuotation() && $purchase->hasConvertedPurchaseOrder()
                    ? 'This quotation has already been converted to a PO.'
                    : 'Only pending quotations/orders can be edited.');
        }

        $purchase->load(['items.product', 'supplier']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('plant.request-po.purchases.edit', compact('purchase', 'suppliers', 'products'));
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizePlantAccess($purchase);
        $this->authorizePurchaseEdit();

        if (! $purchase->canBeEdited()) {
            return redirect()->route('plant.purchases.index')
                ->with('error', 'Only pending quotations/orders can be edited.');
        }

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'is_interstate' => ['boolean'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'exists:purchase_items,id'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $isInterstate = $request->boolean('is_interstate');
        $submittedIds = collect($data['items'])->pluck('id')->filter()->all();

        $lineCalcs = [];
        $itemsToUpdate = [];
        $itemsToCreate = [];

        foreach ($data['items'] as $itemData) {
            $unitPrice = (float) ($itemData['unit_price'] ?? 0);
            $discountPercent = (float) ($itemData['discount_percent'] ?? 0);
            $productId = $itemData['product_id'] ?? null;

            if (! empty($itemData['id'])) {
                $purchaseItem = $purchase->items()->find($itemData['id']);
                if (! $purchaseItem) {
                    return back()->with('error', 'Invalid purchase item ID.')->withInput();
                }

                $quantity = (float) ($itemData['quantity'] ?? $purchaseItem->quantity);
                $gstRate = (float) ($itemData['gst_rate'] ?? $purchaseItem->gst_rate);

                $calc = $this->pricing->calculateLine(
                    $quantity,
                    $unitPrice,
                    $gstRate,
                    $isInterstate,
                    $discountPercent
                );

                $lineCalcs[] = $calc;
                $itemsToUpdate[] = [
                    'model' => $purchaseItem,
                    'product_id' => $productId ?: $purchaseItem->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'gst_rate' => $gstRate,
                    'calc' => $calc,
                ];
            } else {
                $quantity = (float) ($itemData['quantity'] ?? 1);
                $product = $productId ? Product::find($productId) : null;
                $gstRate = (float) ($itemData['gst_rate'] ?? $product?->gst_rate ?? 18);

                $calc = $this->pricing->calculateLine(
                    $quantity,
                    $unitPrice,
                    $gstRate,
                    $isInterstate,
                    $discountPercent
                );

                $lineCalcs[] = $calc;
                $itemsToCreate[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'gst_rate' => $gstRate,
                    'calc' => $calc,
                ];
            }
        }

        $totals = $this->pricing->sumLines($lineCalcs);

        DB::transaction(function () use ($purchase, $data, $isInterstate, $totals, $submittedIds, $itemsToUpdate, $itemsToCreate) {
            $deletedItems = $purchase->items()->whereNotIn('id', $submittedIds)->get();

            foreach ($deletedItems as $deletedItem) {
                if ($purchase->purchase_request_id) {
                    $purchaseRequestId = $purchase->purchase_request_id;
                    $productId = $deletedItem->product_id;
                    $origProductId = $deletedItem->original_product_id ?? $deletedItem->product_id;

                    $priItems = PurchaseRequestItem::where('purchase_request_id', $purchaseRequestId)
                        ->where(function ($q) use ($productId, $origProductId) {
                            $q->where('product_id', $productId);
                            if ($origProductId) {
                                $q->orWhere('product_id', $origProductId);
                            }
                            $q->orWhereHas('productRequestItem', function ($prq) use ($productId, $origProductId) {
                                $prq->where('product_id', $productId);
                                if ($origProductId) {
                                    $prq->orWhere('product_id', $origProductId);
                                }
                            });
                        })
                        ->get();

                    foreach ($priItems as $pri) {
                        $productRequestItem = $pri->productRequestItem;
                        $pri->delete();

                        if ($productRequestItem && $productRequestItem->productRequest) {
                            $pr = $productRequestItem->productRequest;
                            $stillOpen = $pr->items()->whereDoesntHave('purchaseRequestItem')->exists();
                            if ($stillOpen && $pr->status === RequestStatus::Completed) {
                                $pr->update(['status' => RequestStatus::InProgress]);
                            }
                        }
                    }
                }

                $deletedItem->delete();
            }

            $purchase->update([
                'supplier_id' => $data['supplier_id'],
                'is_interstate' => $isInterstate,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'supplier_invoice_date' => $data['supplier_invoice_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'total_cgst' => $totals['total_cgst'],
                'total_sgst' => $totals['total_sgst'],
                'total_igst' => $totals['total_igst'],
                'total_gst' => $totals['total_gst'],
                'grand_total' => $totals['grand_total'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemsToUpdate as $itemUpdate) {
                $updateFields = array_merge([
                    'quantity' => $itemUpdate['quantity'],
                    'unit_price' => $itemUpdate['unit_price'],
                    'discount_percent' => $itemUpdate['discount_percent'],
                    'gst_rate' => $itemUpdate['gst_rate'],
                ], $itemUpdate['calc']);

                if (! empty($itemUpdate['product_id'])) {
                    $updateFields['product_id'] = $itemUpdate['product_id'];
                }

                $itemUpdate['model']->update($updateFields);
            }

            foreach ($itemsToCreate as $itemCreate) {
                $createFields = array_merge([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemCreate['product_id'],
                    'quantity' => $itemCreate['quantity'],
                    'unit_price' => $itemCreate['unit_price'],
                    'discount_percent' => $itemCreate['discount_percent'],
                    'gst_rate' => $itemCreate['gst_rate'],
                ], $itemCreate['calc']);

                PurchaseItem::create($createFields);
            }
        });

        return redirect()->route('plant.purchases.index')
            ->with('success', ($purchase->isQuotation() ? 'Quotation' : 'Purchase').' details updated successfully.');
    }

    public function receiveForm(Purchase $purchase): View|RedirectResponse
    {
        $this->authorizePlantAccess($purchase);
        $this->authorizePurchaseReceipt();

        if ($purchase->isQuotation()) {
            return redirect()->route('plant.purchases.index')
                ->with('error', 'Quotations cannot be received. Create a PO first.');
        }

        if ($purchase->status === PurchaseStatus::Delivered) {
            return redirect()->route('plant.purchases.index')
                ->with('error', 'Purchase already received.');
        }

        if (! $purchase->isDispatched()) {
            return redirect()->route('plant.purchases.index')
                ->with('error', 'Purchaser has not dispatched this PO yet. Stock in is blocked until dispatch is submitted.');
        }

        $purchase->load(['items.product', 'items.originalProduct', 'supplier']);

        return view('plant.request-po.purchases.receive', compact('purchase'));
    }

    public function receive(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizePlantAccess($purchase);
        $this->authorizePurchaseReceipt();

        if ($purchase->isQuotation()) {
            return back()->with('error', 'Quotations cannot be received. Create a PO first.');
        }

        if ($purchase->status === PurchaseStatus::Delivered) {
            return back()->with('error', 'Purchase already received.');
        }

        if (! $purchase->isDispatched()) {
            return back()->with('error', 'Purchaser has not dispatched this PO yet.');
        }

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase->load('items.product');

        $lines = collect($data['lines'])->filter(fn ($l) => (float) $l['quantity'] > 0)->values()->all();

        if (empty($lines)) {
            return back()->with('error', 'Enter at least one quantity to receive.');
        }

        foreach ($lines as $line) {
            $item = $purchase->items->firstWhere('product_id', (int) $line['product_id']);
            if (! $item || ! $item->isReceivableForStockIn()) {
                return back()->with('error', 'One or more items are not dispatched / receivable.');
            }
            if ((float) $line['quantity'] > $item->receivableQuantity() + 0.0001) {
                return back()->with('error', 'Receive quantity exceeds dispatched qty for '.$item->product?->name.'.');
            }
        }

        $this->stockService->recordInFromPurchase($purchase, $lines, (int) auth()->id());

        $purchase->refresh()->load('items');
        $fullyReceived = $purchase->items->every(function ($item) {
            if ($item->dispatch_status === \App\Enums\PurchaseItemDispatchStatus::NotProvided) {
                return true;
            }
            $target = (float) ($item->dispatched_quantity ?? $item->quantity);

            return (float) $item->received_quantity >= $target - 0.0001;
        });

        $purchase->update([
            'status' => $fullyReceived ? PurchaseStatus::Delivered : PurchaseStatus::Partial,
            'delivered_at' => $fullyReceived ? now() : $purchase->delivered_at,
            'received_by' => auth()->id(),
            'received_at' => now(),
        ]);

        return redirect()->route('plant.purchases.index')
            ->with('success', 'Stock in recorded for this purchase.');
    }

    protected function authorizePurchaseEdit(): void
    {
        $user = auth()->user();
        if (! $user?->canManagePlantPurchases() && ! $user?->canDispatchOrders()) {
            abort(403);
        }
    }

    protected function syncPurchaseRequestItemsFromCatalog(PurchaseRequest $purchaseRequest): void
    {
        foreach ($purchaseRequest->items as $item) {
            $source = $item->productRequestItem;
            if (! $source?->product_id) {
                continue;
            }

            $updates = [];
            if (! $item->product_id) {
                $updates['product_id'] = $source->product_id;
                $updates['item_name'] = null;
            }
            if (! $item->size && $source->size) {
                $updates['size'] = $source->size;
            }
            if (! $item->sub_category_id && $source->sub_category_id) {
                $updates['sub_category_id'] = $source->sub_category_id;
            }

            if ($updates !== []) {
                $item->update($updates);
            }
        }
    }
}
