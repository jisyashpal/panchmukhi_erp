<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Plant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Services\StockService;
use App\Support\FinancialYear;
use App\Support\FinancialYearRange;
use App\Support\FinancialYearResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}
    public function index(Request $request): View
    {
        $range = $this->fyRangeFromSession($request);

        $movementSub = StockMovement::query()
            ->select('plant_id', 'product_id')
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as fy_stock_in',
                [StockMovementType::In->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as fy_stock_out',
                [StockMovementType::Out->value]
            )
            ->whereDate('moved_at', '>=', $range->dateFrom)
            ->whereDate('moved_at', '<=', $range->dateTo)
            ->when($request->filled('plant_id'), fn ($q) => $q->where('plant_id', $request->integer('plant_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->groupBy('plant_id', 'product_id');

        $balances = StockBalance::with(['plant', 'product.unit', 'product.category'])
            ->joinSub($movementSub, 'fy_movements', function ($join) {
                $join->on('stock_balances.plant_id', '=', 'fy_movements.plant_id')
                    ->on('stock_balances.product_id', '=', 'fy_movements.product_id');
            })
            ->select('stock_balances.*', 'fy_movements.fy_stock_in', 'fy_movements.fy_stock_out')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->whereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->whereHas('product', fn ($pq) => $pq->where('category_id', $request->integer('category_id')));
            })
            ->orderBy('stock_balances.plant_id')
            ->paginate(30)
            ->withQueryString();

        return view('ho.inventory.stocks.index', [
            'balances' => $balances,
            'plants' => Plant::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'financialYearRange' => $range,
        ]);
    }

    public function show(Request $request): View
    {
        $data = $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'product_id' => ['required', 'exists:products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $range = $this->fyRangeFromSession($request);
        $dates = $this->resolveDateFilter($request, $range);
        $plant = Plant::findOrFail($data['plant_id']);
        $product = Product::with('unit')->findOrFail($data['product_id']);

        $balance = StockBalance::where('plant_id', $plant->id)
            ->where('product_id', $product->id)
            ->first();

        $currentQty = $balance?->quantity ?? 0;

        // Calculate opening balance at the start of dates['from']
        $futureMovements = StockMovement::where('plant_id', $plant->id)
            ->where('product_id', $product->id)
            ->whereDate('moved_at', '>=', $dates['from'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as stock_in', [StockMovementType::In->value])
            ->selectRaw('SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as stock_out', [StockMovementType::Out->value])
            ->first();

        $openingBalance = $currentQty - (float) ($futureMovements->stock_in ?? 0) + (float) ($futureMovements->stock_out ?? 0);

        // Fetch movements in date range chronologically
        $movements = StockMovement::with(['subCategory', 'vehicle', 'performer', 'supplier'])
            ->where('plant_id', $plant->id)
            ->where('product_id', $product->id)
            ->whereDate('moved_at', '>=', $dates['from'])
            ->whereDate('moved_at', '<=', $dates['to'])
            ->orderBy('moved_at', 'asc')
            ->get();

        // Retrieve purchase items history for this product to map rates
        $latestRate = (float) (PurchaseItem::where('product_id', $product->id)
            ->latest('id')
            ->value('unit_price') ?? 0);

        $purchaseRates = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchase_items.product_id', $product->id)
            ->select('purchases.ordered_at', 'purchase_items.unit_price', 'purchase_items.purchase_id')
            ->orderBy('purchases.ordered_at', 'desc')
            ->get();

        $getRateAtTime = function ($movedAt, $referenceId = null) use ($purchaseRates, $latestRate) {
            if ($referenceId && $match = $purchaseRates->firstWhere('purchase_id', $referenceId)) {
                return (float) $match->unit_price;
            }
            
            // Find the most recent purchase rate before or equal to movedAt
            $match = $purchaseRates->first(fn ($item) => $item->ordered_at <= $movedAt);
            if ($match) {
                return (float) $match->unit_price;
            }
            
            // Fallback to the oldest available rate or overall latest rate
            if ($purchaseRates->isNotEmpty()) {
                return (float) $purchaseRates->last()->unit_price;
            }
            
            return $latestRate;
        };

        // Determine opening rate and value
        $openingRate = $getRateAtTime($dates['from'] . ' 00:00:00');
        $openingValue = $openingBalance * $openingRate;

        // Calculate running balance and movement transaction values
        $runningBalance = $openingBalance;
        $totalValueIn = 0.0;
        $totalValueOut = 0.0;

        $movements->map(function ($m) use (&$runningBalance, &$totalValueIn, &$totalValueOut, $getRateAtTime) {
            $m->rate = $getRateAtTime($m->moved_at, $m->reference_id);
            $m->value = $m->quantity * $m->rate;

            if ($m->type === StockMovementType::In) {
                $runningBalance += $m->quantity;
                $totalValueIn += $m->value;
            } else {
                $runningBalance -= $m->quantity;
                $totalValueOut += $m->value;
            }
            $m->running_balance = $runningBalance;
            return $m;
        });

        $purchaseIds = $movements
            ->filter(fn ($m) => $m->reference_type === StockReferenceType::Purchase && $m->reference_id)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $purchaseReferences = $purchaseIds->isNotEmpty()
            ? Purchase::whereIn('id', $purchaseIds)->pluck('reference_no', 'id')
            : collect();

        $periodStockIn = $movements->where('type', StockMovementType::In)->sum('quantity');
        $periodStockOut = $movements->where('type', StockMovementType::Out)->sum('quantity');

        // Grouped usage/consumption summary with quantity and value
        $usageSummary = $movements->where('type', StockMovementType::Out)
            ->groupBy(function ($m) {
                if ($m->vehicle) {
                    return 'Vehicle: '.$m->vehicle->displayLabel();
                }
                if ($m->subCategory) {
                    return 'Sub-Category: '.$m->subCategory->name;
                }
                if ($m->reason) {
                    return 'Reason: '.$m->reason;
                }

                return 'Other / Unspecified';
            })
            ->map(fn ($group) => [
                'quantity' => $group->sum('quantity'),
                'value' => $group->sum(fn ($m) => $m->quantity * $m->rate),
            ]);

        return view('ho.inventory.stocks.show', [
            'plant' => $plant,
            'product' => $product,
            'balance' => $balance,
            'movements' => $movements,
            'purchaseReferences' => $purchaseReferences,
            'financialYearRange' => $range,
            'dateFrom' => $dates['from'],
            'dateTo' => $dates['to'],
            'periodStockIn' => $periodStockIn,
            'periodStockOut' => $periodStockOut,
            'openingBalance' => $openingBalance,
            'openingRate' => $openingRate,
            'openingValue' => $openingValue,
            'totalValueIn' => $totalValueIn,
            'totalValueOut' => $totalValueOut,
            'latestRate' => $latestRate,
            'usageSummary' => $usageSummary,
        ]);
    }

    private function fyRangeFromSession(Request $request): FinancialYearRange
    {
        $label = app(FinancialYearResolver::class)->ensureSessionDefault($request);
        $fyDates = FinancialYear::range($label);

        return new FinancialYearRange(
            label: $label,
            dateFrom: $fyDates['from']->toDateString(),
            dateTo: $fyDates['to']->toDateString(),
            isManual: false,
        );
    }

    /**
     * @return array{from: string, to: string}
     */
    private function resolveDateFilter(Request $request, FinancialYearRange $fyRange): array
    {
        $from = $request->filled('date_from')
            ? $request->string('date_from')->toString()
            : $fyRange->dateFrom;
        $to = $request->filled('date_to')
            ? $request->string('date_to')->toString()
            : $fyRange->dateTo;

        if ($from < $fyRange->dateFrom) {
            $from = $fyRange->dateFrom;
        }
        if ($to > $fyRange->dateTo) {
            $to = $fyRange->dateTo;
        }
        if ($from > $to) {
            $from = $fyRange->dateFrom;
            $to = $fyRange->dateTo;
        }

        return ['from' => $from, 'to' => $to];
    }

    public function bulkUploadForm(Request $request): View
    {
        return view('ho.inventory.stocks.bulk-upload', [
            'plants' => Plant::orderBy('name')->get(),
            'selectedPlantId' => $request->integer('plant_id'),
        ]);
    }

    public function downloadBulkSample(Request $request): StreamedResponse
    {
        $plantId = $request->integer('plant_id');
        $plant = $plantId ? Plant::find($plantId) : null;

        $filename = 'stock_bulk_upload_sample'.($plant ? '_'.Str::slug($plant->name) : '').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($plantId) {
            $handle = fopen('php://output', 'w');

            // Exact header columns matching user's image / standard format
            fputcsv($handle, [
                'Cat.',
                'Item Type',
                'Item Description',
                'Part Number',
                'COMPANY NAME',
                'Qty',
                'UNIT',
            ]);

            if ($plantId) {
                // If a plant is selected, export existing plant items/balances so user can directly edit quantities
                $balances = StockBalance::with(['product.category', 'product.subCategory', 'product.brand', 'product.unit'])
                    ->where('plant_id', $plantId)
                    ->get();

                if ($balances->isNotEmpty()) {
                    foreach ($balances as $b) {
                        fputcsv($handle, [
                            $b->product->category?->name ?? '',
                            $b->product->subCategory?->name ?? '',
                            $b->product->name,
                            $b->product->size ?? $b->product->sku,
                            $b->product->brand?->name ?? '',
                            $b->quantity,
                            strtoupper($b->product->unit?->abbreviation ?? 'NOS'),
                        ]);
                    }
                } else {
                    $this->writeSampleRows($handle);
                }
            } else {
                $this->writeSampleRows($handle);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function writeSampleRows($handle): void
    {
        fputcsv($handle, ['Hyva', 'Valve', 'Dual Brake Valve', '601130', '', '2', 'NOS']);
        fputcsv($handle, ['Hyva', 'Switch', 'Combination Switch (TATA)', '278064506909', 'TATA', '1', 'NOS']);
        fputcsv($handle, ['Hyva', 'Bearing', 'Bearing (33111) TATA-3107', '33111 / 3107', 'TATA', '3', 'NOS']);
        fputcsv($handle, ['JCB 3DX', 'Lamp', 'Head Lamp Assy (3DX) (Gold)', '', 'GOLD', '2', 'NOS']);
        fputcsv($handle, ['General', 'Tape', 'Black Tape', '', '', '30', 'NOS']);
    }

    public function bulkUpload(Request $request): RedirectResponse
    {
        $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'update_mode' => ['required', 'in:add,set'],
            'auto_create_products' => ['nullable', 'boolean'],
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

        $plant = Plant::findOrFail($request->integer('plant_id'));
        $updateMode = $request->string('update_mode')->toString();
        $autoCreate = $request->boolean('auto_create_products', true);
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

        $normalizedHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);

        $findHeader = function (array $candidates) use ($normalizedHeaders) {
            foreach ($candidates as $cand) {
                $pos = array_search(strtolower($cand), $normalizedHeaders, true);
                if ($pos !== false) {
                    return $pos;
                }
            }

            return false;
        };

        $colCategory = $findHeader(['cat.', 'cat', 'category']);
        $colSubCategory = $findHeader(['item type', 'itemtype', 'sub category', 'subcategory', 'sub_category']);
        $colProdName = $findHeader(['item description', 'itemdescription', 'product name', 'productname', 'item name', 'itemname', 'description']);
        $colPartNo = $findHeader(['part number', 'partnumber', 'part no', 'partno', 'size', 'sku', 'part']);
        $colBrand = $findHeader(['company name', 'companyname', 'brand', 'company']);
        $colQty = $findHeader(['qty', 'quantity', 'count']);
        $colUnit = $findHeader(['unit', 'units', 'uom']);

        if ($colProdName === false && $colPartNo === false) {
            return back()->withErrors(['csv_file' => 'File must contain an "Item Description" or "Part Number" column to identify products.']);
        }

        if ($colQty === false) {
            return back()->withErrors(['csv_file' => 'File must contain a "Qty" column.']);
        }

        $rowCount = 0;
        $updatedCount = 0;
        $createdProductCount = 0;
        $rowErrors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $rowCount++;
                if (! is_array($row) || empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                    continue;
                }

                $catName = $colCategory !== false ? trim((string) ($row[$colCategory] ?? '')) : '';
                $subName = $colSubCategory !== false ? trim((string) ($row[$colSubCategory] ?? '')) : '';
                $prodName = $colProdName !== false ? trim((string) ($row[$colProdName] ?? '')) : '';
                $partNo = $colPartNo !== false ? trim((string) ($row[$colPartNo] ?? '')) : '';
                $brandName = $colBrand !== false ? trim((string) ($row[$colBrand] ?? '')) : '';
                $rawQty = $colQty !== false ? trim((string) ($row[$colQty] ?? '')) : '';
                $unitAbbr = $colUnit !== false ? trim((string) ($row[$colUnit] ?? '')) : 'nos';
                if (empty($unitAbbr)) {
                    $unitAbbr = 'nos';
                }

                if (! is_numeric($rawQty) || (float) $rawQty < 0) {
                    $rowErrors[] = "Row {$rowCount}: Invalid quantity '{$rawQty}'. Must be a non-negative number.";
                    continue;
                }
                $qty = (float) $rawQty;

                if (empty($prodName) && empty($partNo)) {
                    $rowErrors[] = "Row {$rowCount}: Item Description or Part Number is required.";
                    continue;
                }

                // 1. Try finding existing product by Part Number / SKU / Name
                $product = null;
                if (! empty($partNo)) {
                    $product = Product::where('sku', $partNo)
                        ->orWhere('size', $partNo)
                        ->first();
                }

                if (! $product && ! empty($prodName)) {
                    $product = Product::where('name', $prodName)->first();
                }

                // 2. If product not found, auto-create if enabled
                if (! $product) {
                    if (! $autoCreate) {
                        $rowErrors[] = "Row {$rowCount}: Product '".($prodName ?: $partNo)."' not found in database.";
                        continue;
                    }

                    if (empty($catName) || empty($prodName)) {
                        $rowErrors[] = "Row {$rowCount}: Cannot auto-create product. Category and Item Description are required.";
                        continue;
                    }

                    $category = Category::firstOrCreate(
                        ['slug' => Str::slug($catName)],
                        ['name' => $catName, 'is_active' => true]
                    );

                    $subCategory = null;
                    if (! empty($subName)) {
                        $subCategory = SubCategory::firstOrCreate(
                            ['category_id' => $category->id, 'slug' => Str::slug($subName)],
                            ['name' => $subName, 'is_active' => true]
                        );
                    }

                    $unit = Unit::where('abbreviation', strtolower($unitAbbr))
                        ->orWhere('name', 'like', $unitAbbr)
                        ->first();

                    if (! $unit) {
                        $unit = Unit::create([
                            'name' => ucfirst($unitAbbr),
                            'abbreviation' => strtolower($unitAbbr),
                        ]);
                    }

                    $brand = null;
                    if (! empty($brandName)) {
                        $brand = Brand::firstOrCreate(['name' => $brandName]);
                    }

                    // Generate clean SKU
                    $cleanCategory = preg_replace('/[^A-Za-z0-9]/', '', $category->name);
                    $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $prodName);
                    $baseSku = strtoupper(substr($cleanCategory, 0, 3)).'_'.strtoupper(substr($cleanName, 0, 4));
                    $sku = $baseSku;
                    $counter = 1;
                    while (Product::where('sku', $sku)->exists()) {
                        $sku = $baseSku.'_'.str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
                        $counter++;
                    }

                    $product = Product::create([
                        'category_id' => $category->id,
                        'sub_category_id' => $subCategory?->id,
                        'brand_id' => $brand?->id,
                        'unit_id' => $unit->id,
                        'name' => $prodName,
                        'size' => $partNo ?: null,
                        'sku' => $sku,
                        'reorder_level' => 10,
                        'is_active' => true,
                    ]);

                    $createdProductCount++;
                }

                // 3. Update stock balance for plant
                if ($updateMode === 'add') {
                    if ($qty > 0) {
                        $this->stockService->recordIn(
                            plantId: $plant->id,
                            productId: $product->id,
                            quantity: $qty,
                            performedBy: $performedBy,
                            referenceType: StockReferenceType::StockIn,
                            meta: ['notes' => 'Bulk stock upload (Stock In) for '.$plant->name]
                        );
                        $updatedCount++;
                    }
                } else {
                    // Update mode: set (Overwrite/Adjust to target balance)
                    $currentBalance = $this->stockService->getBalance($plant->id, $product->id);
                    $delta = $qty - $currentBalance;

                    if (abs($delta) > 0.0001) {
                        if ($delta > 0) {
                            $this->stockService->recordIn(
                                plantId: $plant->id,
                                productId: $product->id,
                                quantity: $delta,
                                performedBy: $performedBy,
                                referenceType: StockReferenceType::Adjustment,
                                meta: ['notes' => 'Bulk stock adjustment (Set balance to '.$qty.') for '.$plant->name]
                            );
                        } else {
                            $this->stockService->recordOut(
                                plantId: $plant->id,
                                productId: $product->id,
                                quantity: abs($delta),
                                referenceType: StockReferenceType::Adjustment,
                                referenceId: null,
                                performedBy: $performedBy,
                                notes: 'Bulk stock adjustment (Set balance to '.$qty.') for '.$plant->name
                            );
                        }
                        $updatedCount++;
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['csv_file' => 'Error processing bulk stock upload: '.$e->getMessage()]);
        }

        $message = "Bulk stock upload complete for {$plant->name}. {$updatedCount} items updated.";
        if ($createdProductCount > 0) {
            $message .= " ({$createdProductCount} new products created).";
        }

        if (count($rowErrors) > 0) {
            return redirect()->route('ho.stocks.index')
                ->with('success', $message)
                ->with('warning_rows', $rowErrors);
        }

        return redirect()->route('ho.stocks.index')->with('success', $message);
    }
}
