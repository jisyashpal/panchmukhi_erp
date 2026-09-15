<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PurchaseDocumentType;
use App\Enums\PurchaseStatus;
use App\Models\Plant;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\PlantDocumentNumberService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ManagesPurchaseImports
{
    public function bulkUploadForm(Request $request): View
    {
        $selectedPlantId = method_exists($this, 'plantId') ? $this->plantId() : $request->integer('plant_id');
        $portalLayout = request()->is('ho*') ? 'layouts.ho' : 'layouts.plant';

        return view('plant.request-po.purchases.bulk-upload', [
            'plants' => Plant::where('is_active', true)->orderBy('name')->get(),
            'selectedPlantId' => $selectedPlantId,
            'portalLayout' => $portalLayout,
        ]);
    }

    public function downloadBulkSample(Request $request): StreamedResponse
    {
        $plantId = method_exists($this, 'plantId') ? $this->plantId() : $request->integer('plant_id');
        $plant = $plantId ? Plant::find($plantId) : null;
        $plantCode = $plant ? ($plant->code ?: $plant->name) : 'PISKA';

        $filename = 'bulk_purchases_sample'.($plant ? '_'.Str::slug($plant->name) : '').'.csv';

        return response()->streamDownload(function () use ($plantCode) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Headers
            fputcsv($handle, [
                'Reference No',
                'Plant Code / Name',
                'Supplier Name',
                'Document Type',
                'Order Date',
                'Supplier Invoice No',
                'Supplier Invoice Date',
                'Is Interstate (1/0)',
                'Product SKU / Name',
                'Quantity',
                'Unit Price',
                'Discount %',
                'GST Rate %',
                'Notes',
            ]);

            // Sample Rows
            fputcsv($handle, [
                'PO-'.strtoupper($plantCode).'-1001',
                $plantCode,
                'Apex Tools & Machinery',
                'purchase_order',
                date('Y-m-d'),
                'INV-2026-001',
                date('Y-m-d'),
                '0',
                'SKU-001',
                '10',
                '450.00',
                '0',
                '18',
                'Bulk purchase initial batch',
            ]);

            fputcsv($handle, [
                'PO-'.strtoupper($plantCode).'-1001',
                $plantCode,
                'Apex Tools & Machinery',
                'purchase_order',
                date('Y-m-d'),
                'INV-2026-001',
                date('Y-m-d'),
                '0',
                'SKU-002',
                '5',
                '1200.00',
                '5',
                '18',
                'Second item in same purchase order',
            ]);

            fputcsv($handle, [
                'PO-'.strtoupper($plantCode).'-1002',
                $plantCode,
                'Global Industrial Corp',
                'purchase_order',
                date('Y-m-d'),
                'BILL-9082',
                date('Y-m-d'),
                '1',
                'SKU-003',
                '20',
                '250.00',
                '0',
                '12',
                'Interstate supply purchase',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function bulkUpload(Request $request): RedirectResponse
    {
        $request->validate([
            'plant_id' => ['nullable', 'exists:plants,id'],
            'auto_receive' => ['nullable', 'boolean'],
            'auto_create_suppliers' => ['nullable', 'boolean'],
            'csv_file' => [
                'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['csv', 'xlsx', 'xls', 'txt', 'ods'], true)) {
                        $fail('The uploaded file must be an Excel (.xlsx, .xls) or CSV (.csv) file.');
                    }
                },
            ],
        ]);

        $userPlantId = method_exists($this, 'plantId') ? $this->plantId() : null;
        $defaultPlantId = $request->integer('plant_id') ?: $userPlantId;
        $autoReceive = $request->boolean('auto_receive', false);
        $autoCreateSuppliers = $request->boolean('auto_create_suppliers', true);
        $performedBy = (int) auth()->id();

        $file = $request->file('csv_file');
        $filePath = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = [];
        try {
            if (in_array($extension, ['xlsx', 'xls', 'ods', 'csv'], true)) {
                $spreadsheet = IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray(null, true, true, false);
            } else {
                if (($handle = fopen($filePath, 'r')) !== false) {
                    while (($r = fgetcsv($handle)) !== false) {
                        $rows[] = $r;
                    }
                    fclose($handle);
                }
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['csv_file' => 'Failed to parse file: '.$e->getMessage()]);
        }

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'The uploaded file is empty.']);
        }

        $headers = array_shift($rows);
        if (! $headers || ! is_array($headers)) {
            return back()->withErrors(['csv_file' => 'The uploaded file is missing headers.']);
        }

        // Clean UTF-8 BOM if present
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/[\x{EF}\x{BB}\x{BF}]/u', '', (string) $headers[0]);
        }

        $normalizeHeader = fn ($header) => trim((string) preg_replace('/[^a-z0-9]+/i', ' ', strtolower(trim((string) $header))));
        $normalizedHeaders = array_map($normalizeHeader, $headers);

        $findHeader = function (array $candidates) use ($normalizedHeaders) {
            foreach ($candidates as $cand) {
                $normalizedCandidate = trim((string) preg_replace('/[^a-z0-9]+/i', ' ', strtolower($cand)));
                $pos = array_search($normalizedCandidate, $normalizedHeaders, true);
                if ($pos !== false) {
                    return $pos;
                }
            }

            return false;
        };

        $colRefNo = $findHeader(['reference no', 'reference_no', 'po number', 'po_number', 'ref no', 'ref_no', 'order no', 'quotation no']);
        $colPlant = $findHeader(['plant code', 'plant_code', 'plant name', 'plant_name', 'plant', 'site']);
        $colSupplier = $findHeader(['supplier name', 'supplier_name', 'supplier code', 'supplier_code', 'supplier', 'vendor']);
        $colDocType = $findHeader(['document type', 'document_type', 'doc type', 'type']);
        $colOrderDate = $findHeader(['order date', 'ordered_at', 'order_date', 'date']);
        $colInvoiceNo = $findHeader(['supplier invoice no', 'supplier_invoice_no', 'invoice no', 'invoice_no', 'bill no']);
        $colInvoiceDate = $findHeader(['supplier invoice date', 'supplier_invoice_date', 'invoice date', 'bill date']);
        $colInterstate = $findHeader(['is interstate', 'is_interstate', 'interstate']);
        $colProduct = $findHeader(['product name', 'product', 'item description', 'item name', 'item', 'description']);
        $colSku = $findHeader(['sku', 'product sku', 'product code', 'part no', 'part number', 'item code']);
        $colQty = $findHeader(['quantity', 'qty', 'count']);
        $colUnitPrice = $findHeader(['unit price', 'unit_price', 'price', 'rate', 'cost']);
        $colDiscount = $findHeader(['discount percent', 'discount_percent', 'discount %', 'discount']);
        $colGstRate = $findHeader(['gst rate', 'gst_rate', 'gst %', 'gst', 'tax rate']);
        $colNotes = $findHeader(['notes', 'note', 'remarks', 'remark']);

        if ($colProduct === false && $colSku === false) {
            return back()->withErrors(['csv_file' => 'File must contain a "Product SKU" or "Product Name" column.']);
        }
        if ($colQty === false) {
            return back()->withErrors(['csv_file' => 'File must contain a "Quantity" or "Qty" column.']);
        }
        if ($colUnitPrice === false) {
            return back()->withErrors(['csv_file' => 'File must contain a "Unit Price" or "Rate" column.']);
        }

        // Cache lookups
        $allPlants = Plant::all();
        $allSuppliers = Supplier::all();
        $allProducts = Product::all();
        $docNumberService = app(PlantDocumentNumberService::class);

        // Group rows into purchases
        $purchaseGroups = [];
        $rowErrors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // header is row 1
            if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                continue; // Skip empty rows
            }

            $getVal = function ($col) use ($row) {
                if ($col === false || ! isset($row[$col])) {
                    return '';
                }

                return trim((string) $row[$col]);
            };

            $refNo = $getVal($colRefNo);
            $plantStr = $getVal($colPlant);
            $supplierStr = $getVal($colSupplier);
            $docTypeStr = strtolower($getVal($colDocType));
            $orderDateStr = $getVal($colOrderDate);
            $invoiceNoStr = $getVal($colInvoiceNo);
            $invoiceDateStr = $getVal($colInvoiceDate);
            $interstateStr = strtolower($getVal($colInterstate));
            $productStr = $getVal($colProduct);
            $skuStr = $getVal($colSku);
            $qtyVal = (float) $getVal($colQty);
            $priceVal = (float) $getVal($colUnitPrice);
            $discountVal = (float) $getVal($colDiscount);
            $gstVal = (float) $getVal($colGstRate);
            $notesStr = $getVal($colNotes);

            // Resolve Plant
            $targetPlant = null;
            if (! empty($plantStr)) {
                $targetPlant = $allPlants->first(function ($p) use ($plantStr) {
                    return strcasecmp($p->code ?? '', $plantStr) === 0 || strcasecmp($p->name ?? '', $plantStr) === 0;
                });
            }
            if (! $targetPlant && $defaultPlantId) {
                $targetPlant = $allPlants->firstWhere('id', $defaultPlantId);
            }
            if (! $targetPlant) {
                $rowErrors[] = "Row {$rowNum}: Plant could not be identified (specify plant in file or select plant dropdown).";
                continue;
            }

            // Resolve Supplier
            $targetSupplier = null;
            if (! empty($supplierStr)) {
                $targetSupplier = $allSuppliers->first(function ($s) use ($supplierStr) {
                    return strcasecmp($s->name, $supplierStr) === 0
                        || ($s->phone && strcasecmp($s->phone, $supplierStr) === 0)
                        || ($s->gstin && strcasecmp($s->gstin, $supplierStr) === 0);
                });

                if (! $targetSupplier && $autoCreateSuppliers) {
                    $targetSupplier = Supplier::create([
                        'name' => $supplierStr,
                        'is_active' => true,
                    ]);
                    $allSuppliers->push($targetSupplier);
                }
            }
            if (! $targetSupplier) {
                // Try fallback to first supplier if none given
                $targetSupplier = $allSuppliers->first();
            }
            if (! $targetSupplier) {
                $rowErrors[] = "Row {$rowNum}: Supplier '{$supplierStr}' could not be resolved or created.";
                continue;
            }

            // Resolve Product
            $targetProduct = null;
            if (! empty($skuStr)) {
                $targetProduct = $allProducts->firstWhere('sku', $skuStr);
            }
            if (! $targetProduct && ! empty($productStr)) {
                $targetProduct = $allProducts->first(function ($p) use ($productStr) {
                    return strcasecmp($p->name, $productStr) === 0 || strcasecmp($p->sku ?? '', $productStr) === 0;
                });
            }
            if (! $targetProduct) {
                $rowErrors[] = "Row {$rowNum}: Product '{$productStr}' (SKU: {$skuStr}) not found in catalog.";
                continue;
            }

            if ($qtyVal <= 0) {
                $rowErrors[] = "Row {$rowNum}: Invalid quantity '{$qtyVal}'.";
                continue;
            }

            // Document type
            $docType = str_contains($docTypeStr, 'quot')
                ? PurchaseDocumentType::Quotation
                : PurchaseDocumentType::PurchaseOrder;

            // Dates
            $orderedAt = ! empty($orderDateStr) && strtotime($orderDateStr) !== false
                ? date('Y-m-d H:i:s', strtotime($orderDateStr))
                : date('Y-m-d H:i:s');

            $invoiceDate = ! empty($invoiceDateStr) && strtotime($invoiceDateStr) !== false
                ? date('Y-m-d', strtotime($invoiceDateStr))
                : null;

            $isInterstate = in_array($interstateStr, ['1', 'true', 'yes', 'y'], true);

            // Define Group Key for Purchase Order header
            $groupKey = ! empty($refNo)
                ? 'REF:'.$refNo
                : (! empty($invoiceNoStr)
                    ? 'INV:'.$invoiceNoStr.'|'.$targetPlant->id.'|'.$targetSupplier->id
                    : 'KEY:'.$targetPlant->id.'|'.$targetSupplier->id.'|'.date('Y-m-d', strtotime($orderedAt)));

            if (! isset($purchaseGroups[$groupKey])) {
                $purchaseGroups[$groupKey] = [
                    'ref_no' => $refNo,
                    'plant' => $targetPlant,
                    'supplier' => $targetSupplier,
                    'doc_type' => $docType,
                    'ordered_at' => $orderedAt,
                    'invoice_no' => $invoiceNoStr ?: null,
                    'invoice_date' => $invoiceDate,
                    'is_interstate' => $isInterstate,
                    'notes' => $notesStr,
                    'items' => [],
                ];
            }

            $purchaseGroups[$groupKey]['items'][] = [
                'row_num' => $rowNum,
                'product' => $targetProduct,
                'quantity' => $qtyVal,
                'unit_price' => $priceVal,
                'discount_percent' => $discountVal,
                'gst_rate' => $gstVal,
            ];
        }

        if (! empty($rowErrors) && empty($purchaseGroups)) {
            return back()->withErrors(['csv_file' => 'Import failed with errors: '.implode(' | ', array_slice($rowErrors, 0, 5))]);
        }

        $importedPurchasesCount = 0;
        $importedItemsCount = 0;

        DB::beginTransaction();
        try {
            foreach ($purchaseGroups as $group) {
                /** @var Plant $plant */
                $plant = $group['plant'];
                /** @var Supplier $supplier */
                $supplier = $group['supplier'];

                $refNo = $group['ref_no'];
                if (empty($refNo)) {
                    $refNo = $docNumberService->nextReferenceNo($plant->id);
                }

                // Check existing reference_no collision
                $existingRef = Purchase::where('plant_id', $plant->id)->where('reference_no', $refNo)->first();
                if ($existingRef) {
                    $refNo = $docNumberService->nextReferenceNo($plant->id);
                }

                $purchase = Purchase::create([
                    'plant_id' => $plant->id,
                    'supplier_id' => $supplier->id,
                    'document_type' => $group['doc_type'],
                    'reference_no' => $refNo,
                    'status' => $autoReceive ? PurchaseStatus::Delivered : PurchaseStatus::Ordered,
                    'is_interstate' => $group['is_interstate'],
                    'supplier_invoice_no' => $group['invoice_no'],
                    'supplier_invoice_date' => $group['invoice_date'],
                    'ordered_at' => $group['ordered_at'],
                    'delivered_at' => $autoReceive ? now() : null,
                    'received_at' => $autoReceive ? now() : null,
                    'received_by' => $autoReceive ? $performedBy : null,
                    'notes' => $group['notes'],
                    'subtotal' => 0,
                    'total_cgst' => 0,
                    'total_sgst' => 0,
                    'total_igst' => 0,
                    'total_gst' => 0,
                    'grand_total' => 0,
                ]);

                $subtotal = 0.0;
                $totalCgst = 0.0;
                $totalSgst = 0.0;
                $totalIgst = 0.0;

                $stockLines = [];

                foreach ($group['items'] as $itemData) {
                    /** @var Product $product */
                    $product = $itemData['product'];
                    $qty = (float) $itemData['quantity'];
                    $unitPrice = (float) $itemData['unit_price'];
                    $discountPct = (float) $itemData['discount_percent'];
                    $gstRate = (float) $itemData['gst_rate'];

                    $taxable = $qty * $unitPrice * (1.0 - ($discountPct / 100.0));
                    $cgst = 0.0;
                    $sgst = 0.0;
                    $igst = 0.0;

                    if ($group['is_interstate']) {
                        $igst = $taxable * ($gstRate / 100.0);
                    } else {
                        $cgst = $taxable * ($gstRate / 200.0);
                        $sgst = $taxable * ($gstRate / 200.0);
                    }

                    $lineTotal = $taxable + $cgst + $sgst + $igst;

                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'discount_percent' => $discountPct,
                        'gst_rate' => $gstRate,
                        'taxable_amount' => $taxable,
                        'cgst_amount' => $cgst,
                        'sgst_amount' => $sgst,
                        'igst_amount' => $igst,
                        'line_total' => $lineTotal,
                        'received_quantity' => $autoReceive ? $qty : 0,
                    ]);

                    $subtotal += $taxable;
                    $totalCgst += $cgst;
                    $totalSgst += $sgst;
                    $totalIgst += $igst;

                    $importedItemsCount++;
                    $stockLines[] = [
                        'product_id' => $product->id,
                        'quantity' => $qty,
                    ];
                }

                $totalGst = $totalCgst + $totalSgst + $totalIgst;
                $grandTotal = $subtotal + $totalGst;

                $purchase->update([
                    'subtotal' => $subtotal,
                    'total_cgst' => $totalCgst,
                    'total_sgst' => $totalSgst,
                    'total_igst' => $totalIgst,
                    'total_gst' => $totalGst,
                    'grand_total' => $grandTotal,
                ]);

                if ($autoReceive && ! empty($stockLines)) {
                    app(StockService::class)->recordInFromPurchase($purchase, $stockLines, $performedBy, [
                        'notes' => 'Bulk purchase upload (Stock In) - PO '.$purchase->reference_no,
                    ]);
                }

                $importedPurchasesCount++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['csv_file' => 'Error saving bulk purchases: '.$e->getMessage()]);
        }

        $msg = "Bulk purchase upload complete! Created {$importedPurchasesCount} purchase document(s) with {$importedItemsCount} item line(s).";
        if (! empty($rowErrors)) {
            $msg .= ' (Warning: '.count($rowErrors).' row(s) had warnings or were skipped).';
        }

        $redirectRoute = request()->is('ho*') ? 'ho.purchases.index' : 'plant.purchases.index';

        return redirect()->route($redirectRoute)->with('success', $msg);
    }
}
