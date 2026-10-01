<?php

namespace App\Http\Controllers\Ho\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ManagesProductCatalog;
use App\Http\Controllers\Ho\RequestPo\PurchaseController;
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
use App\Models\Vehicle;
use App\Services\StockService;
use App\Support\FinancialYear;
use App\Support\FinancialYearRange;
use App\Support\FinancialYearResolver;
use App\Support\MasterName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockController extends Controller
{
    use ManagesProductCatalog;

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

        $selectedProductId = $request->filled('product_id') ? $request->integer('product_id') : null;

        $balances = StockBalance::with(['plant', 'product.unit', 'product.category', 'product.subCategory'])
            ->joinSub($movementSub, 'fy_movements', function ($join) {
                $join->on('stock_balances.plant_id', '=', 'fy_movements.plant_id')
                    ->on('stock_balances.product_id', '=', 'fy_movements.product_id');
            })
            ->select('stock_balances.*', 'fy_movements.fy_stock_in', 'fy_movements.fy_stock_out')
            ->when($selectedProductId, fn ($q) => $q->where('stock_balances.product_id', $selectedProductId))
            ->when($request->filled('search') && ! $selectedProductId, function ($q) use ($request) {
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

        $selectedProduct = $selectedProductId
            ? Product::with(['unit', 'category', 'subCategory'])->find($selectedProductId)
            : null;

        return view('ho.inventory.stocks.index', [
            'balances' => $balances,
            'plants' => Plant::orderBy('name')->get(),
            'products' => Product::with(['category:id,name', 'subCategory:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'category_id', 'sub_category_id']),
            'categories' => Category::orderBy('name')->get(),
            'financialYearRange' => $range,
            'selectedProduct' => $selectedProduct,
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
            $m->rate = $m->unit_price !== null
                ? (float) $m->unit_price
                : $getRateAtTime($m->moved_at, $m->reference_id);
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

    protected function bulkDirection(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';

        return str_contains($name, 'stock-out') ? 'out' : 'in';
    }

    public function bulkUploadForm(Request $request): View
    {
        $direction = $this->bulkDirection($request);
        $settings = $direction === 'in'
            ? session('stock_in_bulk_settings', [])
            : session('stock_out_bulk_settings', []);

        return view('ho.inventory.stocks.bulk-upload', [
            'direction' => $direction,
            'plants' => Plant::orderBy('name')->get(),
            'vehicles' => $direction === 'out'
                ? Vehicle::where('is_active', true)->orderBy('name')->get(['id', 'name', 'registration_no'])
                : collect(),
            'selectedPlantId' => $settings['plant_id'] ?? $request->integer('plant_id'),
            'pendingRows' => $direction === 'in'
                ? session('stock_in_bulk_pending', [])
                : session('stock_out_bulk_pending', []),
            'pendingUpdateMode' => (string) ($settings['update_mode'] ?? 'add'),
            'pendingAutoCreateProducts' => (bool) ($settings['auto_create_products'] ?? false),
            'pendingOutMode' => (string) ($settings['out_mode'] ?? 'adjust'),
            'pendingAutoCreateVehicles' => (bool) ($settings['auto_create_vehicles'] ?? false),
            'pendingReason' => (string) ($settings['reason'] ?? ''),
            'catalogProducts' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku']),
        ]);
    }

    public function downloadBulkSample(Request $request): StreamedResponse
    {
        $direction = $this->bulkDirection($request);
        $plantId = $request->integer('plant_id');
        $plant = $plantId ? Plant::find($plantId) : null;
        $label = $direction === 'out' ? 'stock_out' : 'stock_in';

        $filename = $label.'_bulk_upload_sample'.($plant ? '_'.Str::slug($plant->name) : '').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($plantId, $direction) {
            $handle = fopen('php://output', 'w');

            if ($direction === 'out') {
                fputcsv($handle, [
                    'MONTH',
                    'DATE',
                    'Vehicle / Machine',
                    'FOR',
                    'ITEM TYPE',
                    'NAME OF PARTS',
                    'QTY',
                    'UNIT',
                    'RATE',
                    'AMOUNT',
                    'REMARKS',
                ]);
                $this->writeStockOutSampleRows($handle);
                fclose($handle);

                return;
            }

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

    private function writeStockOutSampleRows($handle): void
    {
        fputcsv($handle, ['Apr-26', '1-04-2026', 'CONVEYOR', 'CONVEYOR', 'BEARING', 'BEARING (6206)', '1', 'NOS', '675', '675', '']);
        fputcsv($handle, ['Apr-26', '2-04-2026', 'LOADER', 'LOADER', 'OIL', 'GEAR OIL', '10', 'LTR', '202.96', '2029.6', '']);
        fputcsv($handle, ['Apr-26', '5-04-2026', 'BC-3', 'BC-3', 'ROLLER', 'MS ROLLER 350', '4', 'NOS', '720', '2880', 'MOCWAY']);
    }

    public function bulkUpload(Request $request): RedirectResponse
    {
        $direction = $this->bulkDirection($request);
        $isStockOut = $direction === 'out';
        $hasPendingRows = $request->filled('pending_rows') && is_array($request->input('pending_rows'));

        $request->validate([
            'plant_id' => ['required', 'exists:plants,id'],
            'update_mode' => [$isStockOut ? 'nullable' : 'required', 'in:add,set'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'auto_create_products' => ['nullable', 'boolean'],
            'out_mode' => ['nullable', 'in:adjust,record'],
            'auto_create_vehicles' => ['nullable', 'boolean'],
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

        $plant = Plant::findOrFail($request->integer('plant_id'));
        $updateMode = $isStockOut ? 'out' : $request->string('update_mode')->toString();
        $autoCreate = $isStockOut ? false : $request->boolean('auto_create_products');
        $outMode = $isStockOut ? ($request->input('out_mode') ?: 'adjust') : 'adjust';
        $autoCreateVehicles = $isStockOut && $request->boolean('auto_create_vehicles');
        $reason = $isStockOut ? $request->input('reason') : null;
        $performedBy = (int) auth()->id();

        if ($hasPendingRows) {
            if ($isStockOut) {
                return $this->finishStockOutBulk(
                    $plant,
                    $reason,
                    $outMode,
                    $autoCreateVehicles,
                    $performedBy,
                    $this->stockOutPendingFromRequest($request)
                );
            }

            return $this->finishStockInBulk(
                $plant,
                $updateMode,
                $autoCreate,
                $performedBy,
                $this->stockInPendingFromRequest($request)
            );
        }

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

        $looksLikePurchaseSheet = in_array('supplier name', $normalizedHeaders, true)
            && (
                in_array('reference no', $normalizedHeaders, true)
                || in_array('document type', $normalizedHeaders, true)
                || in_array('product sku name', $normalizedHeaders, true)
            );
        if (! $isStockOut && $looksLikePurchaseSheet) {
            return app(PurchaseController::class)->bulkUpload($request);
        }

        $colCategory = $findHeader(['cat.', 'cat', 'category']);
        $colSubCategory = $findHeader(['item type', 'itemtype', 'sub category', 'subcategory', 'sub_category']);
        $colProdName = $findHeader([
            'name of parts',
            'name of part',
            'item description',
            'itemdescription',
            'product name',
            'productname',
            'item name',
            'itemname',
            'description',
        ]);
        $colPartNo = $findHeader(['part number', 'partnumber', 'part no', 'partno', 'size']);
        $colSku = $findHeader(['sku', 'product sku', 'product code']);
        $colBrand = $findHeader(['company name', 'companyname', 'brand', 'company']);
        $colQty = $findHeader(['qty', 'quantity', 'count']);
        $colUnit = $findHeader(['unit', 'units', 'uom']);
        $colDate = $findHeader(['date', 'moved at', 'movement date', 'transaction date']);
        $colVehicle = $this->findStockOutVehicleColumn($normalizedHeaders);
        $colFor = $findHeader(['for']);
        $colRemarks = $findHeader(['remarks', 'remark', 'notes', 'reason']);

        if ($colProdName === false && $colPartNo === false) {
            return back()->withErrors(['csv_file' => 'File must contain a "NAME OF PARTS" or "Item Description" (or Part Number) column to identify products.']);
        }

        if ($colQty === false) {
            return back()->withErrors(['csv_file' => 'File must contain a "Qty" column.']);
        }

        if (! $isStockOut) {
            return $this->finishStockInBulk(
                $plant,
                $updateMode,
                $autoCreate,
                $performedBy,
                $this->stockInPendingFromFile(
                    $rows,
                    $colCategory,
                    $colSubCategory,
                    $colProdName,
                    $colPartNo,
                    $colSku,
                    $colBrand,
                    $colQty,
                    $colUnit,
                    $colDate
                )
            );
        }

        return $this->finishStockOutBulk(
            $plant,
            $reason,
            $outMode,
            $autoCreateVehicles,
            $performedBy,
            $this->stockOutPendingFromFile(
                $rows,
                $colCategory,
                $colSubCategory,
                $colProdName,
                $colPartNo,
                $colSku,
                $colBrand,
                $colQty,
                $colUnit,
                $colDate,
                $colVehicle,
                $colFor,
                $colRemarks
            )
        );
    }

    private function matchNamedMaster($items, string $name)
    {
        $normalized = MasterName::normalize($name);
        if ($normalized === '') {
            return null;
        }

        return $items->first(function ($item) use ($normalized, $name) {
            if (MasterName::normalize($item->name ?? null) === $normalized) {
                return true;
            }

            return isset($item->slug) && $item->slug === Str::slug($name);
        });
    }

    private function firstOrCreateNamedMaster($items, string $class, string $name, callable $createAttributes, ?callable $extraMatch = null)
    {
        $existing = $this->matchNamedMaster($items, $name);
        if ($existing) {
            return $existing;
        }
        if ($extraMatch) {
            $existing = $items->first(fn ($item) => $extraMatch($item, $name));
            if ($existing) {
                return $existing;
            }
        }

        $model = $class::create($createAttributes($name));
        $items->push($model);

        return $model;
    }

    private function matchBulkUnit($units, string $unitAbbr): ?Unit
    {
        $normalized = MasterName::normalize($unitAbbr);
        if ($normalized === '') {
            return null;
        }

        return $units->first(function (Unit $unit) use ($normalized) {
            return MasterName::normalize($unit->abbreviation) === $normalized
                || MasterName::normalize($unit->name) === $normalized;
        });
    }

    private function matchBulkCatalogProduct(
        $products,
        ?Category $category,
        ?SubCategory $subCategory,
        ?Brand $brand,
        string $prodName,
        string $partNo,
        string $skuValue
    ): ?Product {
        if (! $category) {
            return null;
        }

        $normalizedName = MasterName::normalize($prodName);
        $categoryId = (int) $category->id;
        $subId = $subCategory?->id;
        $brandId = $brand?->id;

        return $products->first(function (Product $product) use ($normalizedName, $categoryId, $subId, $brandId, $prodName, $partNo, $skuValue) {
            if ((int) $product->category_id !== $categoryId) {
                return false;
            }
            if ($product->sub_category_id != $subId) {
                return false;
            }
            if ($product->brand_id != $brandId) {
                return false;
            }
            if ($prodName !== '' && MasterName::normalize($product->name) !== $normalizedName) {
                return false;
            }
            if ($skuValue !== '') {
                return MasterName::matches((string) $product->sku, $skuValue);
            }
            if ($partNo === '') {
                return $product->size === null || trim((string) $product->size) === '';
            }

            return MasterName::matches((string) $product->size, $partNo);
        });
    }

    private function matchStockOutProductsByName($products, $subCategories, string $prodName, string $subName)
    {
        $normalizedName = MasterName::normalize($prodName);
        if ($normalizedName === '') {
            return collect();
        }

        $matches = $products->filter(
            fn (Product $product) => MasterName::normalize($product->name) === $normalizedName
        );

        if ($subName !== '' && $matches->count() > 1) {
            $subIds = $subCategories
                ->filter(fn (SubCategory $item) => MasterName::matches($item->name, $subName) || $item->slug === Str::slug($subName))
                ->pluck('id');

            $narrowed = $matches->filter(
                fn (Product $product) => $subIds->contains($product->sub_category_id)
            );
            if ($narrowed->isNotEmpty()) {
                $matches = $narrowed;
            }
        }

        return $matches->values();
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     */
    private function finishStockInBulk(
        Plant $plant,
        string $updateMode,
        bool $autoCreate,
        int $performedBy,
        array $pendingRows
    ): RedirectResponse {
        if ($pendingRows === []) {
            return back()->withErrors(['csv_file' => 'The uploaded file is empty.']);
        }

        [$annotatedRows, $rowErrors] = $this->annotateStockInRows($pendingRows, $autoCreate);

        if ($rowErrors !== []) {
            return redirect()->route('ho.stocks.stock-in.bulk-upload')
                ->with('error', 'File was not imported. Fix the highlighted item names, then import again. Nothing was saved.')
                ->with('stock_in_bulk_pending', $annotatedRows)
                ->with('stock_in_bulk_settings', [
                    'plant_id' => $plant->id,
                    'update_mode' => $updateMode,
                    'auto_create_products' => $autoCreate,
                ]);
        }

        try {
            [$updatedCount, $createdProductCount, $existingProductCount, $duplicateRowCount, $duplicateRowDetails] = $this->persistStockInRows(
                $plant,
                $updateMode,
                $autoCreate,
                $performedBy,
                $annotatedRows
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['csv_file' => 'Error processing bulk stock upload: '.$e->getMessage()]);
        }

        $message = "Bulk stock upload complete for {$plant->name}. {$updatedCount} items updated.";
        if ($createdProductCount > 0) {
            $message .= " {$createdProductCount} new products created.";
        }
        if ($existingProductCount > 0) {
            $message .= " {$existingProductCount} existing catalog products reused.";
        }
        if ($duplicateRowCount > 0) {
            $message .= ' '.$duplicateRowCount.' '.($duplicateRowCount === 1 ? 'row' : 'rows')
                .' used a product already seen in this file: '.implode('; ', $duplicateRowDetails).'.';
        }

        return redirect()->route('ho.stocks.index')->with('success', $message);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stockInPendingFromRequest(Request $request): array
    {
        $pendingRows = [];
        foreach (array_values($request->input('pending_rows', [])) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $pendingRows[] = [
                'row_num' => (int) ($row['row_num'] ?? 0),
                'category' => trim((string) ($row['category'] ?? '')),
                'subcategory' => trim((string) ($row['subcategory'] ?? '')),
                'product' => trim((string) ($row['product'] ?? '')),
                'part_no' => trim((string) ($row['part_no'] ?? '')),
                'sku' => trim((string) ($row['sku'] ?? '')),
                'brand' => trim((string) ($row['brand'] ?? '')),
                'quantity' => trim((string) ($row['quantity'] ?? '')),
                'unit' => trim((string) ($row['unit'] ?? '')),
                'date' => trim((string) ($row['date'] ?? '')),
                'product_id' => (int) ($row['product_id'] ?? 0),
                'error' => null,
                'product_error' => false,
            ];
        }

        return $pendingRows;
    }

    /**
     * @param  list<array<int, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function stockInPendingFromFile(
        array $rows,
        $colCategory,
        $colSubCategory,
        $colProdName,
        $colPartNo,
        $colSku,
        $colBrand,
        $colQty,
        $colUnit,
        $colDate
    ): array {
        $pendingRows = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row) || empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                continue;
            }

            $getVal = function ($col) use ($row) {
                if ($col === false || ! isset($row[$col])) {
                    return '';
                }
                $value = $row[$col];
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d');
                }

                return trim((string) $value);
            };

            $pendingRows[] = [
                'row_num' => $index + 2,
                'category' => $getVal($colCategory),
                'subcategory' => $getVal($colSubCategory),
                'product' => $getVal($colProdName),
                'part_no' => $getVal($colPartNo),
                'sku' => $getVal($colSku),
                'brand' => $getVal($colBrand),
                'quantity' => $getVal($colQty),
                'unit' => $getVal($colUnit) ?: 'nos',
                'date' => $getVal($colDate),
                'product_id' => 0,
                'error' => null,
                'product_error' => false,
            ];
        }

        return $pendingRows;
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function annotateStockInRows(array $pendingRows, bool $autoCreate): array
    {
        $allCategories = Category::all();
        $allSubCategories = SubCategory::all();
        $allBrands = Brand::all();
        $allProducts = Product::all();
        $annotated = [];
        $rowErrors = [];

        foreach ($pendingRows as $dto) {
            $rowNum = (int) ($dto['row_num'] ?? 0);
            $errors = [];
            $productError = false;
            $prodName = (string) ($dto['product'] ?? '');
            $partNo = (string) ($dto['part_no'] ?? '');
            $skuValue = (string) ($dto['sku'] ?? '');
            $catName = (string) ($dto['category'] ?? '');
            $subName = (string) ($dto['subcategory'] ?? '');
            $brandName = (string) ($dto['brand'] ?? '');
            $rawQty = (string) ($dto['quantity'] ?? '');
            $rawDate = (string) ($dto['date'] ?? '');

            if ($rawDate !== '') {
                try {
                    $dto['date'] = $this->parseBulkMovementDate($rawDate);
                } catch (\Throwable $e) {
                    $errors[] = "Invalid date '{$rawDate}'. Please use a valid date value.";
                }
            }

            if (! is_numeric($rawQty) || (float) $rawQty < 0) {
                $errors[] = "Invalid quantity '{$rawQty}'. Must be a non-negative number.";
            }

            if ($prodName === '' && $partNo === '' && $skuValue === '') {
                $errors[] = 'Item Description or Part Number is required.';
                $productError = true;
            }

            $product = $this->resolveStockInCatalogProduct(
                $allProducts,
                $allCategories,
                $allSubCategories,
                $allBrands,
                $dto
            );

            $willCreate = false;
            if (! $product && ! $productError) {
                $willCreate = $autoCreate && $catName !== '' && $prodName !== '';
                if (! $willCreate) {
                    $errors[] = "Product '".($prodName ?: $partNo)."' not found. Search and select the catalog item.";
                    $productError = true;
                }
            }

            $error = $errors === [] ? null : implode(' ', $errors);
            $dto['error'] = $error;
            $dto['product_error'] = $productError;
            $dto['will_create'] = $willCreate;
            $dto['product_id'] = $product?->id ?? (int) ($dto['product_id'] ?? 0);
            $annotated[] = $dto;
            if ($error !== null) {
                $rowErrors[] = "Row {$rowNum}: {$error}";
            }
        }

        return [$annotated, $rowErrors];
    }

    private function resolveStockInCatalogProduct(
        $allProducts,
        $allCategories,
        $allSubCategories,
        $allBrands,
        array $dto
    ): ?Product {
        $productId = (int) ($dto['product_id'] ?? 0);
        if ($productId > 0) {
            return $allProducts->firstWhere('id', $productId);
        }

        $prodName = (string) ($dto['product'] ?? '');
        $partNo = (string) ($dto['part_no'] ?? '');
        $skuValue = (string) ($dto['sku'] ?? '');
        $catName = (string) ($dto['category'] ?? '');
        $subName = (string) ($dto['subcategory'] ?? '');
        $brandName = (string) ($dto['brand'] ?? '');

        $category = $this->matchNamedMaster($allCategories, $catName);
        $subCategory = ($category && $subName !== '')
            ? $allSubCategories->first(function (SubCategory $item) use ($category, $subName) {
                return (int) $item->category_id === (int) $category->id
                    && (MasterName::matches($item->name, $subName) || $item->slug === Str::slug($subName));
            })
            : null;
        $brand = $this->matchNamedMaster($allBrands, $brandName);

        $product = $this->matchBulkCatalogProduct(
            $allProducts,
            $category,
            $subCategory,
            $brand,
            $prodName,
            $partNo,
            $skuValue
        );

        if ($product) {
            return $product;
        }

        $namedMatches = $this->matchStockOutProductsByName($allProducts, $allSubCategories, $prodName, $subName);
        if ($namedMatches->isEmpty()) {
            return null;
        }

        $partMatches = $namedMatches->filter(function (Product $item) use ($partNo, $skuValue) {
            if ($skuValue !== '' && MasterName::matches((string) $item->sku, $skuValue)) {
                return true;
            }
            if ($partNo === '') {
                return $item->size === null || trim((string) $item->size) === '';
            }

            return MasterName::matches((string) $item->size, $partNo);
        });

        return $partMatches->count() === 1 ? $partMatches->first() : null;
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     * @return array{0: int, 1: int, 2: int, 3: int, 4: list<string>}
     */
    private function persistStockInRows(
        Plant $plant,
        string $updateMode,
        bool $autoCreate,
        int $performedBy,
        array $pendingRows
    ): array {
        $allCategories = Category::all();
        $allSubCategories = SubCategory::all();
        $allBrands = Brand::all();
        $allUnits = Unit::all();
        $allProducts = Product::all();
        $updatedCount = 0;
        $createdProductCount = 0;
        $existingProductCount = 0;
        $duplicateRowCount = 0;
        $duplicateRowDetails = [];
        $seenProductIds = [];

        DB::beginTransaction();
        try {
            foreach ($pendingRows as $dto) {
                $prodName = (string) ($dto['product'] ?? '');
                $partNo = (string) ($dto['part_no'] ?? '');
                $skuValue = (string) ($dto['sku'] ?? '');
                $catName = (string) ($dto['category'] ?? '');
                $subName = (string) ($dto['subcategory'] ?? '');
                $brandName = (string) ($dto['brand'] ?? '');
                $unitAbbr = (string) ($dto['unit'] ?? 'nos') ?: 'nos';
                $qty = (float) ($dto['quantity'] ?? 0);
                $movementDate = (string) ($dto['date'] ?? '') ?: null;

                $product = $this->resolveStockInCatalogProduct(
                    $allProducts,
                    $allCategories,
                    $allSubCategories,
                    $allBrands,
                    $dto
                );

                $createdThisRow = false;
                if (! $product && $autoCreate && $catName !== '' && $prodName !== '') {
                    $product = $this->createStockInProductFromRow(
                        $allCategories,
                        $allSubCategories,
                        $allBrands,
                        $allUnits,
                        $allProducts,
                        $catName,
                        $subName,
                        $brandName,
                        $prodName,
                        $partNo,
                        $unitAbbr
                    );
                    $createdProductCount++;
                    $createdThisRow = true;
                }

                if (! $product || $qty <= 0) {
                    throw new \RuntimeException('Row '.((int) ($dto['row_num'] ?? 0)).' failed unexpectedly during save.');
                }

                $rowNum = (int) ($dto['row_num'] ?? 0);
                if (isset($seenProductIds[$product->id])) {
                    $duplicateRowCount++;
                    $firstRow = $seenProductIds[$product->id];
                    $label = $prodName !== '' ? $prodName : ($partNo !== '' ? $partNo : 'item');
                    if ($partNo !== '' && $prodName !== '') {
                        $label .= ', part '.$partNo;
                    }
                    $duplicateRowDetails[] = "Excel row {$rowNum} ({$label}) same as row {$firstRow}";
                } else {
                    $seenProductIds[$product->id] = $rowNum;
                    if (! $createdThisRow) {
                        $existingProductCount++;
                    }
                }

                if ($updateMode === 'add') {
                    $this->stockService->recordIn(
                        plantId: $plant->id,
                        productId: $product->id,
                        quantity: $qty,
                        performedBy: $performedBy,
                        referenceType: StockReferenceType::StockIn,
                        meta: [
                            'notes' => 'Bulk stock upload (Stock In) for '.$plant->name,
                        ],
                        movedAt: $movementDate
                    );
                    $updatedCount++;
                } else {
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
                                meta: ['notes' => 'Bulk stock adjustment (Set balance to '.$qty.') for '.$plant->name],
                                movedAt: $movementDate
                            );
                        } else {
                            $this->stockService->recordOut(
                                plantId: $plant->id,
                                productId: $product->id,
                                quantity: abs($delta),
                                referenceType: StockReferenceType::Adjustment,
                                referenceId: null,
                                performedBy: $performedBy,
                                notes: 'Bulk stock adjustment (Set balance to '.$qty.') for '.$plant->name,
                                movedAt: $movementDate
                            );
                        }
                        $updatedCount++;
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [$updatedCount, $createdProductCount, $existingProductCount, $duplicateRowCount, $duplicateRowDetails];
    }

    private function createStockInProductFromRow(
        $allCategories,
        $allSubCategories,
        $allBrands,
        $allUnits,
        $allProducts,
        string $catName,
        string $subName,
        string $brandName,
        string $prodName,
        string $partNo,
        string $unitAbbr
    ): Product {
        $category = $this->firstOrCreateNamedMaster(
            $allCategories,
            Category::class,
            $catName,
            fn (string $name) => [
                'slug' => Str::slug($name),
                'name' => $name,
                'is_active' => true,
            ],
            fn (Category $item, string $name) => $item->slug === Str::slug($name)
        );

        $subCategory = null;
        if ($subName !== '') {
            $subCategory = $allSubCategories->first(function (SubCategory $item) use ($category, $subName) {
                return (int) $item->category_id === (int) $category->id
                    && (MasterName::matches($item->name, $subName) || $item->slug === Str::slug($subName));
            });
            if (! $subCategory) {
                $subCategory = SubCategory::create([
                    'category_id' => $category->id,
                    'slug' => Str::slug($subName),
                    'name' => $subName,
                    'is_active' => true,
                ]);
                $allSubCategories->push($subCategory);
            }
        }

        $unit = $this->matchBulkUnit($allUnits, $unitAbbr);
        if (! $unit) {
            $unit = Unit::create([
                'name' => ucfirst($unitAbbr),
                'abbreviation' => strtolower($unitAbbr),
            ]);
            $allUnits->push($unit);
        }

        $brand = null;
        if ($brandName !== '') {
            $brand = $this->firstOrCreateNamedMaster(
                $allBrands,
                Brand::class,
                $brandName,
                fn (string $name) => ['name' => $name]
            );
        }

        $baseSku = $this->buildProductSku(
            $category->name,
            $subCategory?->name ?? '',
            $brand?->name ?? '',
            $prodName,
            $partNo,
            ''
        );
        $sku = $baseSku;
        $counter = 1;
        while ($allProducts->contains(fn (Product $item) => $item->sku === $sku) || Product::where('sku', $sku)->exists()) {
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
        $allProducts->push($product);

        return $product;
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     */
    private function finishStockOutBulk(
        Plant $plant,
        ?string $reason,
        string $outMode,
        bool $autoCreateVehicles,
        int $performedBy,
        array $pendingRows
    ): RedirectResponse {
        if ($pendingRows === []) {
            return back()->withErrors(['csv_file' => 'The uploaded file is empty.']);
        }

        $adjustStock = $outMode !== 'record';
        [$annotatedRows, $rowErrors] = $this->annotateStockOutRows($plant, $pendingRows, $adjustStock, $autoCreateVehicles);

        if ($rowErrors !== []) {
            return redirect()->route('ho.stocks.stock-out.bulk-upload')
                ->with('error', 'File was not imported. Fix the highlighted products and machines, then import again. Nothing was saved.')
                ->with('stock_out_bulk_pending', $annotatedRows)
                ->with('stock_out_bulk_settings', [
                    'plant_id' => $plant->id,
                    'reason' => $reason,
                    'out_mode' => $adjustStock ? 'adjust' : 'record',
                    'auto_create_vehicles' => $autoCreateVehicles,
                ]);
        }

        try {
            $updatedCount = $this->persistStockOutRows(
                $plant,
                $reason,
                $performedBy,
                $annotatedRows,
                $adjustStock,
                $autoCreateVehicles
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['csv_file' => 'Error processing bulk stock out: '.$e->getMessage()]);
        }

        $outcome = $adjustStock ? 'issued' : 'recorded without changing stock';

        return redirect()->route('ho.stocks.index')
            ->with('success', "Bulk stock out complete for {$plant->name}. {$updatedCount} items {$outcome}.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stockOutPendingFromRequest(Request $request): array
    {
        $pendingRows = [];
        foreach (array_values($request->input('pending_rows', [])) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $pendingRows[] = [
                'row_num' => (int) ($row['row_num'] ?? 0),
                'category' => trim((string) ($row['category'] ?? '')),
                'subcategory' => trim((string) ($row['subcategory'] ?? '')),
                'product' => trim((string) ($row['product'] ?? '')),
                'part_no' => trim((string) ($row['part_no'] ?? '')),
                'sku' => trim((string) ($row['sku'] ?? '')),
                'brand' => trim((string) ($row['brand'] ?? '')),
                'quantity' => trim((string) ($row['quantity'] ?? '')),
                'unit' => trim((string) ($row['unit'] ?? '')),
                'date' => trim((string) ($row['date'] ?? '')),
                'remarks' => trim((string) ($row['remarks'] ?? '')),
                'vehicle_for' => trim((string) ($row['vehicle_for'] ?? '')),
                'for_label' => trim((string) ($row['for_label'] ?? '')),
                'vehicle_id' => (int) ($row['vehicle_id'] ?? 0),
                'product_id' => (int) ($row['product_id'] ?? 0),
                'error' => null,
                'product_error' => false,
                'vehicle_error' => false,
            ];
        }

        return $pendingRows;
    }

    /**
     * @param  list<array<int, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function stockOutPendingFromFile(
        array $rows,
        $colCategory,
        $colSubCategory,
        $colProdName,
        $colPartNo,
        $colSku,
        $colBrand,
        $colQty,
        $colUnit,
        $colDate,
        $colVehicle,
        $colFor,
        $colRemarks
    ): array {
        $pendingRows = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row) || empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                continue;
            }

            $getVal = function ($col) use ($row) {
                if ($col === false || ! isset($row[$col])) {
                    return '';
                }
                $value = $row[$col];
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d');
                }

                return trim((string) $value);
            };

            $vehicleFor = $getVal($colVehicle);
            $forLabel = $getVal($colFor);

            $pendingRows[] = [
                'row_num' => $index + 2,
                'category' => $getVal($colCategory),
                'subcategory' => $getVal($colSubCategory),
                'product' => $getVal($colProdName),
                'part_no' => $getVal($colPartNo),
                'sku' => $getVal($colSku),
                'brand' => $getVal($colBrand),
                'quantity' => $getVal($colQty),
                'unit' => $getVal($colUnit) ?: 'nos',
                'date' => $getVal($colDate),
                'remarks' => $getVal($colRemarks),
                'vehicle_for' => $vehicleFor,
                'for_label' => $forLabel,
                'vehicle_id' => 0,
                'product_id' => 0,
                'error' => null,
                'product_error' => false,
                'vehicle_error' => false,
            ];
        }

        return $pendingRows;
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function annotateStockOutRows(Plant $plant, array $pendingRows, bool $adjustStock, bool $autoCreateVehicles): array
    {
        $allCategories = Category::all();
        $allSubCategories = SubCategory::all();
        $allBrands = Brand::all();
        $allProducts = Product::all();
        $allVehicles = Vehicle::where('is_active', true)->get();
        $annotated = [];
        $rowErrors = [];
        $reservedQty = [];

        foreach ($pendingRows as $dto) {
            $rowNum = (int) ($dto['row_num'] ?? 0);
            $errors = [];
            $productError = false;
            $vehicleError = false;
            $prodName = (string) ($dto['product'] ?? '');
            $partNo = (string) ($dto['part_no'] ?? '');
            $skuValue = (string) ($dto['sku'] ?? '');
            $subName = (string) ($dto['subcategory'] ?? '');
            $rawQty = (string) ($dto['quantity'] ?? '');
            $rawDate = (string) ($dto['date'] ?? '');
            $vehicleFor = (string) ($dto['vehicle_for'] ?? '');

            if ($rawDate !== '') {
                try {
                    $dto['date'] = $this->parseBulkMovementDate($rawDate);
                } catch (\Throwable $e) {
                    $errors[] = "Invalid date '{$rawDate}'. Please use a valid date value.";
                }
            }

            if (! is_numeric($rawQty) || (float) $rawQty <= 0) {
                $errors[] = "Invalid quantity '{$rawQty}'. Issue quantity must be greater than 0.";
            }

            if ($prodName === '' && $partNo === '' && $skuValue === '') {
                $errors[] = 'NAME OF PARTS or Item Description is required.';
                $productError = true;
            }

            $product = $this->resolveStockOutCatalogProduct(
                $allProducts,
                $allCategories,
                $allSubCategories,
                $allBrands,
                $dto
            );

            if (! $product && ! $productError) {
                $namedMatches = $this->matchStockOutProductsByName($allProducts, $allSubCategories, $prodName, $subName);
                if ($namedMatches->count() > 1) {
                    $errors[] = "Product '{$prodName}' matched multiple catalog items. Search and select the correct item.";
                } else {
                    $errors[] = "Product '".($prodName ?: $partNo)."' not found. Search and select the catalog item.";
                }
                $productError = true;
            }

            $vehicle = $this->resolveStockOutVehicle($allVehicles, $dto);
            $wantsVehicle = trim($vehicleFor) !== '' || (int) ($dto['vehicle_id'] ?? 0) > 0;
            $willCreateVehicle = false;
            if ($wantsVehicle && ! $vehicle) {
                $willCreateVehicle = $autoCreateVehicles
                    && trim($vehicleFor) !== ''
                    && (int) ($dto['vehicle_id'] ?? 0) === 0;
                if (! $willCreateVehicle) {
                    $vehicleError = true;
                    $errors[] = "Vehicle / Machine '{$vehicleFor}' was not found in the Vehicles list. Select it from the list, same as Stock Out.";
                }
            }

            if ($adjustStock && $product && is_numeric($rawQty) && (float) $rawQty > 0) {
                $qty = (float) $rawQty;
                $reservedQty[$product->id] = ($reservedQty[$product->id] ?? 0) + $qty;
                $available = $this->stockService->getBalance($plant->id, $product->id);
                if ($available < $reservedQty[$product->id]) {
                    $errors[] = "Insufficient stock for '{$product->name}'. Available {$available}, requested {$reservedQty[$product->id]}.";
                }
            }

            $error = $errors === [] ? null : implode(' ', $errors);
            $dto['error'] = $error;
            $dto['product_error'] = $productError;
            $dto['vehicle_error'] = $vehicleError;
            $dto['will_create_vehicle'] = $willCreateVehicle;
            $dto['product_id'] = $product?->id ?? (int) ($dto['product_id'] ?? 0);
            $dto['vehicle_id'] = $vehicle?->id ?? (int) ($dto['vehicle_id'] ?? 0);
            $annotated[] = $dto;
            if ($error !== null) {
                $rowErrors[] = "Row {$rowNum}: {$error}";
            }
        }

        return [$annotated, $rowErrors];
    }

    private function resolveStockOutCatalogProduct(
        $allProducts,
        $allCategories,
        $allSubCategories,
        $allBrands,
        array $dto
    ): ?Product {
        $productId = (int) ($dto['product_id'] ?? 0);
        if ($productId > 0) {
            return $allProducts->firstWhere('id', $productId);
        }

        $prodName = (string) ($dto['product'] ?? '');
        $partNo = (string) ($dto['part_no'] ?? '');
        $skuValue = (string) ($dto['sku'] ?? '');
        $catName = (string) ($dto['category'] ?? '');
        $subName = (string) ($dto['subcategory'] ?? '');
        $brandName = (string) ($dto['brand'] ?? '');

        $category = $this->matchNamedMaster($allCategories, $catName);
        $subCategory = ($category && $subName !== '')
            ? $allSubCategories->first(function (SubCategory $item) use ($category, $subName) {
                return (int) $item->category_id === (int) $category->id
                    && (MasterName::matches($item->name, $subName) || $item->slug === Str::slug($subName));
            })
            : null;
        $brand = $this->matchNamedMaster($allBrands, $brandName);

        $product = $this->matchBulkCatalogProduct(
            $allProducts,
            $category,
            $subCategory,
            $brand,
            $prodName,
            $partNo,
            $skuValue
        );
        if ($product) {
            return $product;
        }

        $namedMatches = $this->matchStockOutProductsByName($allProducts, $allSubCategories, $prodName, $subName);

        return $namedMatches->count() === 1 ? $namedMatches->first() : null;
    }

    private function resolveStockOutVehicle($allVehicles, array $dto): ?Vehicle
    {
        $vehicleId = (int) ($dto['vehicle_id'] ?? 0);
        if ($vehicleId > 0) {
            return $allVehicles->firstWhere('id', $vehicleId);
        }

        $label = trim((string) ($dto['vehicle_for'] ?? ''));
        if ($label === '') {
            return null;
        }

        return $this->matchBulkVehicle($allVehicles, $label);
    }

    private function matchBulkVehicle($vehicles, string $label): ?Vehicle
    {
        $normalized = MasterName::normalize($label);
        if ($normalized === '') {
            return null;
        }

        return $vehicles->first(function (Vehicle $vehicle) use ($normalized) {
            if (MasterName::normalize($vehicle->name) === $normalized) {
                return true;
            }
            if (MasterName::normalize((string) $vehicle->registration_no) === $normalized) {
                return true;
            }

            return MasterName::normalize($vehicle->displayLabel()) === $normalized;
        });
    }

    /**
     * Prefer a dedicated Vehicle / Machine column (same label as Stock Out), then MACHINE, then FOR.
     */
    private function findStockOutVehicleColumn(array $normalizedHeaders)
    {
        $preferred = [
            'vehicle machine',
            'vehicle or machine',
            'name of machine',
            'machine name',
            'vehicle name',
            'used for',
            'machine',
            'vehicle',
        ];

        foreach ($preferred as $candidate) {
            $pos = array_search($candidate, $normalizedHeaders, true);
            if ($pos !== false) {
                return $pos;
            }
        }

        foreach ($normalizedHeaders as $pos => $header) {
            if (preg_match('/\b(vehicle|machine)\b/', (string) $header)) {
                return $pos;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $pendingRows
     */
    private function persistStockOutRows(
        Plant $plant,
        ?string $reason,
        int $performedBy,
        array $pendingRows,
        bool $adjustStock,
        bool $autoCreateVehicles
    ): int {
        $allCategories = Category::all();
        $allSubCategories = SubCategory::all();
        $allBrands = Brand::all();
        $allProducts = Product::all();
        $allVehicles = Vehicle::where('is_active', true)->get();
        $outLines = [];

        foreach ($pendingRows as $dto) {
            $product = $this->resolveStockOutCatalogProduct(
                $allProducts,
                $allCategories,
                $allSubCategories,
                $allBrands,
                $dto
            );
            $vehicle = $this->resolveStockOutVehicle($allVehicles, $dto);
            $vehicleLabel = trim((string) ($dto['vehicle_for'] ?? ''));
            if (! $vehicle && $autoCreateVehicles && $vehicleLabel !== '' && (int) ($dto['vehicle_id'] ?? 0) === 0) {
                $vehicle = Vehicle::create([
                    'name' => $vehicleLabel,
                    'is_active' => true,
                ]);
                $allVehicles->push($vehicle);
            }
            $qty = (float) ($dto['quantity'] ?? 0);
            $movementDate = (string) ($dto['date'] ?? '') ?: null;
            $rowRemarks = trim((string) ($dto['remarks'] ?? ''));
            $forLabel = trim((string) ($dto['for_label'] ?? ''));
            $lineReason = $rowRemarks !== '' ? $rowRemarks : ($forLabel !== '' ? $forLabel : $reason);

            if (! $product || $qty <= 0) {
                throw new \RuntimeException('Row '.((int) ($dto['row_num'] ?? 0)).' failed unexpectedly during save.');
            }

            $outLines[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'moved_at' => $movementDate,
                'vehicle_id' => $vehicle?->id,
                'reason' => $lineReason,
                'notes' => $lineReason ?: 'Bulk stock out for '.$plant->name,
            ];
        }

        if ($outLines === []) {
            throw new \RuntimeException('No valid stock-out rows to import.');
        }

        $notes = $reason ?: 'Bulk stock out for '.$plant->name;
        $meta = [
            'reason' => $reason,
            'notes' => $notes,
        ];

        if ($adjustStock) {
            $this->stockService->recordOutMany(
                $plant->id,
                $outLines,
                StockReferenceType::StockOut,
                null,
                $performedBy,
                $notes,
                $meta
            );
        } else {
            $this->stockService->recordOutRegister(
                $plant->id,
                $outLines,
                StockReferenceType::StockOut,
                null,
                $performedBy,
                $notes,
                $meta
            );
        }

        return count($outLines);
    }

    private function parseBulkMovementDate(string $rawDate): string
    {
        $value = trim($rawDate);
        if ($value === '') {
            throw new \InvalidArgumentException('Empty date');
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 20000 && $numeric < 80000) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($numeric)->format('Y-m-d');
            }
        }

        foreach (['j-m-Y', 'd-m-Y', 'j/m/Y', 'd/m/Y', 'j-n-Y', 'd-n-Y', 'Y-m-d', 'j-M-Y', 'd-M-Y'] as $format) {
            try {
                $parsed = \Carbon\Carbon::createFromFormat('!'.$format, $value);
                if ($parsed !== false) {
                    return $parsed->toDateString();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return \Carbon\Carbon::parse($value)->toDateString();
    }
}
