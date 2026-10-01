<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\RequestStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

trait EditsPurchases
{
    protected function purchasePortal(): string
    {
        return 'plant';
    }

    protected function restrictPurchaseEdit(): bool
    {
        return true;
    }

    protected function showPurchaseEditForm(Purchase $purchase): View|RedirectResponse
    {
        if ($this->restrictPurchaseEdit() && ! $purchase->canBeEdited()) {
            return redirect()->route($this->purchasePortal().'.purchases.index')
                ->with('error', $purchase->isQuotation() && $purchase->hasConvertedPurchaseOrder()
                    ? 'This quotation has already been converted to a PO.'
                    : 'Only pending quotations/orders can be edited.');
        }

        $purchase->load(['items.product', 'supplier']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('plant.request-po.purchases.edit', [
            'purchase' => $purchase,
            'suppliers' => $suppliers,
            'products' => $products,
            'purchasesIndexRoute' => $this->purchasePortal().'.purchases.index',
            'purchasesUpdateRoute' => $this->purchasePortal().'.purchases.update',
        ]);
    }

    protected function savePurchaseEdit(Request $request, Purchase $purchase): RedirectResponse
    {
        if ($this->restrictPurchaseEdit() && ! $purchase->canBeEdited()) {
            return redirect()->route($this->purchasePortal().'.purchases.index')
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

                $line = $itemUpdate['model'];
                $oldProductId = (int) $line->product_id;
                $newProductId = (int) ($itemUpdate['product_id'] ?: $oldProductId);

                if ($newProductId !== $oldProductId && (float) $line->received_quantity > 0) {
                    app(StockService::class)->reassignPurchaseLineProduct(
                        $purchase,
                        $oldProductId,
                        $newProductId,
                        (float) $line->received_quantity
                    );
                }

                if (! empty($itemUpdate['product_id'])) {
                    $updateFields['product_id'] = $itemUpdate['product_id'];
                }

                $line->update($updateFields);
            }

            foreach ($itemsToCreate as $itemCreate) {
                PurchaseItem::create(array_merge([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemCreate['product_id'],
                    'quantity' => $itemCreate['quantity'],
                    'unit_price' => $itemCreate['unit_price'],
                    'discount_percent' => $itemCreate['discount_percent'],
                    'gst_rate' => $itemCreate['gst_rate'],
                ], $itemCreate['calc']));
            }

            if ($itemsToCreate !== []) {
                $purchase->syncStatusFromItems();
            }
        });

        $success = ($purchase->isQuotation() ? 'Quotation' : 'Purchase').' details updated successfully.';
        if ($itemsToCreate !== [] && ! $purchase->isQuotation()) {
            $success .= ' New items stay pending until they are dispatched and received. Items already in stock are unchanged.';
        }

        return redirect()->route($this->purchasePortal().'.purchases.index')
            ->with('success', $success);
    }
}
