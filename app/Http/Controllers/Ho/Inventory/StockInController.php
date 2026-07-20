<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Enums\PurchaseStatus;
use App\Enums\StockReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Plant;
use App\Models\Supplier;
use App\Services\PurchasePricingService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockInController extends Controller
{
    public function __construct(
        protected StockService $stockService,
        protected PurchasePricingService $pricing
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $selectedPurchase = $request->filled('purchase_id')
            ? Purchase::with(['items.product', 'plant', 'supplier'])->find($request->integer('purchase_id'))
            : null;

        if ($selectedPurchase?->status === PurchaseStatus::Delivered) {
            return redirect()->route('ho.purchases.show', $selectedPurchase)
                ->with('info', 'This purchase order is already fully received.');
        }

        return view('ho.inventory.stocks.stock-in', [
            'plants' => Plant::orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'purchases' => Purchase::with(['plant', 'supplier', 'items'])
                ->whereIn('status', [PurchaseStatus::Ordered, PurchaseStatus::Partial])
                ->latest()
                ->limit(50)
                ->get(),
            'selectedPurchase' => $selectedPurchase,
            'canEditPurchase' => $selectedPurchase !== null
                && in_array($selectedPurchase->status, [PurchaseStatus::Ordered, PurchaseStatus::Partial], true),
        ]);
    }

    public function updatePurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        if ($purchase->status === PurchaseStatus::Delivered) {
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
                    ], $itemUpdate['calc']));
                } else {
                    PurchaseItem::create(array_merge([
                        'purchase_id' => $purchase->id,
                        'product_id' => $itemUpdate['product_id'],
                        'quantity' => $itemUpdate['quantity'],
                        'unit_price' => $itemUpdate['unit_price'],
                        'gst_rate' => $itemUpdate['gst_rate'],
                        'received_quantity' => 0,
                    ], $itemUpdate['calc']));
                }
            }

            $purchase->items()
                ->whereNotIn('id', $submittedIds)
                ->where('received_quantity', '<=', 0)
                ->delete();
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

        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'photo_confirmed' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $meta = $this->buildStockMeta($request, $data, photoConfirmed: $request->boolean('photo_confirmed'));

        $this->stockService->recordIn(
            (int) $data['plant_id'],
            (int) $data['product_id'],
            (float) $data['quantity'],
            (int) auth()->id(),
            StockReferenceType::StockIn,
            null,
            $meta
        );

        return redirect()->route('ho.stocks.index', ['plant_id' => $data['plant_id']])
            ->with('success', 'Stock in recorded.');
    }

    protected function storeFromPurchase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'exists:purchases,id'],
            'photo_confirmed' => ['accepted'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase = Purchase::with(['items.product'])->findOrFail($data['purchase_id']);

        if ($purchase->status === PurchaseStatus::Delivered) {
            return back()->with('error', 'This purchase order is already fully received.');
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

            $pending = (float) $item->quantity - (float) $item->received_quantity;
            if ((float) $line['quantity'] > $pending + 0.0001) {
                return back()->with('error', 'Receive quantity cannot exceed pending quantity for '.$item->product->name.'.');
            }
        }

        $meta = $this->buildStockMeta($request, $data, $purchase);

        $this->stockService->recordInFromPurchase($purchase, $lines, (int) auth()->id(), $meta);

        $purchase->refresh()->load('items');
        $fullyReceived = $purchase->items->every(
            fn ($item) => (float) $item->received_quantity >= (float) $item->quantity
        );

        $purchase->update([
            'status' => $fullyReceived ? PurchaseStatus::Delivered : PurchaseStatus::Partial,
            'received_by' => auth()->id(),
            'received_at' => now(),
            'delivered_at' => $fullyReceived ? now() : $purchase->delivered_at,
        ]);

        return redirect()->route('ho.purchases.show', $purchase)
            ->with('success', $fullyReceived
                ? 'All items received and stock in recorded.'
                : 'Partial stock in recorded for this purchase order.');
    }

    protected function buildStockMeta(Request $request, array $data, ?Purchase $purchase = null, bool $photoConfirmed = true): array
    {
        $meta = [
            'photo_confirmed' => $photoConfirmed,
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
