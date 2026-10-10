<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Enums\PurchaseItemDispatchStatus;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Concerns\CreatesPurchaseOrders;
use App\Http\Controllers\Concerns\ListsStockMovements;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Plant;
use App\Models\Supplier;
use App\Services\PurchasePricingService;
use App\Services\StockService;
use App\Support\ReportFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockInController extends Controller
{
    use CreatesPurchaseOrders;
    use ListsStockMovements;

    public function __construct(
        protected StockService $stockService,
        protected PurchasePricingService $pricing
    ) {}

    public function create(Request $request): View|RedirectResponse|StreamedResponse
    {
        $filters = ReportFilters::fromRequest($request);

        if ($request->query('export') === 'excel') {
            return $this->exportStockMovements(StockMovementType::In, $filters);
        }

        $selectedPurchase = $request->filled('purchase_id')
            ? Purchase::with(['items.product', 'items.originalProduct', 'plant', 'supplier'])->find($request->integer('purchase_id'))
            : null;

        if ($selectedPurchase?->isQuotation()) {
            return redirect()->route('ho.stocks.stock-in')
                ->with('error', 'Quotations cannot be stocked in. Create a PO first.');
        }

        if ($selectedPurchase?->status === PurchaseStatus::Delivered && ! $selectedPurchase->hasPendingReceipt()) {
            return redirect()->route('ho.purchases.show', $selectedPurchase)
                ->with('info', 'This purchase order is already fully received.');
        }

        if ($selectedPurchase && ! $selectedPurchase->isDispatched()) {
            return redirect()->route('ho.stocks.stock-in')
                ->with('error', 'Purchaser has not dispatched PO '.$selectedPurchase->reference_no.' yet. Stock in is blocked until product-wise dispatch is submitted.');
        }

        $movements = $selectedPurchase
            ? null
            : $this->stockService->listMovements(StockMovementType::In, $filters);

        return view('ho.inventory.stocks.stock-in', [
            'plants' => Plant::orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'purchases' => Purchase::with(['plant', 'supplier', 'items'])
                ->purchaseOrders()
                ->whereNotNull('dispatched_at')
                ->whereIn('status', [PurchaseStatus::Ordered, PurchaseStatus::Partial, PurchaseStatus::Delivered])
                ->where(function ($query) {
                    $query->whereIn('status', [PurchaseStatus::Ordered, PurchaseStatus::Partial])
                        ->orWhereHas('items', function ($items) {
                            $items->whereNull('dispatch_status')
                                ->orWhere(function ($items) {
                                    $items->whereIn('dispatch_status', [
                                        PurchaseItemDispatchStatus::Dispatched->value,
                                        PurchaseItemDispatchStatus::Alternative->value,
                                    ])->whereColumn('received_quantity', '<', 'dispatched_quantity');
                                });
                        });
                })
                ->latest()
                ->limit(50)
                ->get(),
            'selectedPurchase' => $selectedPurchase,
            'canEditPurchase' => $selectedPurchase !== null
                && (
                    in_array($selectedPurchase->status, [PurchaseStatus::Ordered, PurchaseStatus::Partial], true)
                    || $selectedPurchase->hasPendingReceipt()
                ),
            'filters' => $filters,
            'movements' => $movements,
            'purchaseReferences' => $movements
                ? $this->stockService->purchaseReferencesFor($movements)
                : collect(),
        ]);
    }

    public function updatePurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        if ($purchase->isQuotation()) {
            return back()->with('error', 'Quotations cannot be stocked in. Create a PO first.');
        }

        if ($purchase->status === PurchaseStatus::Delivered && ! $purchase->hasPendingReceipt()) {
            return back()->with('error', 'This purchase order is already fully received.');
        }

        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'is_interstate' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:purchase_items,id'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $purchase->load(['items.product']);
        $isInterstate = $request->boolean('is_interstate');
        $submittedIds = [];
        $lineCalcs = [];
        $itemsToUpsert = [];
        $seenProductIds = [];

        foreach ($data['items'] as $itemData) {
            $productId = isset($itemData['id'])
                ? (int) $purchase->items->firstWhere('id', (int) $itemData['id'])?->product_id
                : 0;

            if (isset($itemData['id'])) {
                if (! $productId) {
                    return back()->with('error', 'Invalid purchase line.')->withInput();
                }
            } else {
                if (empty($itemData['product_id'])) {
                    return back()->with('error', 'Select a product for each new line.')->withInput();
                }
                $productId = (int) $itemData['product_id'];
            }

            if (! $productId) {
                return back()->with('error', 'Invalid purchase line.')->withInput();
            }

            if (in_array($productId, $seenProductIds, true)) {
                return back()->with('error', 'Duplicate product on purchase order.')->withInput();
            }
            $seenProductIds[] = $productId;

            if (isset($itemData['id'])) {
                $purchaseItem = $purchase->items->firstWhere('id', (int) $itemData['id']);
                if (! $purchaseItem || $purchaseItem->purchase_id !== $purchase->id) {
                    return back()->with('error', 'Invalid purchase item.')->withInput();
                }

                $minQty = (float) $purchaseItem->received_quantity;
                if ((float) $itemData['quantity'] < $minQty - 0.0001) {
                    $name = $purchaseItem->product?->name ?? 'item';

                    return back()->with('error', 'Ordered quantity cannot be less than already received for '.$name.'.')->withInput();
                }

                $submittedIds[] = $purchaseItem->id;
                $gstRate = (float) ($itemData['gst_rate'] ?? $purchaseItem->gst_rate);
            } else {
                if ($purchase->items->contains('product_id', $productId)) {
                    return back()->with('error', 'Product already exists on this purchase order.')->withInput();
                }
                $purchaseItem = null;
                $gstRate = (float) ($itemData['gst_rate'] ?? 18);
            }

            $calc = $this->pricing->calculateLine(
                (float) $itemData['quantity'],
                (float) $itemData['unit_price'],
                $gstRate,
                $isInterstate
            );

            $lineCalcs[] = $calc;
            $itemsToUpsert[] = [
                'model' => $purchaseItem,
                'product_id' => $productId,
                'quantity' => (float) $itemData['quantity'],
                'unit_price' => (float) $itemData['unit_price'],
                'gst_rate' => $gstRate,
                'calc' => $calc,
            ];
        }

        $totals = $this->pricing->sumLines($lineCalcs);

        DB::transaction(function () use ($purchase, $data, $isInterstate, $totals, $itemsToUpsert, $submittedIds) {
            $purchase->update([
                'supplier_id' => $data['supplier_id'] ?? $purchase->supplier_id,
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

            foreach ($itemsToUpsert as $itemUpdate) {
                if ($itemUpdate['model']) {
                    $itemUpdate['model']->update(array_merge([
                        'quantity' => $itemUpdate['quantity'],
                        'unit_price' => $itemUpdate['unit_price'],
                        'gst_rate' => $itemUpdate['gst_rate'],
                    ], $itemUpdate['calc'], $purchase->dispatchedQuantityForEditedLine($itemUpdate['model'], (float) $itemUpdate['quantity'])));
                } else {
                    PurchaseItem::create(array_merge([
                        'purchase_id' => $purchase->id,
                        'product_id' => $itemUpdate['product_id'],
                        'quantity' => $itemUpdate['quantity'],
                        'unit_price' => $itemUpdate['unit_price'],
                        'gst_rate' => $itemUpdate['gst_rate'],
                        'received_quantity' => 0,
                    ], $itemUpdate['calc'], $purchase->dispatchAttributesForAddedItem((float) $itemUpdate['quantity'])));
                }
            }

            $purchase->items()
                ->whereNotIn('id', $submittedIds)
                ->where('received_quantity', '<=', 0)
                ->delete();

            $purchase->syncStatusFromItems();
        });

        return redirect()
            ->route('ho.stocks.stock-in', ['purchase_id' => $purchase->id])
            ->with('success', 'Purchase order and bill updated.');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('purchase_id')) {
            return $this->storeFromPurchase($request);
        }

        return $this->storeManual($request);
    }

    protected function storeManual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'moved_at' => ['required', 'date'],
            'is_interstate' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'photo_confirmed' => ['required', 'boolean'],
            'reason' => [
                Rule::requiredIf(! $request->boolean('photo_confirmed')),
                'nullable',
                'string',
                'max:1000',
            ],
            'photo' => ['nullable', 'image', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $seenProductIds = [];
        foreach ($data['items'] as $item) {
            $productId = (int) $item['product_id'];
            if (in_array($productId, $seenProductIds, true)) {
                return back()->with('error', 'Duplicate product on purchase order.')->withInput();
            }
            $seenProductIds[] = $productId;
        }

        $items = collect($data['items'])->map(fn (array $item) => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (float) $item['quantity'],
            'unit_price' => (float) $item['unit_price'],
            'gst_rate' => (float) ($item['gst_rate'] ?? 18),
        ]);

        $purchase = DB::transaction(function () use ($request, $data, $items) {
            $purchase = $this->createPurchaseForSupplier(
                $this->pricing,
                (int) $data['plant_id'],
                (int) $data['supplier_id'],
                $items,
                $request->boolean('is_interstate'),
                null,
                null,
                $data['supplier_invoice_no'] ?? null,
                $data['supplier_invoice_date'] ?? null,
                $data['notes'] ?? null,
                false,
            );

            $meta = $this->buildStockMeta($request, $data, $purchase);
            $lines = $items->map(fn (array $item) => [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ])->all();

            $this->stockService->recordInFromPurchase($purchase, $lines, (int) auth()->id(), $meta, $data['moved_at']);

            $purchase->load('items');
            foreach ($purchase->items as $item) {
                $item->update([
                    'dispatch_status' => PurchaseItemDispatchStatus::Dispatched,
                    'dispatched_at' => now()->toDateString(),
                    'dispatched_quantity' => $item->quantity,
                ]);
            }

            $purchase->update([
                'status' => PurchaseStatus::Delivered,
                'dispatched_at' => now(),
                'dispatched_by' => auth()->id(),
                'received_by' => auth()->id(),
                'received_at' => $data['moved_at'],
                'delivered_at' => now(),
            ]);

            return $purchase->fresh(['items.product', 'plant', 'supplier']);
        });

        return redirect()->route('ho.stocks.stock-in')
            ->with('success', 'PO '.$purchase->reference_no.' created and stock in recorded.');
    }

    protected function storeFromPurchase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'exists:purchases,id'],
            'photo_confirmed' => ['required', 'boolean'],
            'reason' => [
                Rule::requiredIf(! $request->boolean('photo_confirmed')),
                'nullable',
                'string',
                'max:1000',
            ],
            'notes' => ['nullable', 'string'],
            'moved_at' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase = Purchase::with(['items.product'])->findOrFail($data['purchase_id']);

        if ($purchase->status === PurchaseStatus::Delivered && ! $purchase->hasPendingReceipt()) {
            return back()->with('error', 'This purchase order is already fully received.');
        }

        if (! $purchase->isDispatched()) {
            return back()->with('error', 'Purchaser has not dispatched this PO yet. Stock in is blocked until dispatch is submitted.');
        }

        $lines = collect($data['lines'])
            ->filter(fn ($line) => (float) $line['quantity'] > 0)
            ->values()
            ->all();

        if ($lines === []) {
            return back()->with('error', 'Enter at least one quantity to receive.');
        }

        foreach ($lines as $line) {
            $item = $purchase->items->firstWhere('product_id', (int) $line['product_id']);
            if (! $item) {
                return back()->with('error', 'Invalid product for this purchase order.');
            }

            if (! $item->isReceivableForStockIn()) {
                return back()->with('error', ($item->product->name ?? 'Item').' was marked not provided by supplier and cannot be stocked in.');
            }

            $pending = $item->receivableQuantity();
            if ((float) $line['quantity'] > $pending + 0.0001) {
                return back()->with('error', 'Receive quantity cannot exceed dispatched pending quantity for '.$item->product->name.'.');
            }
        }

        $meta = $this->buildStockMeta($request, $data, $purchase);

        $this->stockService->recordInFromPurchase($purchase, $lines, (int) auth()->id(), $meta, $data['moved_at']);

        $purchase->refresh()->load('items');
        $fullyReceived = $purchase->items->every(function (PurchaseItem $item) {
            if ($item->dispatch_status === PurchaseItemDispatchStatus::NotProvided) {
                return true;
            }

            $target = (float) ($item->dispatched_quantity ?? $item->quantity);

            return (float) $item->received_quantity >= $target - 0.0001;
        });

        $purchase->update([
            'status' => $fullyReceived ? PurchaseStatus::Delivered : PurchaseStatus::Partial,
            'received_by' => auth()->id(),
            'received_at' => $data['moved_at'],
            'delivered_at' => $fullyReceived ? now() : $purchase->delivered_at,
        ]);

        return redirect()->route('ho.stocks.stock-in')
            ->with('success', $fullyReceived
                ? 'All receivable items stocked in.'
                : 'Partial stock in recorded for this purchase order.');
    }

    protected function buildStockMeta(Request $request, array $data, ?Purchase $purchase = null): array
    {
        $meta = [
            'photo_confirmed' => $request->boolean('photo_confirmed'),
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? ($purchase
                ? 'Stock in from PO '.$purchase->reference_no
                : 'Stock in'),
            'supplier_id' => $data['supplier_id'] ?? ($purchase?->supplier_id ?? null),
        ];

        if ($request->hasFile('photo')) {
            $meta['photo_path'] = $request->file('photo')->store('stock-photos', 'public');
        }

        return $meta;
    }
}
