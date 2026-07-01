<?php

namespace App\Http\Controllers\Site\RequestPo;

use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Concerns\BelongsToUserSite;
use App\Http\Controllers\Concerns\CreatesPurchaseOrders;
use App\Http\Controllers\Concerns\StreamsPurchasePdf;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\ErpDatabaseNotification;
use App\Services\NotificationService;
use App\Services\PurchasePricingService;
use App\Services\StockService;
use App\Support\PermissionSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PurchaseController extends Controller
{
    use AuthorizesPermissions, BelongsToUserSite, CreatesPurchaseOrders, StreamsPurchasePdf;

    public function __construct(
        protected StockService $stockService,
        protected PurchasePricingService $pricing,
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $isPurchaser = $this->purchaserSeesAllSites();
        $canReceive = auth()->user()->canManageSiteStocks() && ! $isPurchaser;

        $purchases = Purchase::with(['supplier', 'site', 'items.product'])
            ->when(! $isPurchaser, fn ($q) => $q->where('site_id', $this->siteId()))
            ->when($canReceive, fn ($q) => $q->whereIn('status', [PurchaseStatus::Ordered, PurchaseStatus::Partial]))
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
            ->latest('ordered_at')
            ->paginate(20)
            ->withQueryString();

        return view('site.request-po.purchases.index', [
            'purchases' => $purchases,
            'isPurchaser' => $isPurchaser,
            'canReceive' => $canReceive,
            'pdfDefaults' => [
                'company_name' => config('company.name', ''),
                'company_address' => config('company.address', ''),
                'company_gstin' => config('company.gstin', ''),
            ],
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::SITE_MANAGE_PURCHASES);

        $purchaseRequest = $request->filled('purchase_request_id')
            ? PurchaseRequest::with(['items.product', 'items.supplier', 'site', 'productRequest.items'])->findOrFail($request->integer('purchase_request_id'))
            : null;

        if ($purchaseRequest?->purchases()->exists()) {
            $purchases = $purchaseRequest->purchases()->latest()->get();

            if ($purchases->count() === 1) {
                return redirect()->route('site.purchases.show', $purchases->first())
                    ->with('info', 'Purchase order(s) already exist for this request.');
            }

            return redirect()->route('site.purchases.index', ['purchase_request_id' => $purchaseRequest->id])
                ->with('info', 'Purchase order(s) already exist for this request.');
        }

        return view('site.request-po.purchases.create', [
            'sites' => Site::orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'purchaseRequests' => PurchaseRequest::whereNot('status', RequestStatus::Cancelled)
                ->whereDoesntHave('purchases')
                ->with('site')
                ->latest()
                ->get(),
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::SITE_MANAGE_PURCHASES);

        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
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
            'items.*.gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($data['purchase_request_id'] ?? null) {
            $purchaseRequest = PurchaseRequest::findOrFail($data['purchase_request_id']);
            if ($purchaseRequest->purchases()->exists()) {
                return back()->with('error', 'Purchase order(s) already exist for this request.');
            }
        }

        $isInterstate = $request->boolean('is_interstate');
        $itemsBySupplier = collect($data['items'])->groupBy('supplier_id');
        $createdPurchases = [];

        foreach ($itemsBySupplier as $supplierId => $items) {
            $createdPurchases[] = $this->createPurchaseForSupplier(
                pricing: $this->pricing,
                siteId: (int) $data['site_id'],
                supplierId: (int) $supplierId,
                items: $items,
                isInterstate: $isInterstate,
                purchaseRequestId: $data['purchase_request_id'] ?? null,
                referencePrefix: $data['reference_no'] ?? null,
                supplierInvoiceNo: $data['supplier_invoice_no'] ?? null,
                supplierInvoiceDate: $data['supplier_invoice_date'] ?? null,
                notes: $data['notes'] ?? null,
                multiSupplier: $itemsBySupplier->count() > 1,
            );
        }

        if ($data['purchase_request_id'] ?? null) {
            PurchaseRequest::where('id', $data['purchase_request_id'])->update([
                'status' => RequestStatus::Completed,
                'site_status' => RequestStatus::Completed,
            ]);
        }

        foreach ($createdPurchases as $purchase) {
            $this->notifications->notifyUsers(
                User::query()
                    ->where('site_id', $purchase->site_id)
                    ->where('is_active', true)
                    ->whereHas('role', fn ($q) => $q->whereIn('slug', [
                        Role::SLUG_SITE_IN_CHARGE,
                        Role::SLUG_PURCHASER,
                    ]))
                    ->get(),
                new ErpDatabaseNotification(
                    type: 'purchase_ordered',
                    title: 'Purchase order placed',
                    body: "PO {$purchase->reference_no} ordered — awaiting dispatch.",
                    url: route('site.purchases.index'),
                    siteId: $purchase->site_id,
                )
            );
        }

        if (count($createdPurchases) === 1) {
            return redirect()->route('site.purchases.show', $createdPurchases[0])
                ->with('success', 'Purchase order created.');
        }

        $refs = collect($createdPurchases)->pluck('reference_no')->implode(', ');

        return redirect()->route('site.purchases.index', [
            'purchase_request_id' => $data['purchase_request_id'] ?? null,
        ])->with('success', count($createdPurchases)." purchase orders created: {$refs}");
    }

    public function show(Purchase $purchase): View
    {
        $this->authorizeSiteAccess($purchase);
        $purchase->load(['site', 'supplier', 'items.product', 'purchaseRequest', 'receiver', 'dispatcher']);

        return view('site.request-po.purchases.show', [
            'purchase' => $purchase,
            'pdfDefaults' => $this->purchasePdfDefaults($purchase),
            'pdfRoute' => route('site.purchases.pdf', $purchase),
            'emailRoute' => route('site.purchases.email', $purchase),
            'shareLinksRoute' => route('site.purchases.share-links', $purchase),
            'pdfFilename' => $this->purchasePdfFilename($purchase),
            'supplier' => $purchase->supplier,
        ]);
    }

    public function pdf(Request $request, Purchase $purchase): Response
    {
        $this->authorizeSiteAccess($purchase);

        return $this->streamPurchasePdf($request, $purchase);
    }

    public function sharedPdf(Request $request, Purchase $purchase): Response
    {
        return $this->streamPurchasePdf($request, $purchase);
    }

    public function shareLinks(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizeSiteAccess($purchase);

        return $this->purchaseShareLinksResponse($request, $purchase, 'site.purchases.shared-pdf');
    }

    public function email(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizeSiteAccess($purchase);

        return $this->emailPurchasePdfToSupplier($request, $purchase);
    }

    public function edit(Purchase $purchase): View|RedirectResponse
    {
        $this->authorizeSiteAccess($purchase);
        $this->authorizePermission(PermissionSlug::SITE_DISPATCH_ORDERS);

        if (! in_array($purchase->status, [PurchaseStatus::Ordered, PurchaseStatus::Partial])) {
            return redirect()->route('site.purchases.index')
                ->with('error', 'Only pending orders can be edited.');
        }

        $purchase->load(['items.product', 'supplier']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('site.request-po.purchases.edit', compact('purchase', 'suppliers'));
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizeSiteAccess($purchase);
        $this->authorizePermission(PermissionSlug::SITE_DISPATCH_ORDERS);

        if (! in_array($purchase->status, [PurchaseStatus::Ordered, PurchaseStatus::Partial])) {
            return redirect()->route('site.purchases.index')
                ->with('error', 'Only pending orders can be edited.');
        }

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'is_interstate' => ['boolean'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:purchase_items,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $isInterstate = $request->boolean('is_interstate');
        $lineCalcs = [];
        $itemsToUpdate = [];

        foreach ($data['items'] as $itemData) {
            $purchaseItem = $purchase->items()->find($itemData['id']);
            if (! $purchaseItem) {
                return back()->with('error', 'Invalid purchase item ID.')->withInput();
            }

            $calc = $this->pricing->calculateLine(
                (float) $purchaseItem->quantity,
                (float) $itemData['unit_price'],
                (float) $purchaseItem->gst_rate,
                $isInterstate
            );

            $lineCalcs[] = $calc;
            $itemsToUpdate[] = [
                'model' => $purchaseItem,
                'unit_price' => $itemData['unit_price'],
                'calc' => $calc,
            ];
        }

        $totals = $this->pricing->sumLines($lineCalcs);

        DB::transaction(function () use ($purchase, $data, $isInterstate, $totals, $itemsToUpdate) {
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
                $itemUpdate['model']->update(array_merge([
                    'unit_price' => $itemUpdate['unit_price'],
                ], $itemUpdate['calc']));
            }
        });

        return redirect()->route('site.purchases.index')
            ->with('success', 'Purchase details updated successfully.');
    }

    public function receiveForm(Purchase $purchase): View|RedirectResponse
    {
        $this->authorizeSiteAccess($purchase);
        $this->authorizePurchaseReceipt();

        if ($purchase->status === PurchaseStatus::Delivered) {
            return redirect()->route('site.purchases.index')
                ->with('error', 'Purchase already received.');
        }

        $purchase->load(['items.product', 'supplier']);

        return view('site.request-po.purchases.receive', compact('purchase'));
    }

    public function receive(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizeSiteAccess($purchase);
        $this->authorizePurchaseReceipt();

        if ($purchase->status === PurchaseStatus::Delivered) {
            return back()->with('error', 'Purchase already received.');
        }

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $lines = collect($data['lines'])->filter(fn ($l) => (float) $l['quantity'] > 0)->values()->all();

        if (empty($lines)) {
            return back()->with('error', 'Enter at least one quantity to receive.');
        }

        $this->stockService->recordInFromPurchase($purchase, $lines, (int) auth()->id());

        $purchase->update([
            'status' => PurchaseStatus::Delivered,
            'delivered_at' => now(),
            'received_by' => auth()->id(),
            'received_at' => now(),
        ]);

        return redirect()->route('site.purchases.index')
            ->with('success', 'Stock in recorded for this purchase.');
    }
}
