<?php

namespace App\Http\Controllers\Ho;

use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\ErpDatabaseNotification;
use App\Services\NotificationService;
use App\Services\PurchasePricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchasePricingService $pricing,
        protected NotificationService $notifications
    ) {}

    public function index(Request $request): View
    {
        $purchases = Purchase::with(['site', 'supplier', 'purchaseRequest'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $readyForPo = PurchaseRequest::query()
            ->whereNot('status', RequestStatus::Cancelled)
            ->whereDoesntHave('purchase')
            ->count();

        return view('ho.purchases.index', [
            'purchases' => $purchases,
            'sites' => Site::orderBy('name')->get(),
            'readyForPo' => $readyForPo,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $purchaseRequest = $request->filled('purchase_request_id')
            ? PurchaseRequest::with(['items.product', 'site'])->findOrFail($request->integer('purchase_request_id'))
            : null;

        if ($purchaseRequest?->purchase) {
            return redirect()->route('ho.purchases.show', $purchaseRequest->purchase)
                ->with('info', 'A purchase order already exists for this request.');
        }

        return view('ho.purchases.create', [
            'sites' => Site::orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'purchaseRequests' => PurchaseRequest::whereNot('status', RequestStatus::Cancelled)
                ->whereDoesntHave('purchase')
                ->with('site')
                ->latest()
                ->get(),
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_request_id' => ['nullable', 'exists:purchase_requests,id'],
            'reference_no' => ['nullable', 'string', 'max:100', 'unique:purchases,reference_no'],
            'is_interstate' => ['boolean'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $isInterstate = $request->boolean('is_interstate');
        $lineCalcs = [];
        $itemsToCreate = [];

        foreach ($data['items'] as $item) {
            $calc = $this->pricing->calculateLine(
                (float) $item['quantity'],
                (float) $item['unit_price'],
                (float) ($item['gst_rate'] ?? 0),
                $isInterstate
            );
            $lineCalcs[] = $calc;
            $itemsToCreate[] = array_merge($item, $calc);
        }

        $totals = $this->pricing->sumLines($lineCalcs);

        $purchase = Purchase::create([
            'site_id' => $data['site_id'],
            'supplier_id' => $data['supplier_id'],
            'purchase_request_id' => $data['purchase_request_id'] ?? null,
            'reference_no' => $data['reference_no'] ?? 'PO-'.Str::upper(Str::random(8)),
            'status' => PurchaseStatus::Ordered,
            'is_interstate' => $isInterstate,
            'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
            'supplier_invoice_date' => $data['supplier_invoice_date'] ?? null,
            'subtotal' => $totals['subtotal'],
            'total_cgst' => $totals['total_cgst'],
            'total_sgst' => $totals['total_sgst'],
            'total_igst' => $totals['total_igst'],
            'total_gst' => $totals['total_gst'],
            'grand_total' => $totals['grand_total'],
            'ordered_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($itemsToCreate as $item) {
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'gst_rate' => $item['gst_rate'] ?? 0,
                'taxable_amount' => $item['taxable_amount'],
                'cgst_amount' => $item['cgst_amount'],
                'sgst_amount' => $item['sgst_amount'],
                'igst_amount' => $item['igst_amount'],
                'line_total' => $item['line_total'],
            ]);
        }

        if ($purchase->purchase_request_id) {
            PurchaseRequest::where('id', $purchase->purchase_request_id)->update([
                'status' => RequestStatus::Completed,
                'site_status' => RequestStatus::Completed,
            ]);
        }

        $this->notifications->notifyUsers(
            User::query()
                ->where('site_id', $purchase->site_id)
                ->where('is_active', true)
                ->whereHas('role', fn ($q) => $q->whereIn('slug', [
                    \App\Models\Role::SLUG_SITE_IN_CHARGE,
                    \App\Models\Role::SLUG_PURCHASER,
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

        return redirect()->route('ho.purchases.show', $purchase)->with('success', 'Purchase order created.');
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['site', 'supplier', 'items.product', 'purchaseRequest', 'receiver', 'dispatcher']);

        return view('ho.purchases.show', compact('purchase'));
    }
}
