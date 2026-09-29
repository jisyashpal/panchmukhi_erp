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
use App\Support\MasterName;
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
        $settings = session('purchase_bulk_settings', []);
        $selectedPlantId = method_exists($this, 'plantId')
            ? $this->plantId()
            : ($settings['plant_id'] ?? $request->integer('plant_id'));

        return view('plant.request-po.purchases.bulk-upload', [
            'plants' => Plant::where('is_active', true)->orderBy('name')->get(),
            'selectedPlantId' => $selectedPlantId,
            'portalLayout' => $this->purchaseImportPortalLayout(),
            'pendingRows' => session('purchase_bulk_pending', []),
            'pendingAutoReceive' => (bool) ($settings['auto_receive'] ?? false),
            'pendingAutoCreateSuppliers' => (bool) ($settings['auto_create_suppliers'] ?? false),
            'catalogProducts' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
            'catalogSuppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'gstin']),
        ]);
    }

    /**
     * Canonical headers written by Download Sample and accepted by upload.
     *
     * @return list<string>
     */
    private function bulkPurchaseSampleHeaders(): array
    {
        return [
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
        ];
    }

    public function downloadBulkSample(Request $request): StreamedResponse
    {
        $plantId = method_exists($this, 'plantId') ? $this->plantId() : $request->integer('plant_id');
        $plant = $plantId ? Plant::find($plantId) : Plant::query()->where('is_active', true)->orderBy('name')->first();
        $plantCode = $plant ? ($plant->code ?: $plant->name) : 'PLANT';

        $products = Product::query()->where('is_active', true)->orderBy('id')->limit(3)->get();
        if ($products->isEmpty()) {
            $products = Product::query()->orderBy('id')->limit(3)->get();
        }

        $productKeys = collect();
        $first = $products->get(0);
        $second = $products->get(1) ?: $first;
        $third = $products->get(2) ?: $first;
        $productKeys->push($first?->sku ?: ($first?->name ?: 'REPLACE-WITH-CATALOG-SKU'));
        $productKeys->push($second?->name ?: ($second?->sku ?: 'REPLACE-WITH-CATALOG-NAME'));
        if ($third && $third->sku && $third->name) {
            $productKeys->push($third->sku.' / '.$third->name);
        } else {
            $productKeys->push($third?->sku ?: ($third?->name ?: 'REPLACE-WITH-CATALOG-SKU'));
        }

        $suppliers = Supplier::query()->where('is_active', true)->orderBy('id')->limit(2)->get();
        $supplierA = $suppliers->get(0)?->name ?? 'Apex Tools & Machinery';
        $supplierB = $suppliers->get(1)?->name ?? $supplierA;
        if ($supplierB === $supplierA) {
            $supplierB = 'Global Industrial Corp';
        }

        $filename = 'bulk_purchases_sample'.($plant ? '_'.Str::slug($plant->name) : '').'.csv';
        $today = date('Y-m-d');
        $headers = $this->bulkPurchaseSampleHeaders();

        return response()->streamDownload(function () use ($plantCode, $productKeys, $supplierA, $supplierB, $today, $headers) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            fputcsv($handle, [
                'PO-'.strtoupper($plantCode).'-1001',
                $plantCode,
                $supplierA,
                'purchase_order',
                $today,
                'INV-2026-001',
                $today,
                '0',
                $productKeys[0],
                '10',
                '450.00',
                '0',
                '18',
                'Bulk purchase initial batch',
            ]);

            fputcsv($handle, [
                'PO-'.strtoupper($plantCode).'-1001',
                $plantCode,
                $supplierA,
                'purchase_order',
                $today,
                'INV-2026-001',
                $today,
                '0',
                $productKeys[1],
                '5',
                '1200.00',
                '5',
                '18',
                'Second item in same purchase order',
            ]);

            fputcsv($handle, [
                'PO-'.strtoupper($plantCode).'-1002',
                $plantCode,
                $supplierB,
                'purchase_order',
                $today,
                'BILL-9082',
                $today,
                '1',
                $productKeys[2],
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
        $hasPendingRows = $request->filled('pending_rows') && is_array($request->input('pending_rows'));

        $request->validate([
            'plant_id' => ['nullable', 'exists:plants,id'],
            'auto_receive' => ['nullable', 'boolean'],
            'auto_create_suppliers' => ['nullable', 'boolean'],
            'csv_file' => [
                $hasPendingRows ? 'nullable' : 'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['csv', 'xlsx', 'xls', 'txt', 'ods'], true)) {
                        $fail('The uploaded file must be an Excel (.xlsx, .xls) or CSV (.csv) file.');
                    }
                },
            ],
            'pending_rows' => [$hasPendingRows ? 'required' : 'nullable', 'array'],
        ]);

        $userPlantId = method_exists($this, 'plantId') ? $this->plantId() : null;
        $defaultPlantId = $request->integer('plant_id') ?: $userPlantId;
        $autoReceive = $request->boolean('auto_receive', false);
        $autoCreateSuppliers = $request->boolean('auto_create_suppliers', false);
        $performedBy = (int) auth()->id();
        $formRoute = $this->isHoPurchaseImport() ? 'ho.purchases.bulk-upload' : 'plant.purchases.bulk-upload';
        $indexRoute = $this->isHoPurchaseImport() ? 'ho.purchases.index' : 'plant.purchases.index';

        if ($request->hasFile('csv_file')) {
            $parsed = $this->parsePurchaseUploadFile($request->file('csv_file'));
            if ($parsed instanceof RedirectResponse) {
                return $parsed;
            }
            $pendingRows = $parsed;
        } else {
            $pendingRows = $this->pendingRowsFromRequest($request);
        }

        if (empty($pendingRows)) {
            return back()->withErrors(['csv_file' => 'The uploaded file is empty.']);
        }

        [$annotatedRows, $rowErrors] = $this->annotatePendingPurchaseRows(
            $pendingRows,
            $defaultPlantId,
            $autoCreateSuppliers
        );

        if (! empty($rowErrors)) {
            return redirect()->route($formRoute)
                ->with('error', 'File was not imported. Fix the highlighted Product SKU / Name and Supplier Name rows, then import again. Nothing was saved.')
                ->with('purchase_bulk_pending', $annotatedRows)
                ->with('purchase_bulk_settings', [
                    'plant_id' => $defaultPlantId,
                    'auto_receive' => $autoReceive,
                    'auto_create_suppliers' => $autoCreateSuppliers,
                ]);
        }

        try {
            [$importedPurchasesCount, $importedItemsCount] = $this->persistValidatedPurchaseRows(
                $annotatedRows,
                $defaultPlantId,
                $autoReceive,
                $autoCreateSuppliers,
                $performedBy
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['csv_file' => 'Error saving bulk purchases: '.$e->getMessage()]);
        }

        $msg = "Bulk purchase upload complete! Created {$importedPurchasesCount} purchase document(s) with {$importedItemsCount} item line(s).";

        return redirect()->route($indexRoute)->with('success', $msg);
    }

    /**
     * @return list<array<string, mixed>>|RedirectResponse
     */
    private function parsePurchaseUploadFile($file): array|RedirectResponse
    {
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

        if (isset($headers[0])) {
            $headers[0] = preg_replace('/[\x{EF}\x{BB}\x{BF}]/u', '', (string) $headers[0]);
        }

        $normalizeHeader = fn ($header) => trim((string) preg_replace('/[^a-z0-9]+/i', ' ', strtolower(trim((string) $header))));
        $normalizedHeaders = array_map($normalizeHeader, $headers);

        $findHeader = function (array $candidates) use ($normalizedHeaders) {
            foreach ($candidates as $cand) {
                $normalizedCandidate = trim((string) preg_replace('/[^a-z0-9]+/i', ' ', strtolower($cand)));
                if ($normalizedCandidate === '') {
                    continue;
                }

                $pos = array_search($normalizedCandidate, $normalizedHeaders, true);
                if ($pos !== false) {
                    return $pos;
                }

                foreach ($normalizedHeaders as $index => $normalizedHeader) {
                    if ($normalizedHeader !== '' && str_starts_with($normalizedHeader, $normalizedCandidate.' ')) {
                        return $index;
                    }
                }
            }

            return false;
        };

        $sampleHeaders = $this->bulkPurchaseSampleHeaders();
        $colRefNo = $findHeader([$sampleHeaders[0], 'reference no', 'reference_no', 'po number', 'po_number', 'ref no', 'ref_no', 'order no', 'quotation no']);
        $colPlant = $findHeader([$sampleHeaders[1], 'plant code name', 'plant code', 'plant_code', 'plant name', 'plant_name', 'plant', 'site']);
        $colSupplier = $findHeader([$sampleHeaders[2], 'supplier name', 'supplier_name', 'supplier code', 'supplier_code', 'supplier', 'vendor']);
        $colDocType = $findHeader([$sampleHeaders[3], 'document type', 'document_type', 'doc type']);
        $colOrderDate = $findHeader([$sampleHeaders[4], 'order date', 'ordered_at', 'order_date']);
        $colInvoiceNo = $findHeader([$sampleHeaders[5], 'supplier invoice no', 'supplier_invoice_no', 'invoice no', 'invoice_no', 'bill no']);
        $colInvoiceDate = $findHeader([$sampleHeaders[6], 'supplier invoice date', 'supplier_invoice_date', 'invoice date', 'bill date']);
        $colInterstate = $findHeader([$sampleHeaders[7], 'is interstate 1 0', 'is interstate', 'is_interstate', 'interstate']);
        $colProduct = $findHeader([$sampleHeaders[8], 'product sku name', 'product name', 'product', 'item description', 'item name', 'item', 'description']);
        $colSku = $findHeader([$sampleHeaders[8], 'product sku name', 'sku', 'product sku', 'product code', 'part no', 'part number', 'item code']);
        $colQty = $findHeader([$sampleHeaders[9], 'quantity', 'qty', 'count']);
        $colUnitPrice = $findHeader([$sampleHeaders[10], 'unit price', 'unit_price', 'price', 'rate', 'cost']);
        $colDiscount = $findHeader([$sampleHeaders[11], 'discount percent', 'discount_percent', 'discount %', 'discount']);
        $colGstRate = $findHeader([$sampleHeaders[12], 'gst rate', 'gst_rate', 'gst %', 'gst', 'tax rate']);
        $colNotes = $findHeader([$sampleHeaders[13], 'notes', 'note', 'remarks', 'remark']);

        if ($colProduct === false && $colSku === false) {
            return back()->withErrors(['csv_file' => 'File must contain a "Product SKU" or "Product Name" column.']);
        }
        if ($colQty === false) {
            return back()->withErrors(['csv_file' => 'File must contain a "Quantity" or "Qty" column.']);
        }
        if ($colUnitPrice === false) {
            return back()->withErrors(['csv_file' => 'File must contain a "Unit Price" or "Rate" column.']);
        }

        $pendingRows = [];
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                continue;
            }

            $getVal = function ($col) use ($row) {
                if ($col === false || ! isset($row[$col])) {
                    return '';
                }

                return trim((string) $row[$col]);
            };

            $productStr = $getVal($colProduct);
            $skuStr = $getVal($colSku);
            $productLookup = $this->combinedProductLookup($skuStr, $productStr);

            $pendingRows[] = [
                'row_num' => $rowNum,
                'reference_no' => $getVal($colRefNo),
                'plant' => $getVal($colPlant),
                'supplier' => $getVal($colSupplier),
                'document_type' => $getVal($colDocType),
                'order_date' => $getVal($colOrderDate),
                'invoice_no' => $getVal($colInvoiceNo),
                'invoice_date' => $getVal($colInvoiceDate),
                'interstate' => $getVal($colInterstate),
                'product' => $productLookup,
                'quantity' => $getVal($colQty),
                'unit_price' => $getVal($colUnitPrice),
                'discount' => $getVal($colDiscount),
                'gst_rate' => $getVal($colGstRate),
                'notes' => $getVal($colNotes),
                'error' => null,
            ];
        }

        return $pendingRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingRowsFromRequest(Request $request): array
    {
        $pendingRows = [];
        foreach (array_values($request->input('pending_rows', [])) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $pendingRows[] = [
                'row_num' => (int) ($row['row_num'] ?? 0),
                'reference_no' => trim((string) ($row['reference_no'] ?? '')),
                'plant' => trim((string) ($row['plant'] ?? '')),
                'supplier' => trim((string) ($row['supplier'] ?? '')),
                'document_type' => trim((string) ($row['document_type'] ?? '')),
                'order_date' => trim((string) ($row['order_date'] ?? '')),
                'invoice_no' => trim((string) ($row['invoice_no'] ?? '')),
                'invoice_date' => trim((string) ($row['invoice_date'] ?? '')),
                'interstate' => trim((string) ($row['interstate'] ?? '')),
                'product' => trim((string) ($row['product'] ?? '')),
                'quantity' => trim((string) ($row['quantity'] ?? '')),
                'unit_price' => trim((string) ($row['unit_price'] ?? '')),
                'discount' => trim((string) ($row['discount'] ?? '')),
                'gst_rate' => trim((string) ($row['gst_rate'] ?? '')),
                'notes' => trim((string) ($row['notes'] ?? '')),
                'product_id' => (int) ($row['product_id'] ?? 0),
                'supplier_id' => (int) ($row['supplier_id'] ?? 0),
                'error' => null,
            ];
        }

        return $pendingRows;
    }

    private function combinedProductLookup(string $skuStr, string $productStr): string
    {
        if ($skuStr !== '' && $productStr !== '' && $this->normalizeCatalogKey($skuStr) !== $this->normalizeCatalogKey($productStr)) {
            return $skuStr.' / '.$productStr;
        }

        return $skuStr !== '' ? $skuStr : $productStr;
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function annotatePendingPurchaseRows(array $pendingRows, ?int $defaultPlantId, bool $autoCreateSuppliers): array
    {
        $allPlants = Plant::all();
        $allSuppliers = Supplier::all();
        $allProducts = Product::all();
        $annotated = [];
        $rowErrors = [];

        foreach ($pendingRows as $dto) {
            $rowNum = (int) ($dto['row_num'] ?? 0);
            $errors = [];

            $plantStr = (string) ($dto['plant'] ?? '');
            $targetPlant = null;
            if ($plantStr !== '') {
                $targetPlant = $allPlants->first(function ($p) use ($plantStr) {
                    return strcasecmp($p->code ?? '', $plantStr) === 0 || strcasecmp($p->name ?? '', $plantStr) === 0;
                });
            }
            if (! $targetPlant && $defaultPlantId) {
                $targetPlant = $allPlants->firstWhere('id', $defaultPlantId);
            }
            if (! $targetPlant) {
                $errors[] = 'Plant could not be identified (specify plant in file or select plant dropdown).';
            }

            [$targetSupplier, $supplierMatchError] = $this->resolvePendingCatalogSupplier($allSuppliers, $dto);
            $supplierStr = trim((string) ($dto['supplier'] ?? ''));
            $canAutoCreateSupplier = $autoCreateSuppliers && MasterName::normalize($supplierStr) !== '';
            if (! $targetSupplier && ! $canAutoCreateSupplier) {
                $errors[] = $supplierMatchError;
            }

            [$targetProduct, $productMatchError] = $this->resolvePendingCatalogProduct($allProducts, $dto);
            if (! $targetProduct) {
                $errors[] = $productMatchError;
            }

            $qtyVal = (float) ($dto['quantity'] ?? 0);
            if ($qtyVal <= 0) {
                $errors[] = "Invalid quantity '{$qtyVal}'.";
            }

            $error = $errors === [] ? null : implode(' ', $errors);
            $dto['error'] = $error;
            $dto['product_error'] = $targetProduct ? null : $productMatchError;
            $dto['supplier_error'] = (! $targetSupplier && ! $canAutoCreateSupplier) ? $supplierMatchError : null;
            $annotated[] = $dto;
            if ($error !== null) {
                $rowErrors[] = "Row {$rowNum}: {$error}";
            }
        }

        return [$annotated, $rowErrors];
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     * @return array{0: int, 1: int}
     */
    private function persistValidatedPurchaseRows(
        array $pendingRows,
        ?int $defaultPlantId,
        bool $autoReceive,
        bool $autoCreateSuppliers,
        int $performedBy
    ): array {
        $allPlants = Plant::all();
        $allSuppliers = Supplier::all();
        $allProducts = Product::all();
        $docNumberService = app(PlantDocumentNumberService::class);
        $importedPurchasesCount = 0;
        $importedItemsCount = 0;

        DB::beginTransaction();
        try {
            $purchaseGroups = [];

            foreach ($pendingRows as $dto) {
            $rowNum = (int) ($dto['row_num'] ?? 0);
            $plantStr = (string) ($dto['plant'] ?? '');
            $supplierStr = (string) ($dto['supplier'] ?? '');
            $productLookup = (string) ($dto['product'] ?? '');
            $qtyVal = (float) ($dto['quantity'] ?? 0);
            $priceVal = (float) ($dto['unit_price'] ?? 0);
            $discountVal = (float) ($dto['discount'] ?? 0);
            $gstVal = (float) ($dto['gst_rate'] ?? 0);
            $refNo = (string) ($dto['reference_no'] ?? '');
            $docTypeStr = strtolower((string) ($dto['document_type'] ?? ''));
            $orderDateStr = (string) ($dto['order_date'] ?? '');
            $invoiceNoStr = (string) ($dto['invoice_no'] ?? '');
            $invoiceDateStr = (string) ($dto['invoice_date'] ?? '');
            $interstateStr = strtolower((string) ($dto['interstate'] ?? ''));
            $notesStr = (string) ($dto['notes'] ?? '');

            $targetPlant = null;
            if ($plantStr !== '') {
                $targetPlant = $allPlants->first(function ($p) use ($plantStr) {
                    return strcasecmp($p->code ?? '', $plantStr) === 0 || strcasecmp($p->name ?? '', $plantStr) === 0;
                });
            }
            if (! $targetPlant && $defaultPlantId) {
                $targetPlant = $allPlants->firstWhere('id', $defaultPlantId);
            }

            [$targetSupplier] = $this->resolvePendingCatalogSupplier($allSuppliers, $dto);
            if (! $targetSupplier && $autoCreateSuppliers && MasterName::normalize($supplierStr) !== '') {
                $targetSupplier = Supplier::create([
                    'name' => trim($supplierStr),
                    'is_active' => true,
                ]);
                $allSuppliers->push($targetSupplier);
            }

            [$targetProduct] = $this->resolvePendingCatalogProduct($allProducts, $dto);

            if (! $targetPlant || ! $targetSupplier || ! $targetProduct || $qtyVal <= 0) {
                throw new \RuntimeException("Row {$rowNum} failed unexpectedly during save.");
            }

            $docType = str_contains($docTypeStr, 'quot')
                ? PurchaseDocumentType::Quotation
                : PurchaseDocumentType::PurchaseOrder;

            $orderedAt = $orderDateStr !== '' && strtotime($orderDateStr) !== false
                ? date('Y-m-d H:i:s', strtotime($orderDateStr))
                : date('Y-m-d H:i:s');

            $invoiceDate = $invoiceDateStr !== '' && strtotime($invoiceDateStr) !== false
                ? date('Y-m-d', strtotime($invoiceDateStr))
                : null;

            $isInterstate = in_array($interstateStr, ['1', 'true', 'yes', 'y'], true);

            $groupKey = $refNo !== ''
                ? 'REF:'.$refNo
                : ($invoiceNoStr !== ''
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

            foreach ($purchaseGroups as $group) {
                /** @var Plant $plant */
                $plant = $group['plant'];
                /** @var Supplier $supplier */
                $supplier = $group['supplier'];

                $refNo = $group['ref_no'];
                if ($refNo === '') {
                    $refNo = $docNumberService->nextReferenceNo($plant->id);
                }

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
            throw $e;
        }

        return [$importedPurchasesCount, $importedItemsCount];
    }

    /**
     * @return array{0: ?Supplier, 1: string}
     */
    private function resolvePendingCatalogSupplier($allSuppliers, array $dto): array
    {
        $supplierId = (int) ($dto['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $supplier = $allSuppliers->firstWhere('id', $supplierId);
            if ($supplier) {
                return [$supplier, ''];
            }

            return [null, 'Selected catalog supplier was not found.'];
        }

        $supplierStr = trim((string) ($dto['supplier'] ?? ''));
        if (MasterName::normalize($supplierStr) === '') {
            return [null, 'Supplier Name is empty. Search and select an existing supplier.'];
        }

        $matched = $this->matchImportedSupplier($allSuppliers, $supplierStr);
        if ($matched) {
            return [$matched, ''];
        }

        return [null, "Supplier '{$supplierStr}' not found. Search and select an existing supplier."];
    }

    /**
     * @return array{0: ?Product, 1: string}
     */
    private function resolvePendingCatalogProduct($allProducts, array $dto): array
    {
        $productId = (int) ($dto['product_id'] ?? 0);
        if ($productId > 0) {
            $product = $allProducts->firstWhere('id', $productId);
            if ($product) {
                return [$product, ''];
            }

            return [null, 'Selected catalog product was not found.'];
        }

        $productLookup = (string) ($dto['product'] ?? '');

        return $this->matchCatalogProduct($allProducts, $productLookup, $productLookup);
    }

    private function normalizeCatalogKey(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return Str::lower($value);
    }

    private function findCatalogProductBySkuOrName($allProducts, string $value): ?Product
    {
        $normalized = $this->normalizeCatalogKey($value);
        if ($normalized === '') {
            return null;
        }

        return $allProducts->first(function (Product $product) use ($normalized) {
            $sku = $this->normalizeCatalogKey($product->sku);
            $name = $this->normalizeCatalogKey($product->name);

            return ($sku !== '' && $sku === $normalized) || ($name !== '' && $name === $normalized);
        });
    }

    /**
     * Match Product SKU / Name against catalog. SKU or exact product name is allowed.
     * Combined values like "SKU / Name" must both belong to the same product.
     *
     * @return array{0: ?Product, 1: string}
     */
    private function matchCatalogProduct($allProducts, string $skuStr, string $productStr): array
    {
        $skuStr = trim(str_replace("\xC2\xA0", ' ', $skuStr));
        $productStr = trim(str_replace("\xC2\xA0", ' ', $productStr));
        $skuStr = trim((string) preg_replace('/\s+/u', ' ', $skuStr));
        $productStr = trim((string) preg_replace('/\s+/u', ' ', $productStr));

        if ($skuStr === '' && $productStr === '') {
            return [null, 'Product SKU / Name is empty. Use a catalog SKU or the exact product name.'];
        }

        $sameCell = $skuStr !== '' && $this->normalizeCatalogKey($skuStr) === $this->normalizeCatalogKey($productStr);
        $lookup = $skuStr !== '' ? $skuStr : $productStr;

        if ($sameCell || $productStr === '' || $skuStr === '') {
            $direct = $this->findCatalogProductBySkuOrName($allProducts, $lookup);
            if ($direct) {
                return [$direct, ''];
            }

            if (preg_match('/^(.+?)\s+\/\s+(.+)$/u', $lookup, $matches) === 1) {
                $skuPart = trim($matches[1]);
                $namePart = trim($matches[2]);
                $bySku = $this->findCatalogProductBySkuOrName($allProducts, $skuPart);
                $byName = $this->findCatalogProductBySkuOrName($allProducts, $namePart);

                if ($bySku && $byName && (int) $bySku->id === (int) $byName->id) {
                    return [$bySku, ''];
                }
                if ($bySku && $byName) {
                    return [null, "SKU '{$skuPart}' and name '{$namePart}' do not match the same catalog product."];
                }
                if ($bySku && ! $byName) {
                    return [null, "SKU '{$skuPart}' found, but product name '{$namePart}' does not exactly match the catalog."];
                }
                if ($byName && ! $bySku) {
                    return [null, "Product name '{$namePart}' found, but SKU '{$skuPart}' does not exactly match the catalog."];
                }
            }

            return [null, "Product '{$lookup}' not found. Use an exact catalog SKU or exact product name."];
        }

        $bySku = $this->findCatalogProductBySkuOrName($allProducts, $skuStr);
        $byName = $this->findCatalogProductBySkuOrName($allProducts, $productStr);

        if ($bySku && $byName && (int) $bySku->id === (int) $byName->id) {
            return [$bySku, ''];
        }
        if ($bySku && $byName) {
            return [null, "SKU '{$skuStr}' and product name '{$productStr}' do not match the same catalog product."];
        }
        if ($bySku && ! $byName) {
            return [null, "SKU '{$skuStr}' found, but product name '{$productStr}' does not exactly match the catalog."];
        }
        if ($byName && ! $bySku) {
            return [null, "Product name '{$productStr}' found, but SKU '{$skuStr}' does not exactly match the catalog."];
        }

        return [null, "Product SKU '{$skuStr}' / name '{$productStr}' not found in catalog."];
    }

    private function matchImportedSupplier($allSuppliers, string $supplierStr): ?Supplier
    {
        $key = MasterName::supplierKey($supplierStr);
        if ($key === '') {
            return null;
        }

        return $allSuppliers->first(function (Supplier $supplier) use ($key, $supplierStr) {
            return MasterName::matchesSupplier($supplier->name, $supplierStr)
                || ($supplier->phone && MasterName::supplierKey($supplier->phone) === $key)
                || ($supplier->gstin && strcasecmp(trim((string) $supplier->gstin), trim($supplierStr)) === 0);
        });
    }

    private function isHoPurchaseImport(): bool
    {
        return request()->routeIs('ho.*');
    }

    private function purchaseImportPortalLayout(): string
    {
        if (! $this->isHoPurchaseImport()) {
            return 'layouts.plant';
        }

        return view()->shared('portalLayout') ?: 'layouts.ho';
    }
}
