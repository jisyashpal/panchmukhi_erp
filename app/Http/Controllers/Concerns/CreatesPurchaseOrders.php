<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\PurchasePricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait CreatesPurchaseOrders
{
    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    protected function createPurchaseForSupplier(
        PurchasePricingService $pricing,
        int $siteId,
        int $supplierId,
        Collection $items,
        bool $isInterstate,
        ?int $purchaseRequestId,
        ?string $referencePrefix,
        ?string $supplierInvoiceNo,
        ?string $supplierInvoiceDate,
        ?string $notes,
        bool $multiSupplier,
    ): Purchase {
        $lineCalcs = [];
        $itemsToCreate = [];

        foreach ($items as $item) {
            $calc = $pricing->calculateLine(
                (float) $item['quantity'],
                (float) $item['unit_price'],
                (float) ($item['gst_rate'] ?? 0),
                $isInterstate
            );
            $lineCalcs[] = $calc;
            $itemsToCreate[] = array_merge($item, $calc);
        }

        $totals = $pricing->sumLines($lineCalcs);
        $referenceNo = $this->resolvePurchaseReferenceNo($referencePrefix, $supplierId, $multiSupplier);

        $purchase = Purchase::create([
            'site_id' => $siteId,
            'supplier_id' => $supplierId,
            'purchase_request_id' => $purchaseRequestId,
            'reference_no' => $referenceNo,
            'status' => PurchaseStatus::Ordered,
            'is_interstate' => $isInterstate,
            'supplier_invoice_no' => $supplierInvoiceNo,
            'supplier_invoice_date' => $supplierInvoiceDate,
            'notes' => $notes,
            'subtotal' => $totals['subtotal'],
            'total_cgst' => $totals['total_cgst'],
            'total_sgst' => $totals['total_sgst'],
            'total_igst' => $totals['total_igst'],
            'total_gst' => $totals['total_gst'],
            'grand_total' => $totals['grand_total'],
            'ordered_at' => now(),
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

        return $purchase;
    }

    protected function resolvePurchaseReferenceNo(?string $prefix, int $supplierId, bool $multiSupplier): string
    {
        if ($prefix && ! $multiSupplier) {
            $referenceNo = $prefix;
        } elseif ($prefix && $multiSupplier) {
            $referenceNo = $prefix.'-S'.$supplierId;
        } else {
            $referenceNo = 'PO-'.Str::upper(Str::random(8));
        }

        while (Purchase::where('reference_no', $referenceNo)->exists()) {
            $referenceNo = 'PO-'.Str::upper(Str::random(8));
        }

        return $referenceNo;
    }
}
