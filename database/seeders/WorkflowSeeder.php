<?php

namespace Database\Seeders;

use App\Enums\PurchaseDocumentType;
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
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
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

        $plant = Plant::where('code', 'PMCMPL-01')->first() ?: Plant::firstOrFail();
        $branchPlant = Plant::where('code', 'PMCMPL-02')->first() ?: Plant::firstOrFail();

        $plantUser = User::where('email', 'piska@gmail.com')->first()
            ?: User::where('email', 'plant@gmail.com')->first()
            ?: User::whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_PLANT_IN_CHARGE))->firstOrFail();
        $hoUser = User::where('email', 'ho@gmail.com')->firstOrFail();
        $purchaser = User::where('email', 'purchaser@gmail.com')->firstOrFail();

        $localSupplier = Supplier::where('email', 'supplier@demo.test')->firstOrFail();
        $ultratechSupplier = Supplier::where('email', 'ultratech@demo.test')->firstOrFail();
        $interstateSupplier = Supplier::where('email', 'tata@demo.test')->firstOrFail();

        $cementSub = $cement->subCategory;

        $this->seedHyvaVehiclesAndProductLife($plant, $hoUser, $stockService = app(StockService::class));

        $this->seedProductRequests($plant, $plantUser, $hoUser, $cement, $steel12, $sand, $localSupplier, $ultratechSupplier);
        $this->seedPurchaseRequests($plant, $plantUser, $hoUser, $steel16, $paint, $sand, $cement, $localSupplier, $ultratechSupplier);
        $this->seedPurchases($plant, $purchaser, $plantUser, $hoUser, $localSupplier, $ultratechSupplier, $interstateSupplier, $cement, $steel12, $sand, $paint);

        $this->seedStockAndUsage($plant, $branchPlant, $hoUser, $cementSub, $cement, $steel12, $sand, $paint, $stockService);
    }

    /**
     * HYVA category: 3 demo vehicles + vehicle-wise product life (filter change with KM readings).
     */
    private function seedHyvaVehiclesAndProductLife(Plant $plant, User $hoUser, StockService $stockService): void
    {
        $hyva = Category::where('name', 'HYVA')->first();
        if (! $hyva) {
            return;
        }

        $vehicles = [
            ['name' => 'HYVA Tipper 01', 'registration_no' => 'JH01HY1001'],
            ['name' => 'HYVA Tipper 02', 'registration_no' => 'JH01HY1002'],
            ['name' => 'HYVA Tipper 03', 'registration_no' => 'JH01HY1003'],
        ];

        $vehicleModels = collect($vehicles)->map(function (array $row) use ($hyva) {
            return Vehicle::firstOrCreate(
                [
                    'category_id' => $hyva->id,
                    'registration_no' => $row['registration_no'],
                ],
                [
                    'name' => $row['name'],
                    'is_active' => true,
                ]
            );
        });

        $filterType = SubCategory::where('category_id', $hyva->id)
            ->where('name', 'FILTER')
            ->first();

        $beltType = SubCategory::where('category_id', $hyva->id)
            ->where('name', 'BELT')
            ->first();

        $filters = Product::where('category_id', $hyva->id)
            ->when($filterType, fn ($q) => $q->where('sub_category_id', $filterType->id))
            ->where('is_active', true)
            ->orderBy('id')
            ->take(3)
            ->get();

        if ($filters->count() < 2) {
            $filters = Product::where('category_id', $hyva->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->take(3)
                ->get();
            $filterType = $filters->first()?->subCategory;
        }

        if ($filters->count() < 2 || ! $filterType) {
            return;
        }

        $oldFilter = $filters[0];
        $newFilter = $filters[1] ?? $filters[0];
        $vehicle = $vehicleModels->first();

        // Ensure stock for product-life consumption
        foreach ([$oldFilter, $newFilter] as $product) {
            if ($stockService->getBalance($plant->id, $product->id) < 5) {
                $stockService->recordIn(
                    $plant->id,
                    $product->id,
                    10,
                    $hoUser->id,
                    StockReferenceType::StockIn,
                    null,
                    ['notes' => '[demo] HYVA opening stock for product life']
                );
            }
        }

        if (! ProductUseEntry::where('notes', '[demo] HYVA Tipper 01 — first FILTER fitment')->exists()) {
            DB::transaction(function () use ($plant, $vehicle, $oldFilter, $filterType, $hoUser, $stockService) {
                $first = ProductUseEntry::create([
                    'plant_id' => $plant->id,
                    'vehicle_id' => $vehicle->id,
                    'product_id' => $oldFilter->id,
                    'replaced_product_id' => null,
                    'sub_category_id' => $filterType->id,
                    'meter_type' => 'km',
                    'current_reading' => 40000,
                    'previous_reading' => null,
                    'quantity' => 1,
                    'life_hours' => 0,
                    'usage_count' => 1,
                    'used_at' => now()->subDays(45),
                    'recorded_by' => $hoUser->id,
                    'notes' => '[demo] HYVA Tipper 01 — first FILTER fitment',
                ]);
                $stockService->recordProductUse($first);
            });
        }

        if (! ProductUseEntry::where('notes', '[demo] HYVA Tipper 01 — FILTER change (prev life used)')->exists()) {
            DB::transaction(function () use ($plant, $vehicle, $oldFilter, $newFilter, $filterType, $hoUser, $stockService) {
                $change = ProductUseEntry::create([
                    'plant_id' => $plant->id,
                    'vehicle_id' => $vehicle->id,
                    'product_id' => $newFilter->id,
                    'replaced_product_id' => $oldFilter->id,
                    'sub_category_id' => $filterType->id,
                    'meter_type' => 'km',
                    'current_reading' => 45200,
                    'previous_reading' => 40000,
                    'quantity' => 1,
                    'life_hours' => 5200, // previous filter used 5200 km
                    'usage_count' => 1,
                    'used_at' => now()->subDays(2),
                    'recorded_by' => $hoUser->id,
                    'notes' => '[demo] HYVA Tipper 01 — FILTER change (prev life used)',
                ]);
                $stockService->recordProductUse($change);
            });
        }

        // Optional belt demo on Tipper 02 so create form has another vehicle history
        if ($beltType) {
            $belts = Product::where('category_id', $hyva->id)
                ->where('sub_category_id', $beltType->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->take(2)
                ->get();

            $tipper2 = $vehicleModels->get(1);
            if ($belts->isNotEmpty() && $tipper2) {
                $belt = $belts->first();
                if ($stockService->getBalance($plant->id, $belt->id) < 2) {
                    $stockService->recordIn(
                        $plant->id,
                        $belt->id,
                        5,
                        $hoUser->id,
                        StockReferenceType::StockIn,
                        null,
                        ['notes' => '[demo] HYVA belt stock for product life']
                    );
                }

                if (! ProductUseEntry::where('notes', '[demo] HYVA Tipper 02 — first BELT fitment')->exists()) {
                    DB::transaction(function () use ($plant, $tipper2, $belt, $beltType, $hoUser, $stockService) {
                        $entry = ProductUseEntry::create([
                            'plant_id' => $plant->id,
                            'vehicle_id' => $tipper2->id,
                            'product_id' => $belt->id,
                            'replaced_product_id' => null,
                            'sub_category_id' => $beltType->id,
                            'meter_type' => 'km',
                            'current_reading' => 28000,
                            'previous_reading' => null,
                            'quantity' => 1,
                            'life_hours' => 0,
                            'usage_count' => 1,
                            'used_at' => now()->subDays(20),
                            'recorded_by' => $hoUser->id,
                            'notes' => '[demo] HYVA Tipper 02 — first BELT fitment',
                        ]);
                        $stockService->recordProductUse($entry);
                    });
                }
            }
        }
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
                'remarks' => '[demo] Purchase request from completed product request (quotation awaiting PO)',
            ]
        );
        $this->seedPurchaseRequestItems($completedPr, [
            [
                'product_id' => $cement->id,
                'quantity' => 50,
                'unit_price' => 380,
                'supplier_id' => $localSupplier->id,
                'product_request_item_id' => $completed->items()->where('product_id', $cement->id)->value('id'),
            ],
            [
                'product_id' => $sand->id,
                'quantity' => 6,
                'unit_price' => 1180,
                'supplier_id' => $ultratechSupplier->id,
                'product_request_item_id' => $completed->items()->where('product_id', $sand->id)->value('id'),
            ],
        ]);

        // Partial convert demo: steel quoted (Create PO tab), sand leftover stays PENDING
        $partial = ProductRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Product request — partial quotation (leftover pending)'],
            [
                'requested_by' => $plantUser->id,
                'requester_name' => $plantUser->name,
                'status' => RequestStatus::InProgress,
                'reviewed_by' => $hoUser->id,
                'reviewed_at' => now()->subDays(1),
            ]
        );
        $this->seedProductRequestItems($partial, [
            ['product_id' => $steel->id, 'quantity' => 40, 'unit_price' => 70, 'gst_rate' => 18, 'supplier_id' => $localSupplier->id],
            ['product_id' => $sand->id, 'quantity' => 3, 'unit_price' => 1180, 'gst_rate' => 5, 'supplier_id' => $localSupplier->id],
        ]);

        $partialQuotedItem = $partial->items()->where('product_id', $steel->id)->first();
        $partialPr = PurchaseRequest::firstOrCreate(
            ['plant_id' => $plant->id, 'remarks' => '[demo] Partial quotation PR (steel only)'],
            [
                'requested_by' => $plantUser->id,
                'product_request_id' => $partial->id,
                'plant_status' => RequestStatus::Submitted,
                'status' => RequestStatus::Submitted,
            ]
        );
        if ($partialQuotedItem) {
            $this->seedPurchaseRequestItems($partialPr, [
                [
                    'product_id' => $steel->id,
                    'quantity' => 40,
                    'unit_price' => 70,
                    'supplier_id' => $localSupplier->id,
                    'product_request_item_id' => $partialQuotedItem->id,
                ],
            ]);
        }
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
            [
                'product_id' => $cement->id,
                'quantity' => 100,
                'unit_price' => 380,
                'supplier_id' => $localSupplier->id,
                'product_request_item_id' => $pipelineProductReq->items()->where('product_id', $cement->id)->value('id'),
            ],
        ]);
    }

    /**
     * @param  list<array{product_id: int, quantity: float, unit_price: float, supplier_id?: int, product_request_item_id?: int|null}>  $items
     */
    private function seedPurchaseRequestItems(PurchaseRequest $request, array $items): void
    {
        foreach ($items as $item) {
            $purchaseRequestItem = PurchaseRequestItem::firstOrCreate(
                [
                    'purchase_request_id' => $request->id,
                    'product_id' => $item['product_id'],
                ],
                [
                    'supplier_id' => $item['supplier_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'product_request_item_id' => $item['product_request_item_id'] ?? null,
                ]
            );

            if (! empty($item['product_request_item_id']) && ! $purchaseRequestItem->product_request_item_id) {
                $purchaseRequestItem->update([
                    'product_request_item_id' => $item['product_request_item_id'],
                ]);
            }
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
        $completedPr = PurchaseRequest::where('remarks', '[demo] Purchase request from completed product request (quotation awaiting PO)')->first()
            ?: PurchaseRequest::where('remarks', 'like', '[demo] Purchase request from completed product request%')->first();
        $partialPr = PurchaseRequest::where('remarks', '[demo] Partial quotation PR (steel only)')->first();
        $readyForPo = PurchaseRequest::where('remarks', '[demo] Purchase request — ready for PO')->first();

        // Quotation awaiting PO (Create PO tab + Quotation section)
        if ($completedPr) {
            $this->createPurchase(
                'QT-DEMO-AWAITING-PO',
                $plant,
                $localSupplier,
                $completedPr,
                PurchaseStatus::Ordered,
                false,
                [
                    ['product_id' => $cement->id, 'quantity' => 50, 'unit_price' => 380, 'gst_rate' => 28, 'discount_percent' => 5],
                    ['product_id' => $sand->id, 'quantity' => 6, 'unit_price' => 1180, 'gst_rate' => 5, 'discount_percent' => 0],
                ],
                $pricing,
                [
                    'ordered_at' => now()->subDays(4),
                    'document_type' => PurchaseDocumentType::Quotation,
                    'notes' => '[demo] Quotation awaiting PO conversion',
                    'supplier_invoice_no' => 'SQ-DEMO-001',
                    'supplier_invoice_date' => now()->subDays(4)->toDateString(),
                ]
            );
        }

        if ($partialPr) {
            $this->createPurchase(
                'QT-DEMO-PARTIAL',
                $plant,
                $localSupplier,
                $partialPr,
                PurchaseStatus::Ordered,
                false,
                [
                    ['product_id' => $steel->id, 'quantity' => 40, 'unit_price' => 70, 'gst_rate' => 18, 'discount_percent' => 10],
                ],
                $pricing,
                [
                    'ordered_at' => now()->subDay(),
                    'document_type' => PurchaseDocumentType::Quotation,
                    'notes' => '[demo] Partial request quotation — sand leftover stays PENDING',
                ]
            );
        }

        if ($readyForPo) {
            $this->createPurchase(
                'QT-DEMO-READY',
                $plant,
                $localSupplier,
                $readyForPo,
                PurchaseStatus::Ordered,
                false,
                [
                    ['product_id' => $paint->id, 'quantity' => 8, 'unit_price' => 3200, 'gst_rate' => 18, 'discount_percent' => 0],
                    ['product_id' => $sand->id, 'quantity' => 12, 'unit_price' => 1150, 'gst_rate' => 5, 'discount_percent' => 2],
                ],
                $pricing,
                [
                    'ordered_at' => now()->subDays(3),
                    'document_type' => PurchaseDocumentType::Quotation,
                    'notes' => '[demo] Ready-for-PO request converted to quotation',
                ]
            );
        }

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
                ['product_id' => $steel->id, 'quantity' => 500, 'unit_price' => 65, 'gst_rate' => 18, 'discount_percent' => 5],
            ],
            $pricing,
            [
                'ordered_at' => now()->subDays(3),
                'dispatched_at' => now()->subDays(2),
                'dispatched_by' => $purchaser->id,
            ]
        );

        if ($multiSupplierPr) {
            $multiQuoteCem = $this->createPurchase(
                'QT-DEMO-MULTI-CEM',
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
                    'ordered_at' => now()->subDays(9),
                    'document_type' => PurchaseDocumentType::Quotation,
                    'notes' => '[demo] Multi-supplier quotation — cement (Ultratech)',
                ]
            );

            $multiQuoteStl = $this->createPurchase(
                'QT-DEMO-MULTI-STL',
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
                    'ordered_at' => now()->subDays(9),
                    'document_type' => PurchaseDocumentType::Quotation,
                    'notes' => '[demo] Multi-supplier quotation — steel (local)',
                ]
            );

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
                    'quotation_id' => $multiQuoteCem->id,
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
                    'quotation_id' => $multiQuoteStl->id,
                    'notes' => '[demo] Multi-supplier PR — steel PO (local supplier)',
                ]
            );
        }

        // Pipeline End-to-End: Quotation then PO linked to the Purchase Request
        $pipelinePurchaseReq = PurchaseRequest::where('remarks', '[demo] End-to-End Pipeline: Purchase Request')->firstOrFail();

        $pipelineQuote = $this->createPurchase(
            'QT-PIPELINE',
            $plant,
            $localSupplier,
            $pipelinePurchaseReq,
            PurchaseStatus::Ordered,
            false,
            [
                ['product_id' => $cement->id, 'quantity' => 100, 'unit_price' => 380, 'gst_rate' => 28, 'discount_percent' => 0],
            ],
            $pricing,
            [
                'ordered_at' => now()->subDays(10),
                'document_type' => PurchaseDocumentType::Quotation,
                'notes' => '[demo] Pipeline quotation before PO',
                'supplier_invoice_no' => 'SQ-PIPE-001',
                'supplier_invoice_date' => now()->subDays(10)->toDateString(),
            ]
        );

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
                'quotation_id' => $pipelineQuote->id,
            ]
        );
    }

    /**
     * @param  list<array{product_id: int, quantity: float, unit_price: float, gst_rate: float, discount_percent?: float}>  $items
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
        $documentType = $meta['document_type'] ?? PurchaseDocumentType::PurchaseOrder;
        $lineCalcs = [];
        $itemsToCreate = [];

        foreach ($items as $item) {
            $calc = $pricing->calculateLine(
                (float) $item['quantity'],
                (float) $item['unit_price'],
                (float) $item['gst_rate'],
                $isInterstate,
                (float) ($item['discount_percent'] ?? 0)
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
                'document_type' => $documentType,
                'quotation_id' => $meta['quotation_id'] ?? null,
                'status' => $status,
                'is_interstate' => $isInterstate,
                'subtotal' => $totals['subtotal'],
                'total_cgst' => $totals['total_cgst'],
                'total_sgst' => $totals['total_sgst'],
                'total_igst' => $totals['total_igst'],
                'total_gst' => $totals['total_gst'],
                'grand_total' => $totals['grand_total'],
                'ordered_at' => $meta['ordered_at'] ?? now(),
                'notes' => $meta['notes'] ?? ($documentType === PurchaseDocumentType::Quotation
                    ? '[demo] Seeded quotation'
                    : '[demo] Seeded purchase order'),
            ], array_intersect_key($meta, array_flip([
                'dispatched_at', 'dispatched_by', 'delivered_at',
                'received_by', 'received_at', 'supplier_invoice_no', 'supplier_invoice_date',
            ])))
        );

        if ($purchase->wasRecentlyCreated === false) {
            $purchase->fill([
                'document_type' => $documentType,
                'quotation_id' => $meta['quotation_id'] ?? $purchase->quotation_id,
            ])->save();
        }

        if ($purchase->items()->count() === 0) {
            foreach ($itemsToCreate as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
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

        return $purchase->fresh(['items']);
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
