<?php

namespace Database\Seeders;

use App\Enums\PurchaseStatus;
use App\Enums\RequestStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use App\Models\SubCategory;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\ProductUseEntry;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Plant;
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
        // Look up real catalog products to build the workflow with
        $cement = Product::where('name', 'HYVA BOLT NUT 12X40')->first() ?: Product::first();
        $steel12 = Product::where('name', 'BEARING 22217')->first() ?: Product::first();
        $steel16 = Product::where('name', 'CRUSHER IMPACT ROLLER 200MM')->first() ?: Product::first();
        $sand = Product::where('name', 'BATTERY 12V')->first() ?: Product::first();
        $paint = Product::where('name', 'AIR FILTER SET 15KVA')->first() ?: Product::first();

        $plant = Plant::where('code', 'PLT-001')->firstOrFail();
        $branchPlant = Plant::where('code', 'PLT-002')->firstOrFail();

        $plantUser = User::where('email', 'plant@gmail.com')->firstOrFail();
        $hoUser = User::where('email', 'ho@gmail.com')->firstOrFail();
        $purchaser = User::where('email', 'purchaser@gmail.com')->firstOrFail();

        $localSupplier = Supplier::where('email', 'supplier@demo.test')->firstOrFail();
        $ultratechSupplier = Supplier::where('email', 'ultratech@demo.test')->firstOrFail();
        $interstateSupplier = Supplier::where('email', 'tata@demo.test')->firstOrFail();

        $cementSub = $cement->subCategory;

        $this->seedProductRequests($plant, $plantUser, $hoUser, $cement, $steel12, $sand, $localSupplier, $ultratechSupplier);
        $this->seedPurchaseRequests($plant, $plantUser, $hoUser, $steel16, $paint, $sand, $cement, $localSupplier, $ultratechSupplier);
        $this->seedPurchases($plant, $purchaser, $plantUser, $hoUser, $localSupplier, $ultratechSupplier, $interstateSupplier, $cement, $steel12, $sand, $paint);

        $stockService = app(StockService::class);

        $this->seedStockAndUsage($plant, $branchPlant, $hoUser, $cementSub, $cement, $steel12, $sand, $paint, $stockService);
    }

    private function seedProductRequests(
        Plant $plant,
        User $plantUser,
        User $hoUser,
        Product $cement,
        Product $steel,
        Product $sand,
        Supplier $localSupplier,
        Supplier $ultratechSupplier
    ): void {
        $submitted = ProductRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Product request — submitted'],
            [
                'requested_by' => $plantUser->id,
                'requester_name' => $plantUser->name,
                'status' => RequestStatus::Submitted,
            ]
        );
        $this->seedProductRequestItems($submitted, [
            ['product_id' => $cement->id, 'quantity' => 200],
            ['product_id' => $sand->id, 'quantity' => 5],
        ]);

        $inProgress = ProductRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Product request — in progress'],
            [
                'requested_by' => $plantUser->id,
                'requester_name' => $plantUser->name,
                'status' => RequestStatus::InProgress,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(2),
            ]
        );
        $this->seedProductRequestItems($inProgress, [
            ['product_id' => $steel->id, 'quantity' => 150, 'unit_price' => 68, 'gst_rate' => 18, 'supplier_id' => $localSupplier->id],
            ['product_id' => $cement->id, 'quantity' => 80, 'unit_price' => 385, 'gst_rate' => 28, 'supplier_id' => $ultratechSupplier->id],
        ]);

        $completed = ProductRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Product request — completed'],
            [
                'requested_by' => $plantUser->id,
                'requester_name' => $plantUser->name,
                'status' => RequestStatus::Completed,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(5),
            ]
        );
        $this->seedProductRequestItems($completed, [
            ['product_id' => $cement->id, 'quantity' => 50, 'unit_price' => 380, 'gst_rate' => 28, 'supplier_id' => $localSupplier->id],
            ['product_id' => $sand->id, 'quantity' => 6, 'unit_price' => 1180, 'gst_rate' => 5, 'supplier_id' => $ultratechSupplier->id],
        ]);

        $completedPr = PurchaseRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'product_request_id' => $completed->id],
            [
                'requested_by' => $plantUser->id,
                'plant_status' => RequestStatus::Submitted,
                'status' => RequestStatus::Submitted,
                'remarks' => '[demo] Purchase request from completed product request (multi-supplier, no PO yet)',
            ]
        );
        $this->seedPurchaseRequestItems($completedPr, [
            ['product_id' => $cement->id, 'quantity' => 50, 'unit_price' => 380, 'supplier_id' => $localSupplier->id],
            ['product_id' => $sand->id, 'quantity' => 6, 'unit_price' => 1180, 'supplier_id' => $ultratechSupplier->id],
        ]);

        ProductRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Product request — cancelled'],
            [
                'requested_by' => $plantUser->id,
                'requester_name' => $plantUser->name,
                'status' => RequestStatus::Cancelled,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(1),
                'decline_reason' => 'Duplicate request — already covered in PR #'.$submitted->id,
            ]
        );

        // Pipeline End-to-End: Product Request
        $pipelineProductReq = ProductRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] End-to-End Pipeline: Product Request'],
            [
                'requested_by' => $plantUser->id,
                'requester_name' => $plantUser->name,
                'status' => RequestStatus::Completed,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(12),
            ]
        );
        $this->seedProductRequestItems($pipelineProductReq, [
            ['product_id' => $cement->id, 'quantity' => 100, 'unit_price' => 380, 'gst_rate' => 28, 'supplier_id' => $localSupplier->id],
        ]);
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
        Plant $plant,
        User $plantUser,
        User $hoUser,
        Product $steel,
        Product $paint,
        Product $sand,
        Product $cement,
        Supplier $localSupplier,
        Supplier $ultratechSupplier
    ): void {
        $submitted = PurchaseRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Purchase request — submitted'],
            [
                'requested_by' => $plantUser->id,
                'plant_status' => RequestStatus::Submitted,
                'status' => RequestStatus::Submitted,
            ]
        );
        $this->seedPurchaseRequestItems($submitted, [
            ['product_id' => $steel->id, 'quantity' => 200, 'unit_price' => 70, 'supplier_id' => $localSupplier->id],
        ]);

        PurchaseRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Purchase request — in progress'],
            [
                'requested_by' => $plantUser->id,
                'plant_status' => RequestStatus::InProgress,
                'status' => RequestStatus::InProgress,
                'plant_reviewed_by' => $plantUser->id,
                'plant_reviewed_at' => now()->subDays(3),
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(2),
            ]
        );
        $inProgressPr = PurchaseRequest::where('remarks', '[demo] Purchase request — in progress')->first();
        if ($inProgressPr) {
            $this->seedPurchaseRequestItems($inProgressPr, [
                ['product_id' => $steel->id, 'quantity' => 100, 'unit_price' => 72, 'supplier_id' => $localSupplier->id],
            ]);
        }

        $readyForPo = PurchaseRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Purchase request — ready for PO'],
            [
                'requested_by' => $plantUser->id,
                'plant_status' => RequestStatus::Submitted,
                'status' => RequestStatus::Submitted,
            ]
        );
        $this->seedPurchaseRequestItems($readyForPo, [
            ['product_id' => $paint->id, 'quantity' => 8, 'unit_price' => 3200, 'supplier_id' => $localSupplier->id],
            ['product_id' => $sand->id, 'quantity' => 12, 'unit_price' => 1150, 'supplier_id' => $ultratechSupplier->id],
        ]);

        $multiSupplierPr = PurchaseRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Multi-supplier — POs created'],
            [
                'requested_by' => $plantUser->id,
                'plant_status' => RequestStatus::Completed,
                'status' => RequestStatus::Completed,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(7),
            ]
        );
        $this->seedPurchaseRequestItems($multiSupplierPr, [
            ['product_id' => $cement->id, 'quantity' => 60, 'unit_price' => 378, 'supplier_id' => $ultratechSupplier->id],
            ['product_id' => $steel->id, 'quantity' => 120, 'unit_price' => 66, 'supplier_id' => $localSupplier->id],
        ]);

        PurchaseRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Purchase request — cancelled'],
            [
                'requested_by' => $plantUser->id,
                'plant_status' => RequestStatus::Cancelled,
                'status' => RequestStatus::Cancelled,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(4),
                'decline_reason' => 'Budget not approved for this quarter.',
            ]
        );

        // Pipeline End-to-End: Purchase Request linked to the Product Request
        $pipelineProductReq = ProductRequest::where('remarks', '[demo] End-to-End Pipeline: Product Request')->firstOrFail();

        $pipelinePurchaseReq = PurchaseRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'product_request_id' => $pipelineProductReq->id],
            [
                'requested_by' => $plantUser->id,
                'plant_status' => RequestStatus::Completed,
                'status' => RequestStatus::Completed,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(10),
                'remarks' => '[demo] End-to-End Pipeline: Purchase Request',
            ]
        );
        $this->seedPurchaseRequestItems($pipelinePurchaseReq, [
            ['product_id' => $cement->id, 'quantity' => 100, 'unit_price' => 380, 'supplier_id' => $localSupplier->id],
        ]);
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
        Plant $plant,
        User $purchaser,
        User $plantUser,
        User $hoUser,
        Supplier $localSupplier,
        Supplier $ultratechSupplier,
        Supplier $interstateSupplier,
        Product $cement,
        Product $steel,
        Product $sand,
        Product $paint
    ): void {
        $pricing = app(PurchasePricingService::class);

        $multiSupplierPr = PurchaseRequest::where('remarks', '[demo] Multi-supplier — POs created')->first();

        $this->createPurchase(
            'PO-DEMO-PENDING-DISPATCH',
            $plant,
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
            $plant,
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
            $plant,
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
                'received_by' => $plantUser->id,
                'received_at' => now()->subDays(6),
                'supplier_invoice_no' => 'INV-DEMO-001',
                'supplier_invoice_date' => now()->subDays(7)->toDateString(),
            ]
        );

        $this->createPurchase(
            'PO-DEMO-INTERSTATE',
            $plant,
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

        if ($multiSupplierPr) {
            $this->createPurchase(
                'PO-DEMO-MULTI-CEM',
                $plant,
                $ultratechSupplier,
                $multiSupplierPr,
                PurchaseStatus::Ordered,
                false,
                [
                    ['product_id' => $cement->id, 'quantity' => 60, 'unit_price' => 378, 'gst_rate' => 28],
                ],
                $pricing,
                [
                    'ordered_at' => now()->subDays(8),
                    'notes' => '[demo] Multi-supplier PR — cement PO (Ultratech)',
                ]
            );

            $this->createPurchase(
                'PO-DEMO-MULTI-STL',
                $plant,
                $localSupplier,
                $multiSupplierPr,
                PurchaseStatus::Ordered,
                false,
                [
                    ['product_id' => $steel->id, 'quantity' => 120, 'unit_price' => 66, 'gst_rate' => 18],
                ],
                $pricing,
                [
                    'ordered_at' => now()->subDays(8),
                    'notes' => '[demo] Multi-supplier PR — steel PO (local supplier)',
                ]
            );
        }

        // Pipeline End-to-End: Purchase Order linked to the Purchase Request
        $pipelinePurchaseReq = PurchaseRequest::where('remarks', '[demo] End-to-End Pipeline: Purchase Request')->firstOrFail();

        $this->createPurchase(
            'PO-PIPELINE-DELIVERED',
            $plant,
            $localSupplier,
            $pipelinePurchaseReq,
            PurchaseStatus::Delivered,
            false,
            [
                ['product_id' => $cement->id, 'quantity' => 100, 'unit_price' => 380, 'gst_rate' => 28],
            ],
            $pricing,
            [
                'ordered_at' => now()->subDays(9),
                'dispatched_at' => now()->subDays(8),
                'dispatched_by' => $purchaser->id,
                'delivered_at' => now()->subDays(7),
                'received_by' => $plantUser->id,
                'received_at' => now()->subDays(7),
                'supplier_invoice_no' => 'INV-PIPE-001',
                'supplier_invoice_date' => now()->subDays(8)->toDateString(),
            ]
        );
    }

    /**
     * @param  list<array{product_id: int, quantity: float, unit_price: float, gst_rate: float}>  $items
     */
    private function createPurchase(
        string $referenceNo,
        Plant $plant,
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
                'plant_id' => $plant->id,
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
        Plant $plant,
        Plant $branchPlant,
        User $hoUser,
        SubCategory $cementSub,
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

        if ($stockService->getBalance($plant->id, $steel->id) < 50) {
            $stockService->recordIn(
                $plant->id,
                $steel->id,
                80,
                $hoUser->id,
                StockReferenceType::StockIn,
                null,
                ['notes' => '[demo] Manual stock in — steel (low stock scenario)']
            );
        }

        if ($stockService->getBalance($plant->id, $sand->id) < 3) {
            $stockService->recordIn(
                $plant->id,
                $sand->id,
                5,
                $hoUser->id,
                StockReferenceType::StockIn,
                null,
                ['notes' => '[demo] Manual stock in — sand']
            );
        }

        if (! StockMovement::where('notes', '[demo] Stock out — plant consumption')->exists()) {
            if ($stockService->getBalance($plant->id, $sand->id) >= 2) {
                $stockService->recordOut(
                    $plant->id,
                    $sand->id,
                    2,
                    StockReferenceType::StockOut,
                    null,
                    $hoUser->id,
                    '[demo] Stock out — plant consumption',
                    ['sub_category_id' => $cementSub->id, 'photo_confirmed' => true, 'reason' => 'Daily production use']
                );
            }
        }

        if (! ProductUseEntry::where('notes', '[demo] Product life — mixer usage')->exists()) {
            DB::transaction(function () use ($plant, $cement, $cementSub, $hoUser, $stockService) {
                if ($stockService->getBalance($plant->id, $cement->id) < 15) {
                    return;
                }

                $first = ProductUseEntry::create([
                    'plant_id' => $plant->id,
                    'product_id' => $cement->id,
                    'sub_category_id' => $cementSub->id,
                    'quantity' => 10,
                    'life_hours' => 48,
                    'usage_count' => 3,
                    'used_at' => now()->subDays(2),
                    'recorded_by' => $hoUser->id,
                    'notes' => '[demo] Product life — mixer usage',
                ]);
                $stockService->recordProductUse($first);

                $second = ProductUseEntry::create([
                    'plant_id' => $plant->id,
                    'product_id' => $cement->id,
                    'sub_category_id' => $cementSub->id,
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

        if ($stockService->getBalance($branchPlant->id, $paint->id) === 0.0) {
            $stockService->recordIn(
                $branchPlant->id,
                $paint->id,
                6,
                $hoUser->id,
                StockReferenceType::StockIn,
                null,
                ['notes' => '[demo] Branch plant opening stock — paint']
            );
        }

        if (! StockMovement::where('type', StockMovementType::In)
            ->where('plant_id', $branchPlant->id)
            ->where('product_id', $cement->id)
            ->exists()) {
            $stockService->recordIn(
                $branchPlant->id,
                $cement->id,
                40,
                $hoUser->id,
                StockReferenceType::StockIn,
                null,
                ['notes' => '[demo] Branch plant opening stock — cement']
            );
        }
        // -------------------------------------------------------
        // Pipeline End-to-End: Stock In from delivered PO
        // -------------------------------------------------------
        $pipelinePo = Purchase::where('reference_no', 'PO-PIPELINE-DELIVERED')->first();

        if ($pipelinePo && ! StockMovement::query()
            ->where('reference_type', StockReferenceType::Purchase)
            ->where('reference_id', $pipelinePo->id)
            ->exists()) {
            DB::transaction(function () use ($pipelinePo, $hoUser, $stockService) {
                $lines = $pipelinePo->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity'   => (float) $item->quantity,
                ])->all();

                $stockService->recordInFromPurchase(
                    $pipelinePo,
                    $lines,
                    $hoUser->id,
                    ['notes' => '[demo] Pipeline — Stock In from delivered PO (PO-PIPELINE-DELIVERED)']
                );
            });
        }

        // -------------------------------------------------------
        // Pipeline End-to-End: Stock Out (usage) against cement
        // -------------------------------------------------------
        if (! StockMovement::where('notes', '[demo] Pipeline — Stock Out (plant consumption)')->exists()) {
            if ($stockService->getBalance($plant->id, $cement->id) >= 30) {
                $stockService->recordOut(
                    $plant->id,
                    $cement->id,
                    30,
                    StockReferenceType::StockOut,
                    null,
                    $hoUser->id,
                    '[demo] Pipeline — Stock Out (plant consumption)',
                    ['sub_category_id' => $cementSub->id, 'photo_confirmed' => true, 'reason' => 'Pipeline end-to-end consumption']
                );
            }
        }

        // -------------------------------------------------------
        // Pipeline End-to-End: Product Life / Use Entry for cement
        // -------------------------------------------------------
        if (! ProductUseEntry::where('notes', '[demo] Pipeline — Product Life (cement use)')->exists()) {
            DB::transaction(function () use ($plant, $cement, $cementSub, $hoUser, $stockService) {
                if ($stockService->getBalance($plant->id, $cement->id) < 10) {
                    return;
                }

                $entry = ProductUseEntry::create([
                    'plant_id'       => $plant->id,
                    'product_id'     => $cement->id,
                    'sub_category_id'=> $cementSub->id,
                    'quantity'       => 10,
                    'life_hours'     => 72,
                    'usage_count'    => 5,
                    'used_at'        => now()->subDays(6),
                    'recorded_by'    => $hoUser->id,
                    'notes'          => '[demo] Pipeline — Product Life (cement use)',
                ]);
                $stockService->recordProductUse($entry);
            });
        }
    }
}
