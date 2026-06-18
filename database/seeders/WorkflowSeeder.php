<?php

namespace Database\Seeders;

use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\ProductUseEntry;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchasePricingService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::where('code', 'PLT-001')->firstOrFail();
        $branchSite = Site::where('code', 'PLT-002')->firstOrFail();

        $siteUser = User::where('email', 'site@gmail.com')->firstOrFail();
        $hoUser = User::where('email', 'ho@gmail.com')->firstOrFail();
        $purchaser = User::where('email', 'purchaser@gmail.com')->firstOrFail();

        $localSupplier = Supplier::where('email', 'supplier@demo.test')->firstOrFail();
        $interstateSupplier = Supplier::where('email', 'tata@demo.test')->firstOrFail();

        $cement = Product::where('sku', 'CEM-OPC-50')->firstOrFail();
        $steel12 = Product::where('sku', 'STL-12MM')->firstOrFail();
        $steel16 = Product::where('sku', 'STL-16MM')->firstOrFail();
        $sand = Product::where('sku', 'SND-RIVER')->firstOrFail();
        $paint = Product::where('sku', 'PNT-EMUL-20')->firstOrFail();

        $mixer = Machine::where('site_id', $site->id)->where('code', 'MX-A')->firstOrFail();

        $this->seedProductRequests($site, $siteUser, $hoUser, $cement, $steel12, $sand, $localSupplier);
        $this->seedPurchaseRequests($site, $siteUser, $hoUser, $steel16, $paint, $localSupplier);
        $this->seedPurchases($site, $purchaser, $siteUser, $hoUser, $localSupplier, $interstateSupplier, $cement, $steel12, $sand, $paint);

        $stockService = app(StockService::class);

        $this->seedStockAndUsage($site, $branchSite, $hoUser, $mixer, $cement, $steel12, $sand, $paint, $stockService);
    }

    private function seedProductRequests(
        Site $site,
        User $siteUser,
        User $hoUser,
        Product $cement,
        Product $steel,
        Product $sand,
        Supplier $supplier
    ): void {
        $submitted = ProductRequest::firstOrCreate(
            ['site_id' => $site->id, 'remarks' => '[demo] Product request — submitted'],
            [
                'requested_by' => $siteUser->id,
                'status' => RequestStatus::Submitted,
            ]
        );
        $this->seedProductRequestItems($submitted, [
            ['product_id' => $cement->id, 'quantity' => 200],
            ['product_id' => $sand->id, 'quantity' => 5],
        ]);

        $inProgress = ProductRequest::firstOrCreate(
            ['site_id' => $site->id, 'remarks' => '[demo] Product request — in progress'],
            [
                'requested_by' => $siteUser->id,
                'status' => RequestStatus::InProgress,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(2),
            ]
        );
        $this->seedProductRequestItems($inProgress, [
            ['product_id' => $steel->id, 'quantity' => 150, 'unit_price' => 68, 'gst_rate' => 18, 'supplier_id' => $supplier->id],
        ]);

        $completed = ProductRequest::firstOrCreate(
            ['site_id' => $site->id, 'remarks' => '[demo] Product request — completed'],
            [
                'requested_by' => $siteUser->id,
                'status' => RequestStatus::Completed,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(5),
            ]
        );
        $this->seedProductRequestItems($completed, [
            ['product_id' => $cement->id, 'quantity' => 50, 'unit_price' => 380, 'gst_rate' => 28, 'supplier_id' => $supplier->id],
        ]);

        PurchaseRequest::firstOrCreate(
            ['site_id' => $site->id, 'product_request_id' => $completed->id],
            [
                'requested_by' => $siteUser->id,
                'site_status' => RequestStatus::Submitted,
                'status' => RequestStatus::Submitted,
                'remarks' => '[demo] Purchase request from completed product request',
            ]
        );

        ProductRequest::firstOrCreate(
            ['site_id' => $site->id, 'remarks' => '[demo] Product request — cancelled'],
            [
                'requested_by' => $siteUser->id,
                'status' => RequestStatus::Cancelled,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(1),
                'decline_reason' => 'Duplicate request — already covered in PR #'.$submitted->id,
            ]
        );
    }

    /**
     * @param  list<array{product_id: int, quantity: float, unit_price?: float, gst_rate?: float, supplier_id?: int}>  $items
     */
    private function seedProductRequestItems(ProductRequest $request, array $items): void
    {
        foreach ($items as $item) {
            ProductRequestItem::firstOrCreate(
                [
                    'product_request_id' => $request->id,
                    'product_id' => $item['product_id'],
                ],
                [
                    'supplier_id' => $item['supplier_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'gst_rate' => $item['gst_rate'] ?? 0,
                ]
            );
        }
    }

    private function seedPurchaseRequests(
        Site $site,
        User $siteUser,
        User $hoUser,
        Product $steel,
        Product $paint,
        Supplier $supplier
    ): void {
        $submitted = PurchaseRequest::firstOrCreate(
            ['site_id' => $site->id, 'remarks' => '[demo] Purchase request — submitted'],
            [
                'requested_by' => $siteUser->id,
                'site_status' => RequestStatus::Submitted,
                'status' => RequestStatus::Submitted,
            ]
        );
        $this->seedPurchaseRequestItems($submitted, [
            ['product_id' => $steel->id, 'quantity' => 200, 'unit_price' => 70, 'supplier_id' => $supplier->id],
        ]);

        PurchaseRequest::firstOrCreate(
            ['site_id' => $site->id, 'remarks' => '[demo] Purchase request — in progress'],
            [
                'requested_by' => $siteUser->id,
                'site_status' => RequestStatus::InProgress,
                'status' => RequestStatus::InProgress,
                'site_reviewed_by' => $siteUser->id,
                'site_reviewed_at' => now()->subDays(3),
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(2),
            ]
        );
        $inProgressPr = PurchaseRequest::where('remarks', '[demo] Purchase request — in progress')->first();
        if ($inProgressPr) {
            $this->seedPurchaseRequestItems($inProgressPr, [
                ['product_id' => $steel->id, 'quantity' => 100, 'unit_price' => 72, 'supplier_id' => $supplier->id],
            ]);
        }

        $readyForPo = PurchaseRequest::firstOrCreate(
            ['site_id' => $site->id, 'remarks' => '[demo] Purchase request — ready for PO'],
            [
                'requested_by' => $siteUser->id,
                'site_status' => RequestStatus::Submitted,
                'status' => RequestStatus::Submitted,
            ]
        );
        $this->seedPurchaseRequestItems($readyForPo, [
            ['product_id' => $paint->id, 'quantity' => 8, 'unit_price' => 3200, 'supplier_id' => $supplier->id],
        ]);

        PurchaseRequest::firstOrCreate(
            ['site_id' => $site->id, 'remarks' => '[demo] Purchase request — cancelled'],
            [
                'requested_by' => $siteUser->id,
                'site_status' => RequestStatus::Cancelled,
                'status' => RequestStatus::Cancelled,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(4),
                'decline_reason' => 'Budget not approved for this quarter.',
            ]
        );
    }

    /**
     * @param  list<array{product_id: int, quantity: float, unit_price: float, supplier_id?: int}>  $items
     */
    private function seedPurchaseRequestItems(PurchaseRequest $request, array $items): void
    {
        foreach ($items as $item) {
            PurchaseRequestItem::firstOrCreate(
                [
                    'purchase_request_id' => $request->id,
                    'product_id' => $item['product_id'],
                ],
                [
                    'supplier_id' => $item['supplier_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]
            );
        }
    }

    private function seedPurchases(
        Site $site,
        User $purchaser,
        User $siteUser,
        User $hoUser,
        Supplier $localSupplier,
        Supplier $interstateSupplier,
        Product $cement,
        Product $steel,
        Product $sand,
        Product $paint
    ): void {
        $pricing = app(PurchasePricingService::class);

        $this->createPurchase(
            'PO-DEMO-PENDING-DISPATCH',
            $site,
            $localSupplier,
            null,
            PurchaseStatus::Ordered,
            false,
            [
                ['product_id' => $cement->id, 'quantity' => 100, 'unit_price' => 380, 'gst_rate' => 28],
            ],
            $pricing,
            ['ordered_at' => now()->subDays(2)]
        );

        $this->createPurchase(
            'PO-DEMO-DISPATCHED',
            $site,
            $localSupplier,
            null,
            PurchaseStatus::Ordered,
            false,
            [
                ['product_id' => $sand->id, 'quantity' => 10, 'unit_price' => 1200, 'gst_rate' => 5],
            ],
            $pricing,
            [
                'ordered_at' => now()->subDays(4),
                'dispatched_at' => now()->subDays(1),
                'dispatched_by' => $purchaser->id,
            ]
        );

        $this->createPurchase(
            'PO-DEMO-DELIVERED',
            $site,
            $localSupplier,
            null,
            PurchaseStatus::Delivered,
            false,
            [
                ['product_id' => $cement->id, 'quantity' => 150, 'unit_price' => 375, 'gst_rate' => 28],
                ['product_id' => $paint->id, 'quantity' => 4, 'unit_price' => 3100, 'gst_rate' => 18],
            ],
            $pricing,
            [
                'ordered_at' => now()->subDays(10),
                'dispatched_at' => now()->subDays(8),
                'dispatched_by' => $purchaser->id,
                'delivered_at' => now()->subDays(6),
                'received_by' => $siteUser->id,
                'received_at' => now()->subDays(6),
                'supplier_invoice_no' => 'INV-DEMO-001',
                'supplier_invoice_date' => now()->subDays(7)->toDateString(),
            ]
        );

        $this->createPurchase(
            'PO-DEMO-INTERSTATE',
            $site,
            $interstateSupplier,
            null,
            PurchaseStatus::Ordered,
            true,
            [
                ['product_id' => $steel->id, 'quantity' => 500, 'unit_price' => 65, 'gst_rate' => 18],
            ],
            $pricing,
            [
                'ordered_at' => now()->subDays(3),
                'dispatched_at' => now()->subDays(2),
                'dispatched_by' => $purchaser->id,
            ]
        );
    }

    /**
     * @param  list<array{product_id: int, quantity: float, unit_price: float, gst_rate: float}>  $items
     */
    private function createPurchase(
        string $referenceNo,
        Site $site,
        Supplier $supplier,
        ?PurchaseRequest $purchaseRequest,
        PurchaseStatus $status,
        bool $isInterstate,
        array $items,
        PurchasePricingService $pricing,
        array $meta = []
    ): Purchase {
        $lineCalcs = [];
        $itemsToCreate = [];

        foreach ($items as $item) {
            $calc = $pricing->calculateLine(
                (float) $item['quantity'],
                (float) $item['unit_price'],
                (float) $item['gst_rate'],
                $isInterstate
            );
            $lineCalcs[] = $calc;
            $itemsToCreate[] = array_merge($item, $calc);
        }

        $totals = $pricing->sumLines($lineCalcs);

        $purchase = Purchase::firstOrCreate(
            ['reference_no' => $referenceNo],
            array_merge([
                'site_id' => $site->id,
                'supplier_id' => $supplier->id,
                'purchase_request_id' => $purchaseRequest?->id,
                'status' => $status,
                'is_interstate' => $isInterstate,
                'subtotal' => $totals['subtotal'],
                'total_cgst' => $totals['total_cgst'],
                'total_sgst' => $totals['total_sgst'],
                'total_igst' => $totals['total_igst'],
                'total_gst' => $totals['total_gst'],
                'grand_total' => $totals['grand_total'],
                'ordered_at' => $meta['ordered_at'] ?? now(),
                'notes' => '[demo] Seeded purchase order',
            ], array_intersect_key($meta, array_flip([
                'dispatched_at', 'dispatched_by', 'delivered_at',
                'received_by', 'received_at', 'supplier_invoice_no', 'supplier_invoice_date',
            ])))
        );

        if ($purchase->items()->count() === 0) {
            foreach ($itemsToCreate as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'gst_rate' => $item['gst_rate'],
                    'taxable_amount' => $item['taxable_amount'],
                    'cgst_amount' => $item['cgst_amount'],
                    'sgst_amount' => $item['sgst_amount'],
                    'igst_amount' => $item['igst_amount'],
                    'line_total' => $item['line_total'],
                    'received_quantity' => 0,
                ]);
            }
        }

        return $purchase;
    }

    private function seedStockAndUsage(
        Site $site,
        Site $branchSite,
        User $hoUser,
        Machine $mixer,
        Product $cement,
        Product $steel,
        Product $sand,
        Product $paint,
        StockService $stockService
    ): void {
        $deliveredPurchase = Purchase::where('reference_no', 'PO-DEMO-DELIVERED')->first();

        if ($deliveredPurchase && ! StockMovement::query()
            ->where('reference_type', StockReferenceType::Purchase)
            ->where('reference_id', $deliveredPurchase->id)
            ->exists()) {
            DB::transaction(function () use ($deliveredPurchase, $hoUser, $stockService) {
                $lines = $deliveredPurchase->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => (float) $item->quantity,
                ])->all();

                $stockService->recordInFromPurchase(
                    $deliveredPurchase,
                    $lines,
                    $hoUser->id,
                    ['notes' => '[demo] Stock in from delivered PO']
                );
            });
        }

        if ($stockService->getBalance($site->id, $steel->id) < 50) {
            $stockService->recordIn(
                $site->id,
                $steel->id,
                80,
                $hoUser->id,
                StockReferenceType::StockIn,
                null,
                ['notes' => '[demo] Manual stock in — steel (low stock scenario)']
            );
        }

        if ($stockService->getBalance($site->id, $sand->id) < 3) {
            $stockService->recordIn(
                $site->id,
                $sand->id,
                5,
                $hoUser->id,
                StockReferenceType::StockIn,
                null,
                ['notes' => '[demo] Manual stock in — sand']
            );
        }

        if (! StockMovement::where('notes', '[demo] Stock out — site consumption')->exists()) {
            if ($stockService->getBalance($site->id, $sand->id) >= 2) {
                $stockService->recordOut(
                    $site->id,
                    $sand->id,
                    2,
                    StockReferenceType::StockOut,
                    null,
                    $hoUser->id,
                    '[demo] Stock out — site consumption',
                    ['machine_id' => $mixer->id, 'photo_confirmed' => true, 'reason' => 'Daily production use']
                );
            }
        }

        if (! ProductUseEntry::where('notes', '[demo] Product life — mixer usage')->exists()) {
            DB::transaction(function () use ($site, $cement, $mixer, $hoUser, $stockService) {
                if ($stockService->getBalance($site->id, $cement->id) < 15) {
                    return;
                }

                $first = ProductUseEntry::create([
                    'site_id' => $site->id,
                    'product_id' => $cement->id,
                    'machine_id' => $mixer->id,
                    'quantity' => 10,
                    'life_hours' => 48,
                    'usage_count' => 3,
                    'used_at' => now()->subDays(2),
                    'recorded_by' => $hoUser->id,
                    'notes' => '[demo] Product life — mixer usage',
                ]);
                $stockService->recordProductUse($first);

                $second = ProductUseEntry::create([
                    'site_id' => $site->id,
                    'product_id' => $cement->id,
                    'machine_id' => $mixer->id,
                    'quantity' => 5,
                    'life_hours' => 24,
                    'usage_count' => 2,
                    'used_at' => now()->subDay(),
                    'recorded_by' => $hoUser->id,
                    'notes' => '[demo] Product life — repeat usage for reports',
                ]);
                $stockService->recordProductUse($second);
            });
        }

        if ($stockService->getBalance($branchSite->id, $paint->id) === 0.0) {
            $stockService->recordIn(
                $branchSite->id,
                $paint->id,
                6,
                $hoUser->id,
                StockReferenceType::StockIn,
                null,
                ['notes' => '[demo] Branch site opening stock — paint']
            );
        }

        if (! StockMovement::where('type', StockMovementType::In)
            ->where('site_id', $branchSite->id)
            ->where('product_id', $cement->id)
            ->exists()) {
            $stockService->recordIn(
                $branchSite->id,
                $cement->id,
                40,
                $hoUser->id,
                StockReferenceType::StockIn,
                null,
                ['notes' => '[demo] Branch site opening stock — cement']
            );
        }
    }
}
