<?php

namespace App\Http\Controllers\Plant\RequestPo;

use App\Enums\PurchaseItemDispatchStatus;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Concerns\AuthorizesPermissions;
use App\Http\Controllers\Concerns\BelongsToUserPlant;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Support\PermissionSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DispatchController extends Controller
{
    use AuthorizesPermissions, BelongsToUserPlant;

    public function index(Request $request): View
    {
        $isPurchaser = $this->purchaserSeesAllPlants();

        $purchases = Purchase::with(['supplier', 'plant', 'items'])
            ->purchaseOrders()
            ->when(! $isPurchaser, fn ($q) => $q->where('plant_id', $this->plantId()))
            ->when($request->get('filter') === 'pending', fn ($q) => $q->where('status', PurchaseStatus::Ordered)->whereNull('dispatched_at'))
            ->when($request->get('filter') === 'dispatched', fn ($q) => $q->whereNotNull('dispatched_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('plant.request-po.dispatch.index', compact('purchases', 'isPurchaser'));
    }

    public function show(Purchase $purchase): View|RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_DISPATCH_ORDERS);
        $this->authorizePlantAccess($purchase);

        if ($purchase->isQuotation()) {
            return redirect()->route('plant.dispatch.index')
                ->with('error', 'Quotations cannot be dispatched. Create a PO first.');
        }

        $purchase->load(['items.product', 'items.alternativeProduct', 'items.originalProduct', 'supplier', 'plant', 'dispatcher']);

        $products = Product::where('is_active', true)->orderBy('name')->get();
        $hasOpenLines = $purchase->items->contains(fn (PurchaseItem $item) => ! $item->isDispatchResolved());
        $locked = ! $hasOpenLines && (
            $purchase->isDispatched()
            || $purchase->status === PurchaseStatus::Delivered
            || $purchase->items->contains(fn (PurchaseItem $item) => (float) $item->received_quantity > 0)
        );

        return view('plant.request-po.dispatch.show', compact('purchase', 'products', 'locked', 'hasOpenLines'));
    }

    public function dispatch(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizePermission(PermissionSlug::PLANT_DISPATCH_ORDERS);
        $this->authorizePlantAccess($purchase);

        if ($purchase->isQuotation()) {
            return back()->with('error', 'Quotations cannot be dispatched. Create a PO first.');
        }

        $purchase->load('items');
        $openItems = $purchase->items->filter(fn (PurchaseItem $item) => ! $item->isDispatchResolved());

        if ($openItems->isEmpty() && (
            $purchase->status === PurchaseStatus::Delivered
            || $purchase->isDispatched()
            || $purchase->items->contains(fn (PurchaseItem $item) => (float) $item->received_quantity > 0)
        )) {
            return back()->with('error', 'Dispatch is already complete for this purchase.');
        }

        $supplement = $openItems->isNotEmpty()
            && $purchase->items->contains(fn (PurchaseItem $item) => $item->isDispatchResolved());

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:purchase_items,id'],
            'items.*.dispatch_status' => ['required', Rule::enum(PurchaseItemDispatchStatus::class)],
            'items.*.dispatched_at' => ['nullable', 'date'],
            'items.*.dispatched_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.dispatch_remark' => ['nullable', 'string', 'max:1000'],
            'items.*.alternative_product_id' => ['nullable', 'exists:products,id'],
        ]);

        $submittedIds = collect($data['items'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $requiredIds = ($supplement ? $openItems : $purchase->items)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (array_diff($requiredIds, $submittedIds) !== []) {
            return back()->with('error', $supplement
                ? 'Mark every new product line before submitting.'
                : 'Mark every product line before submitting.')->withInput();
        }

        foreach ($data['items'] as $row) {
            $item = $purchase->items->firstWhere('id', (int) $row['id']);
            if (! $item) {
                return back()->with('error', 'Invalid purchase line.')->withInput();
            }

            if ($item->isDispatchResolved()) {
                continue;
            }

            $status = PurchaseItemDispatchStatus::from($row['dispatch_status']);

            if ($status === PurchaseItemDispatchStatus::Dispatched) {
                if (blank($row['dispatched_at'] ?? null)) {
                    return back()->with('error', 'Dispatch date is required for '.$item->product?->name.'.')->withInput();
                }
            }

            if ($status === PurchaseItemDispatchStatus::NotProvided) {
                if (blank($row['dispatch_remark'] ?? null)) {
                    return back()->with('error', 'Remark is required when item is not provided: '.$item->product?->name.'.')->withInput();
                }
            }

            if ($status === PurchaseItemDispatchStatus::Alternative) {
                if (blank($row['alternative_product_id'] ?? null)) {
                    return back()->with('error', 'Select alternative product for '.$item->product?->name.'.')->withInput();
                }
                if ((int) $row['alternative_product_id'] === (int) $item->product_id) {
                    return back()->with('error', 'Alternative product must be different for '.$item->product?->name.'.')->withInput();
                }
                if (blank($row['dispatched_at'] ?? null)) {
                    return back()->with('error', 'Dispatch date is required for alternative of '.$item->product?->name.'.')->withInput();
                }
            }
        }

        DB::transaction(function () use ($purchase, $data) {
            $headerDispatchDate = null;

            foreach ($data['items'] as $row) {
                /** @var PurchaseItem $item */
                $item = $purchase->items->firstWhere('id', (int) $row['id']);
                if (! $item || $item->isDispatchResolved()) {
                    continue;
                }

                $status = PurchaseItemDispatchStatus::from($row['dispatch_status']);

                $payload = [
                    'dispatch_status' => $status,
                    'dispatched_at' => null,
                    'dispatched_quantity' => null,
                    'dispatch_remark' => $row['dispatch_remark'] ?? null,
                    'alternative_product_id' => null,
                ];

                if ($status === PurchaseItemDispatchStatus::Dispatched) {
                    $qty = (float) ($row['dispatched_quantity'] ?? $item->quantity);
                    $payload['dispatched_at'] = $row['dispatched_at'];
                    $payload['dispatched_quantity'] = min($qty, (float) $item->quantity);
                    $headerDispatchDate = max($headerDispatchDate ?? $row['dispatched_at'], $row['dispatched_at']);
                }

                if ($status === PurchaseItemDispatchStatus::NotProvided) {
                    $payload['dispatched_quantity'] = 0;
                }

                if ($status === PurchaseItemDispatchStatus::Alternative) {
                    $altId = (int) $row['alternative_product_id'];
                    $qty = (float) ($row['dispatched_quantity'] ?? $item->quantity);
                    $payload['alternative_product_id'] = $altId;
                    $payload['original_product_id'] = $item->original_product_id ?: $item->product_id;
                    $payload['product_id'] = $altId;
                    $payload['dispatched_at'] = $row['dispatched_at'];
                    $payload['dispatched_quantity'] = min($qty, (float) $item->quantity);
                    $headerDispatchDate = max($headerDispatchDate ?? $row['dispatched_at'], $row['dispatched_at']);
                }

                $item->update($payload);
            }

            $purchase->update([
                'dispatched_at' => $purchase->dispatched_at ?? ($headerDispatchDate ? $headerDispatchDate.' 12:00:00' : now()),
                'dispatched_by' => $purchase->dispatched_by ?? auth()->id(),
            ]);

            $purchase->syncStatusFromItems();
        });

        return redirect()
            ->route('plant.dispatch.show', $purchase)
            ->with('success', 'Product-wise dispatch saved. HO can now stock in dispatched items.');
    }
}
