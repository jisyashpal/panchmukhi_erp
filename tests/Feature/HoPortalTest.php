<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\ProductRequestItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Tests\TestCase;

class HoPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function hoOperator(): User
    {
        return User::factory()->create([
            'plant_id' => null,
            'role_id' => Role::where('slug', Role::SLUG_HO_DATA_OPERATOR)->value('id'),
        ]);
    }

    public function test_ho_operator_can_access_dashboard(): void
    {
        $user = $this->hoOperator();

        $this->actingAs($user)->get(route('ho.dashboard'))
            ->assertOk()
            ->assertSee('Stock Lines');
    }

    public function test_ho_operator_cannot_edit_product_request(): void
    {
        $plant = Plant::factory()->create();
        $product = Product::factory()->create();
        $ho = $this->hoOperator();

        $request = ProductRequest::create([
            'plant_id' => $plant->id,
            'requested_by' => $ho->id,
            'requester_name' => $ho->name,
            'status' => RequestStatus::Submitted,
        ]);

        ProductRequestItem::create([
            'product_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $this->actingAs($ho)
            ->get('/ho/product-requests/'.$request->id.'/edit')
            ->assertNotFound();

        $this->actingAs($ho)
            ->put('/ho/product-requests/'.$request->id, [
                'items' => [
                    [
                        'id' => 1,
                        'quantity' => 12,
                        'unit_price' => 100,
                        'gst_rate' => 18,
                    ],
                ],
            ])
            ->assertMethodNotAllowed();
    }

    public function test_login_redirects_ho_operator_to_ho_dashboard(): void
    {
        $user = $this->hoOperator();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('ho.dashboard', absolute: false));
    }

    public function test_ho_create_purchase_route_is_not_available(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)
            ->get('/ho/purchases/create')
            ->assertNotFound();
    }

    public function test_ho_operator_can_view_purchases_list(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)
            ->get(route('ho.purchases.index'))
            ->assertOk()
            ->assertSee('Purchases')
            ->assertSee('Search PO / Supplier');

        $this->actingAs($ho)
            ->get(route('ho.purchases.index', ['search' => 'PO-TEST', 'status' => 'ordered']))
            ->assertOk();
    }

    public function test_ho_operator_can_upload_downloaded_purchase_sample(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['code' => 'PISKA', 'name' => 'Piska Plant', 'is_active' => true]);
        Product::factory()->create(['sku' => 'SKU-SAMPLE-1', 'name' => 'Sample Bolt', 'is_active' => true]);
        Product::factory()->create(['sku' => 'SKU-SAMPLE-2', 'name' => 'Sample Nut', 'is_active' => true]);
        Product::factory()->create(['sku' => 'SKU-SAMPLE-3', 'name' => 'Sample Washer', 'is_active' => true]);

        $sample = $this->actingAs($ho)
            ->get(route('ho.purchases.bulk-upload.sample', ['plant_id' => $plant->id]));

        $sample->assertOk();
        $csv = $sample->streamedContent();
        $this->assertStringContainsString('Product SKU / Name', $csv);
        $this->assertStringContainsString('SKU-SAMPLE-1', $csv);

        $response = $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'auto_create_suppliers' => '1',
            'csv_file' => File::fake()->createWithContent('bulk_purchases_sample.csv', $csv),
        ]);

        $response->assertRedirect(route('ho.purchases.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('purchases', 2);
        $this->assertDatabaseCount('purchase_items', 3);
    }

    public function test_ho_operator_bulk_upload_keeps_ho_menu(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)
            ->get(route('ho.purchases.bulk-upload'))
            ->assertOk()
            ->assertSee('HO Portal')
            ->assertSee('Bulk Upload Purchases')
            ->assertDontSee('Main Menu');
    }

    public function test_super_admin_bulk_upload_keeps_admin_menu(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('ho.purchases.index'))
            ->assertOk()
            ->assertSee('Main Menu')
            ->assertSee('Purchases & PO');

        $this->actingAs($admin)
            ->get(route('ho.purchases.bulk-upload'))
            ->assertOk()
            ->assertSee('Main Menu')
            ->assertSee('Purchases & PO')
            ->assertSee('Bulk Upload Purchases')
            ->assertDontSee('HO Portal');
    }

    public function test_purchase_bulk_upload_accepts_exact_product_name(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['code' => 'PISKA', 'is_active' => true]);
        $product = Product::factory()->create(['sku' => 'BOLT-12', 'name' => 'BOLT NUT 12X40', 'is_active' => true]);

        $csv = "Reference No,Plant Code / Name,Supplier Name,Document Type,Order Date,Supplier Invoice No,Supplier Invoice Date,Is Interstate (1/0),Product SKU / Name,Quantity,Unit Price,Discount %,GST Rate %,Notes\n"
            ."PO-1,PISKA,Test Supplier,purchase_order,".date('Y-m-d').",INV-1,".date('Y-m-d').",0,  bolt nut  12x40  ,2,100,0,18,\n";

        $response = $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'auto_create_suppliers' => '1',
            'csv_file' => File::fake()->createWithContent('by_name.csv', $csv),
        ]);

        $response->assertRedirect(route('ho.purchases.index'));
        $this->assertDatabaseHas('purchase_items', ['product_id' => $product->id]);
    }

    public function test_purchase_bulk_upload_rejects_non_matching_product_name(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['code' => 'PISKA', 'is_active' => true]);
        Product::factory()->create(['sku' => 'BOLT-12', 'name' => 'BOLT NUT 12X40', 'is_active' => true]);

        $csv = "Reference No,Plant Code / Name,Supplier Name,Document Type,Order Date,Supplier Invoice No,Supplier Invoice Date,Is Interstate (1/0),Product SKU / Name,Quantity,Unit Price,Discount %,GST Rate %,Notes\n"
            ."PO-1,PISKA,Test Supplier,purchase_order,".date('Y-m-d').",INV-1,".date('Y-m-d').",0,BOLT NUT,2,100,0,18,\n";

        $response = $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'auto_create_suppliers' => '1',
            'csv_file' => File::fake()->createWithContent('bad_name.csv', $csv),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('purchase_bulk_pending');
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_purchase_bulk_upload_does_not_save_until_all_rows_valid(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['code' => 'PISKA', 'is_active' => true]);
        $product = Product::factory()->create(['sku' => 'BOLT-12', 'name' => 'BOLT NUT 12X40', 'is_active' => true]);

        $csv = "Reference No,Plant Code / Name,Supplier Name,Document Type,Order Date,Supplier Invoice No,Supplier Invoice Date,Is Interstate (1/0),Product SKU / Name,Quantity,Unit Price,Discount %,GST Rate %,Notes\n"
            ."PO-1,PISKA,Test Supplier,purchase_order,".date('Y-m-d').",INV-1,".date('Y-m-d').",0,BOLT-12,2,100,0,18,\n"
            ."PO-1,PISKA,Test Supplier,purchase_order,".date('Y-m-d').",INV-1,".date('Y-m-d').",0,UNKNOWN ITEM,1,50,0,18,\n";

        $response = $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'auto_create_suppliers' => '1',
            'csv_file' => File::fake()->createWithContent('mixed.csv', $csv),
        ]);

        $response->assertRedirect(route('ho.purchases.bulk-upload'));
        $response->assertSessionHas('purchase_bulk_pending');
        $this->assertDatabaseCount('purchases', 0);

        $pending = session('purchase_bulk_pending');
        $this->assertTrue(collect($pending)->contains(fn ($row) => str_contains((string) ($row['product'] ?? ''), 'UNKNOWN ITEM')));

        $this->actingAs($ho)->get(route('ho.purchases.bulk-upload'))
            ->assertOk()
            ->assertSee('Fix product and supplier names before import')
            ->assertSee('Search item name or SKU')
            ->assertSee('UNKNOWN ITEM');

        $stillWrong = [
            'plant_id' => $plant->id,
            'auto_create_suppliers' => '1',
            'pending_rows' => $pending,
        ];
        $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), $stillWrong)
            ->assertRedirect(route('ho.purchases.bulk-upload'));
        $this->assertDatabaseCount('purchases', 0);

        $pending[1]['product'] = 'BOLT NUT 12X40';
        $pending[1]['product_id'] = $product->id;
        $fixed = [
            'plant_id' => $plant->id,
            'auto_create_suppliers' => '1',
            'pending_rows' => $pending,
        ];
        $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), $fixed)
            ->assertRedirect(route('ho.purchases.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('purchase_items', 2);
        $this->assertDatabaseHas('purchase_items', ['product_id' => $product->id]);
    }

    public function test_purchase_bulk_upload_rejects_unknown_supplier_and_does_not_duplicate(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['code' => 'PISKA', 'is_active' => true]);
        $product = Product::factory()->create(['sku' => 'BOLT-12', 'name' => 'BOLT NUT 12X40', 'is_active' => true]);
        $supplier = Supplier::factory()->create(['name' => 'Existing Vendor']);

        $csv = "Reference No,Plant Code / Name,Supplier Name,Document Type,Order Date,Supplier Invoice No,Supplier Invoice Date,Is Interstate (1/0),Product SKU / Name,Quantity,Unit Price,Discount %,GST Rate %,Notes\n"
            ."PO-1,PISKA,UNKNOWN SUPPLIER CO,purchase_order,".date('Y-m-d').",INV-1,".date('Y-m-d').",0,BOLT-12,2,100,0,18,\n";

        $response = $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'csv_file' => File::fake()->createWithContent('bad_supplier.csv', $csv),
        ]);

        $response->assertRedirect(route('ho.purchases.bulk-upload'));
        $response->assertSessionHas('purchase_bulk_pending');
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('suppliers', 1);

        $pending = session('purchase_bulk_pending');
        $this->assertTrue(collect($pending)->contains(fn ($row) => str_contains((string) ($row['supplier'] ?? ''), 'UNKNOWN SUPPLIER CO')));
        $this->assertTrue(collect($pending)->contains(fn ($row) => ! empty($row['supplier_error'])));

        $this->actingAs($ho)->get(route('ho.purchases.bulk-upload'))
            ->assertOk()
            ->assertSee('Fix product and supplier names before import')
            ->assertSee('Search existing supplier')
            ->assertSee('UNKNOWN SUPPLIER CO');

        $stillWrong = [
            'plant_id' => $plant->id,
            'pending_rows' => $pending,
        ];
        $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), $stillWrong)
            ->assertRedirect(route('ho.purchases.bulk-upload'));
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('suppliers', 1);

        $pending[0]['supplier'] = $supplier->name;
        $pending[0]['supplier_id'] = $supplier->id;
        $fixed = [
            'plant_id' => $plant->id,
            'pending_rows' => $pending,
        ];
        $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), $fixed)
            ->assertRedirect(route('ho.purchases.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('suppliers', 1);
        $this->assertDatabaseHas('purchases', ['supplier_id' => $supplier->id]);
        $this->assertDatabaseHas('purchase_items', ['product_id' => $product->id]);
    }

    public function test_ho_operator_can_comment_on_purchase(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $supplier = \App\Models\Supplier::factory()->create();
        $purchase = \App\Models\Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-HO-COMMENT',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 118,
            'ordered_at' => now(),
        ]);

        $this->actingAs($ho)
            ->post(route('ho.purchases.comments.store', $purchase), [
                'body' => 'Verify delivery timeline with supplier.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_comments', [
            'purchase_id' => $purchase->id,
            'user_id' => $ho->id,
            'body' => 'Verify delivery timeline with supplier.',
        ]);

        $this->actingAs($ho)
            ->get(route('ho.purchases.index'))
            ->assertOk()
            ->assertSee('Verify delivery timeline', false)
            ->assertSee('Comment', false);
    }

    public function test_ho_operator_can_access_create_product_request_page(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)->get(route('ho.product-requests.create'))
            ->assertOk()
            ->assertSee('New Product Request')
            ->assertSee('Plant')
            ->assertSee('Requested By')
            ->assertSee('Search Item Name / SKU')
            ->assertSee('Add New Item')
            ->assertSee('step="0.01"', false);
    }

    public function test_ho_operator_can_manage_products(): void
    {
        $ho = $this->hoOperator();
        $category = \App\Models\Category::factory()->create();
        $unit = \App\Models\Unit::factory()->create();

        $this->actingAs($ho)->get(route('ho.categories.index'))
            ->assertOk()
            ->assertSee('Categories');

        $this->actingAs($ho)->get(route('ho.brands.index'))
            ->assertOk()
            ->assertSee('Brands');

        $this->actingAs($ho)->get(route('ho.units.index'))
            ->assertOk()
            ->assertSee('Units');

        $this->actingAs($ho)->get(route('ho.products.index'))
            ->assertOk()
            ->assertSee('Products');

        $this->actingAs($ho)->get(route('ho.products.create'))
            ->assertOk()
            ->assertSee('Add Product');

        $response = $this->actingAs($ho)->post(route('ho.products.store'), [
            'products' => [
                [
                    'name' => 'Test Cement Bag',
                    'size' => '50KG',
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'reorder_level' => 15,
                    'is_active' => '1',
                ]
            ]
        ]);

        $response->assertRedirect(route('ho.products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Test Cement Bag',
            'size' => '50KG',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'reorder_level' => 15,
            'is_active' => true,
        ]);
    }

    public function test_ho_operator_can_manage_category(): void
    {
        $ho = $this->hoOperator();

        $response = $this->actingAs($ho)->post(route('ho.categories.store'), [
            'name' => 'HO Test Category',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('ho.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'HO Test Category',
            'slug' => 'ho-test-category',
        ]);
    }

    public function test_creating_category_from_product_create_returns_with_form_data(): void
    {
        $ho = $this->hoOperator();

        $response = $this->actingAs($ho)
            ->post(route('ho.categories.store'), [
                'name' => 'Returned HO Category',
                'is_active' => '1',
                'redirect' => 'products.create',
                'pc_name' => 'Hydraulic Pump',
                'pc_size' => '2 inch',
            ]);

        $category = Category::where('name', 'Returned HO Category')->first();
        $this->assertNotNull($category);

        $response->assertRedirect(route('ho.products.create', [
            'name' => 'Hydraulic Pump',
            'size' => '2 inch',
            'category_id' => $category->id,
        ]));

        $this->actingAs($ho)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Hydraulic Pump')
            ->assertSee('js-create-catalog', false);
    }

    public function test_ho_operator_can_store_product_request(): void
    {
        $plant = Plant::factory()->create();
        $product = Product::factory()->create();
        $ho = $this->hoOperator();

        $response = $this->actingAs($ho)
            ->post(route('ho.product-requests.store'), [
                'plant_id' => $plant->id,
                'requester_name' => 'HO Operator',
                'remarks' => 'Test remarks from HO',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'item_name' => $product->name,
                        'quantity' => 5,
                    ]
                ]
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('product_requests', [
            'plant_id' => $plant->id,
            'requested_by' => $ho->id,
            'remarks' => 'Test remarks from HO',
        ]);
        
        $this->assertDatabaseHas('product_request_items', [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    public function test_ho_operator_can_store_product_request_with_custom_item(): void
    {
        $plant = Plant::factory()->create();
        $ho = $this->hoOperator();
        $category = Category::factory()->create();
        $subCategory = \App\Models\SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Grease',
            'slug' => 'grease-ho-test',
            'is_active' => true,
        ]);

        $this->actingAs($ho)
            ->post(route('ho.product-requests.store'), [
                'plant_id' => $plant->id,
                'requester_name' => 'HO Operator',
                'items' => [
                    [
                        'item_name' => 'Custom Grease 500g',
                        'sub_category_id' => $subCategory->id,
                        'size' => '500g',
                        'unit' => 'tin',
                        'quantity' => 1.5,
                        'priority' => 'high',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_request_items', [
            'item_name' => 'Custom Grease 500g',
            'product_id' => null,
            'size' => '500g',
            'unit' => 'tin',
            'quantity' => 1.5,
            'priority' => 'high',
        ]);
    }

    public function test_admin_can_create_product_request_like_plant(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->get(route('ho.product-requests.create'))
            ->assertOk()
            ->assertSee('Search Item Name / SKU')
            ->assertSee('Create Request');

        $this->actingAs($admin)
            ->post(route('ho.product-requests.store'), [
                'plant_id' => $plant->id,
                'requester_name' => 'Admin',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'item_name' => $product->name,
                        'quantity' => 2.25,
                    ],
                ],
            ])
            ->assertRedirect();

        $item = ProductRequestItem::where('product_id', $product->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals(2.25, (float) $item->quantity);
    }

    public function test_ho_operator_can_bulk_upload_historical_stock_with_custom_date(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $category = Category::factory()->create(['name' => 'Hyva']);
        $subCategory = \App\Models\SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Valve',
            'slug' => 'valve',
            'is_active' => true,
        ]);
        $brand = Brand::factory()->create(['name' => 'TATA']);
        $unit = Unit::factory()->create(['name' => 'Nos', 'abbreviation' => 'NOS']);
        $product = Product::factory()->create([
            'name' => 'Historical Valve',
            'size' => 'HV-01',
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
        ]);

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT,Date\n"
            . "Hyva,Valve,Historical Valve,HV-01,TATA,12,NOS,2024-04-15\n";

        $response = $this->actingAs($ho)->post(route('ho.stocks.stock-in.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'update_mode' => 'add',
            'auto_create_products' => '1',
            'csv_file' => File::fake()->createWithContent('historical_stock.csv', $csv),
        ]);

        $response->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseHas('stock_movements', [
            'plant_id' => $plant->id,
            'product_id' => $product->id,
            'type' => StockMovementType::In->value,
            'quantity' => '12.00',
        ]);

        $movement = StockMovement::query()
            ->where('plant_id', $plant->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame('2024-04-15', $movement->moved_at->format('Y-m-d'));
    }

    public function test_stock_in_bulk_upload_page_does_not_show_supplier(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)
            ->get(route('ho.stocks.stock-in.bulk-upload'))
            ->assertOk()
            ->assertDontSee('name="supplier_id"', false)
            ->assertDontSee('Supplier Name');
    }

    public function test_stock_in_bulk_upload_shows_edit_table_when_product_name_is_wrong(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        Product::factory()->create(['name' => 'Dual Brake Valve', 'sku' => 'DBV-01']);

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT\n"
            ."Hyva,Valve,UNKNOWN VALVE,601130,TATA,2,NOS\n"
            ."Hyva,Valve,Dual Brake Valve,DBV-01,TATA,1,NOS\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-in.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'update_mode' => 'add',
            'csv_file' => File::fake()->createWithContent('stock_in_bad_name.csv', $csv),
        ])
            ->assertRedirect(route('ho.stocks.stock-in.bulk-upload'))
            ->assertSessionHas('stock_in_bulk_pending');

        $this->assertDatabaseCount('stock_movements', 0);

        $this->actingAs($ho)->get(route('ho.stocks.stock-in.bulk-upload'))
            ->assertOk()
            ->assertSee('Fix product names before import')
            ->assertSee('UNKNOWN VALVE')
            ->assertSee('Search item name or SKU');
    }

    public function test_stock_in_bulk_upload_imports_after_wrong_product_is_selected(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'Dual Brake Valve', 'sku' => 'DBV-01']);

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT\n"
            ."Hyva,Valve,UNKNOWN VALVE,601130,TATA,2,NOS\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-in.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'update_mode' => 'add',
            'csv_file' => File::fake()->createWithContent('stock_in_fix_name.csv', $csv),
        ])->assertSessionHas('stock_in_bulk_pending');

        $pending = session('stock_in_bulk_pending');
        $pending[0]['product'] = 'Dual Brake Valve';
        $pending[0]['product_id'] = $product->id;

        $this->actingAs($ho)->post(route('ho.stocks.stock-in.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'update_mode' => 'add',
            'pending_rows' => $pending,
        ])
            ->assertRedirect(route('ho.stocks.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_movements', [
            'plant_id' => $plant->id,
            'product_id' => $product->id,
            'quantity' => '2.00',
        ]);
    }

    public function test_stock_in_bulk_upload_does_not_count_new_products_as_reused(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['name' => 'PISKA-PLANT']);

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT\n"
            ."Hyva,Valve,New Valve A,NV-01,TATA,2,NOS\n"
            ."Hyva,Valve,New Valve B,NV-02,TATA,3,NOS\n";

        $response = $this->actingAs($ho)->post(route('ho.stocks.stock-in.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'update_mode' => 'add',
            'auto_create_products' => '1',
            'csv_file' => File::fake()->createWithContent('stock_in_new_only.csv', $csv),
        ]);

        $response->assertRedirect(route('ho.stocks.index'));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('2 new products created', session('success'));
        $this->assertStringNotContainsString('reused', session('success'));
        $this->assertSame(2, Product::query()->count());
    }

    public function test_stock_in_bulk_upload_creates_separate_products_for_same_name_different_parts(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT\n"
            ."Hyva,Bearing,Bearing,33111,TATA,2,NOS\n"
            ."Hyva,Bearing,Bearing,6206,TATA,3,NOS\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-in.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'update_mode' => 'add',
            'auto_create_products' => '1',
            'csv_file' => File::fake()->createWithContent('stock_in_same_name.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertSame(2, Product::query()->where('name', 'Bearing')->count());
        $this->assertStringContainsString('2 new products created', session('success'));
        $this->assertStringNotContainsString('reused', session('success'));
    }

    public function test_stock_in_bulk_upload_reports_duplicate_excel_row_number(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT\n"
            ."Hyva,Valve,Same Valve,SV-01,TATA,2,NOS\n"
            ."Hyva,Switch,Other Switch,OS-01,TATA,1,NOS\n"
            ."Hyva,Valve,Same Valve,SV-01,TATA,4,NOS\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-in.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'update_mode' => 'add',
            'auto_create_products' => '1',
            'csv_file' => File::fake()->createWithContent('stock_in_dup_row.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertStringContainsString('Excel row 4 (Same Valve, part SV-01) same as row 2', session('success'));
        $this->assertSame(1, Product::query()->where('name', 'Same Valve')->count());
    }

    public function test_stock_in_bulk_upload_does_not_create_or_attach_suppliers(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $category = Category::factory()->create(['name' => 'Hyva']);
        $subCategory = \App\Models\SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Valve',
            'slug' => 'valve',
            'is_active' => true,
        ]);
        $brand = Brand::factory()->create(['name' => 'TATA']);
        $unit = Unit::factory()->create(['name' => 'Nos', 'abbreviation' => 'NOS']);
        $product = Product::factory()->create([
            'name' => 'Dual Brake Valve',
            'size' => '601130',
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
        ]);
        Supplier::factory()->create(['name' => 'ASHA ENTER.']);

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT,Supplier Name\n"
            ."Hyva,Valve,Dual Brake Valve,601130,TATA,2,NOS, asha  enter \n"
            ."Hyva,Valve,New Valve A,NV-01,TATA,1,NOS,New Co.\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-in.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'update_mode' => 'add',
            'auto_create_products' => '1',
            'csv_file' => File::fake()->createWithContent('stock_in_no_suppliers.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseCount('suppliers', 1);
        $this->assertDatabaseHas('stock_movements', [
            'plant_id' => $plant->id,
            'product_id' => $product->id,
            'supplier_id' => null,
        ]);
        $this->assertSame(0, StockMovement::query()->whereNotNull('supplier_id')->count());
        $this->assertSame(1, Brand::query()->where('name', 'TATA')->count());
    }

    public function test_stock_in_bulk_upload_does_not_create_supplier_from_brand_company_name(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT\n"
            ."Hyva,Valve,New Valve A,NV-01,TATA,2,NOS\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-in.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'update_mode' => 'add',
            'auto_create_products' => '1',
            'csv_file' => File::fake()->createWithContent('stock_in_brand_only.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseCount('suppliers', 0);
        $this->assertSame(1, Brand::query()->where('name', 'TATA')->count());
        $this->assertSame(0, StockMovement::query()->whereNotNull('supplier_id')->count());
    }

    public function test_stock_in_bulk_upload_sends_purchase_sheet_to_purchase_import_for_product_edit(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['code' => 'PISKA', 'is_active' => true]);
        $today = date('Y-m-d');
        $csv = "Reference No,Plant Code / Name,Supplier Name,Document Type,Order Date,Supplier Invoice No,Supplier Invoice Date,Is Interstate (1/0),Product SKU / Name,Quantity,Unit Price,Discount %,GST Rate %,Notes\n"
            ."PO-PISKA-1001,PISKA,INDUSTRIAL & MINING SOLUTION,purchase_order,{$today},CHALLAN,{$today},0,K 4 BIT LOCK,2,23000,0,0,LD\n"
            ."PO-PISKA-1001,PISKA,INDUSTRIAL & MINING SOLUTION,purchase_order,{$today},CHALLAN,{$today},0,K 4 FRONT LOCKNUT,3,800,0,0,LD\n";

        $this->actingAs($ho)
            ->from(route('ho.stocks.stock-in.bulk-upload'))
            ->post(route('ho.stocks.stock-in.bulk-upload.store'), [
                'plant_id' => $plant->id,
                'update_mode' => 'add',
                'auto_create_products' => '1',
                'csv_file' => File::fake()->createWithContent('purchase_sheet.csv', $csv),
            ])
            ->assertRedirect(route('ho.purchases.bulk-upload'))
            ->assertSessionHas('purchase_bulk_pending');

        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->actingAs($ho)->get(route('ho.purchases.bulk-upload'))
            ->assertOk()
            ->assertSee('Fix product and supplier names before import')
            ->assertSee('K 4 BIT LOCK')
            ->assertSee('K 4 FRONT LOCKNUT');
    }

    public function test_purchase_bulk_upload_reuses_suppliers_from_repeated_excel_names(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['code' => 'PISKA', 'is_active' => true]);
        $productA = Product::factory()->create(['sku' => 'K4-BITON', 'name' => 'K 4 BITON', 'is_active' => true]);
        $productB = Product::factory()->create(['sku' => 'MNX-2010', 'name' => 'MNX 2010 3P', 'is_active' => true]);
        $productC = Product::factory()->create(['sku' => 'NUT-38', 'name' => '3/8TH NUT', 'is_active' => true]);
        $existing = Supplier::factory()->create(['name' => 'Industrial and Mining Solution']);
        $today = date('Y-m-d');

        $csv = "Reference No,Plant Code / Name,Supplier Name,Document Type,Order Date,Supplier Invoice No,Supplier Invoice Date,Is Interstate (1/0),Product SKU / Name,Quantity,Unit Price,Discount %,GST Rate %,Notes\n"
            ."PO-PISKA-1001,PISKA,INDUSTRIAL & MINING SOLUTION,purchase_order,{$today},CHALLAN,{$today},0,K4-BITON,2,23000,0,0,LD\n"
            ."PO-PISKA-1001,PISKA, industrial  &  mining solution ,purchase_order,{$today},CHALLAN,{$today},0,K4-BITON,3,800,0,0,LD\n"
            ."PO-PISKA-1002,PISKA,KG ELECTRO SOLUTIONS PVT LTD,purchase_order,{$today},INV. 5990,{$today},0,MNX-2010,1,18616,0,18,PMCMPL\n"
            ."PO-PISKA-1002,PISKA,KG ELECTRO SOLUTIONS PVT. LTD.,purchase_order,{$today},INV. 5990,{$today},0,MNX-2010,1,19030,0,18,PMCMPL\n"
            ."PO-PISKA-1005,PISKA,LOCA PURCHASE,purchase_order,{$today},CASH,{$today},0,NUT-38,2,150,0,0,CASH\n"
            ."PO-PISKA-1005,PISKA,Loca Purchase,purchase_order,{$today},CASH,{$today},0,NUT-38,2,150,0,0,CASH\n";

        $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'auto_create_suppliers' => '1',
            'csv_file' => File::fake()->createWithContent('piska_purchases.csv', $csv),
        ])->assertRedirect(route('ho.purchases.index'));

        $this->assertSame(3, Supplier::query()->count());
        $this->assertSame(1, Purchase::where('supplier_id', $existing->id)->count());
        $this->assertTrue(
            Supplier::query()->get()->contains(fn (Supplier $supplier) => \App\Support\MasterName::matchesSupplier($supplier->name, 'KG ELECTRO SOLUTIONS PVT LTD'))
        );
        $this->assertTrue(
            Supplier::query()->get()->contains(fn (Supplier $supplier) => \App\Support\MasterName::matchesSupplier($supplier->name, 'LOCA PURCHASE'))
        );
        $this->assertDatabaseHas('purchase_items', ['product_id' => $productA->id]);
        $this->assertDatabaseHas('purchase_items', ['product_id' => $productB->id]);
        $this->assertDatabaseHas('purchase_items', ['product_id' => $productC->id]);
    }

    public function test_purchase_bulk_upload_reuses_existing_supplier_instead_of_duplicating(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['code' => 'PISKA', 'is_active' => true]);
        $product = Product::factory()->create(['sku' => 'BOLT-12', 'name' => 'BOLT NUT 12X40', 'is_active' => true]);
        $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);

        $csv = "Reference No,Plant Code / Name,Supplier Name,Document Type,Order Date,Supplier Invoice No,Supplier Invoice Date,Is Interstate (1/0),Product SKU / Name,Quantity,Unit Price,Discount %,GST Rate %,Notes\n"
            .'PO-1,PISKA, test  supplier. ,purchase_order,'.date('Y-m-d').',INV-1,'.date('Y-m-d').",0,BOLT-12,2,100,0,18,\n"
            .'PO-2,PISKA,TEST SUPPLIER,purchase_order,'.date('Y-m-d').',INV-2,'.date('Y-m-d').",0,BOLT-12,1,100,0,18,\n";

        $this->actingAs($ho)->post(route('ho.purchases.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'auto_create_suppliers' => '1',
            'csv_file' => File::fake()->createWithContent('purchase_suppliers.csv', $csv),
        ])->assertRedirect(route('ho.purchases.index'));

        $this->assertDatabaseCount('suppliers', 1);
        $this->assertDatabaseHas('purchases', ['supplier_id' => $supplier->id]);
        $this->assertSame(2, \App\Models\Purchase::where('supplier_id', $supplier->id)->count());
    }

    public function test_old_stock_bulk_upload_url_redirects_to_stock_in_bulk(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)
            ->get(route('ho.stocks.bulk-upload'))
            ->assertRedirect(route('ho.stocks.stock-in.bulk-upload'));
    }

    public function test_ho_can_stock_out_multiple_items_on_one_vehicle(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $productA = Product::factory()->create(['name' => 'Bolt']);
        $productB = Product::factory()->create(['name' => 'Nut']);
        $vehicle = Vehicle::create([
            'name' => 'Hyva-01',
            'registration_no' => 'JH01AB1234',
            'is_active' => true,
        ]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $productA->id, 'quantity' => 10]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $productB->id, 'quantity' => 8]);

        $response = $this->actingAs($ho)->post(route('ho.stocks.stock-out.store'), [
            'plant_id' => $plant->id,
            'usage_type' => 'vehicle',
            'vehicle_id' => $vehicle->id,
            'photo_confirmed' => '1',
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 2],
                ['product_id' => $productB->id, 'quantity' => 3],
            ],
        ]);

        $response->assertRedirect(route('ho.stocks.index', ['plant_id' => $plant->id]));

        $this->assertEquals(2, StockMovement::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('stock_movements', [
            'plant_id' => $plant->id,
            'product_id' => $productA->id,
            'vehicle_id' => $vehicle->id,
            'type' => StockMovementType::Out->value,
            'quantity' => '2.00',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'plant_id' => $plant->id,
            'product_id' => $productB->id,
            'vehicle_id' => $vehicle->id,
            'type' => StockMovementType::Out->value,
            'quantity' => '3.00',
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'plant_id' => $plant->id,
            'product_id' => $productA->id,
            'quantity' => '8.00',
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'plant_id' => $plant->id,
            'product_id' => $productB->id,
            'quantity' => '5.00',
        ]);
    }

    public function test_stock_out_rejects_duplicate_products(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create();
        $vehicle = Vehicle::create(['name' => 'JCB-1', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 10]);

        $this->actingAs($ho)->from(route('ho.stocks.stock-out'))
            ->post(route('ho.stocks.stock-out.store'), [
                'plant_id' => $plant->id,
                'usage_type' => 'vehicle',
                'vehicle_id' => $vehicle->id,
                'photo_confirmed' => '1',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect(route('ho.stocks.stock-out'))
            ->assertSessionHasErrors('items');

        $this->assertEquals(0, StockMovement::query()->count());
    }

    public function test_stock_out_rolls_back_when_one_line_exceeds_stock(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $vehicle = Vehicle::create(['name' => 'JCB-2', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $productA->id, 'quantity' => 5]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $productB->id, 'quantity' => 1]);

        $this->actingAs($ho)->from(route('ho.stocks.stock-out'))
            ->post(route('ho.stocks.stock-out.store'), [
                'plant_id' => $plant->id,
                'usage_type' => 'vehicle',
                'vehicle_id' => $vehicle->id,
                'photo_confirmed' => '1',
                'items' => [
                    ['product_id' => $productA->id, 'quantity' => 2],
                    ['product_id' => $productB->id, 'quantity' => 4],
                ],
            ])
            ->assertRedirect(route('ho.stocks.stock-out'))
            ->assertSessionHasErrors();

        $this->assertEquals(0, StockMovement::query()->count());
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $productA->id,
            'quantity' => '5.00',
        ]);
    }

    public function test_stock_out_bulk_upload_issues_multiple_items_on_one_vehicle(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $category = Category::factory()->create(['name' => 'Hyva']);
        $subCategory = \App\Models\SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Valve',
            'slug' => 'valve',
            'is_active' => true,
        ]);
        $brand = Brand::factory()->create(['name' => 'TATA']);
        $unit = Unit::factory()->create(['name' => 'Nos', 'abbreviation' => 'NOS']);
        $productA = Product::factory()->create([
            'name' => 'Dual Brake Valve',
            'size' => '601130',
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
        ]);
        $productB = Product::factory()->create([
            'name' => 'Bearing 33111',
            'size' => '33111',
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
        ]);
        $vehicle = Vehicle::create(['name' => 'Dumper-9', 'registration_no' => 'JH09XY9999', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $productA->id, 'quantity' => 10]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $productB->id, 'quantity' => 6]);

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT,Vehicle / Machine\n"
            ."Hyva,Valve,Dual Brake Valve,601130,TATA,2,NOS,Dumper-9\n"
            ."Hyva,Valve,Bearing 33111,33111,TATA,3,NOS,Dumper-9\n";

        $response = $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'csv_file' => File::fake()->createWithContent('stock_out.csv', $csv),
        ]);

        $response->assertRedirect(route('ho.stocks.index'));
        $this->assertEquals(2, StockMovement::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $productA->id,
            'quantity' => '8.00',
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $productB->id,
            'quantity' => '3.00',
        ]);
    }

    public function test_stock_out_bulk_upload_rolls_back_when_quantity_exceeds_stock(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $category = Category::factory()->create(['name' => 'Hyva']);
        $subCategory = \App\Models\SubCategory::create([
            'category_id' => $category->id,
            'name' => 'Valve',
            'slug' => 'valve-out',
            'is_active' => true,
        ]);
        $brand = Brand::factory()->create(['name' => 'TATA']);
        $unit = Unit::factory()->create(['name' => 'Nos', 'abbreviation' => 'NOS']);
        $product = Product::factory()->create([
            'name' => 'Dual Brake Valve',
            'size' => '601130',
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
        ]);
        $vehicle = Vehicle::create(['name' => 'Dumper-9', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 1]);

        $csv = "Cat.,Item Type,Item Description,Part Number,COMPANY NAME,Qty,UNIT,FOR\n"
            ."Hyva,Valve,Dual Brake Valve,601130,TATA,5,NOS,Dumper-9\n";

        $this->actingAs($ho)->from(route('ho.stocks.stock-out.bulk-upload'))
            ->post(route('ho.stocks.stock-out.bulk-upload.store'), [
                'plant_id' => $plant->id,
                'csv_file' => File::fake()->createWithContent('stock_out.csv', $csv),
            ])
            ->assertRedirect(route('ho.stocks.stock-out.bulk-upload'))
            ->assertSessionHas('stock_out_bulk_pending');

        $this->get(route('ho.stocks.stock-out.bulk-upload'))
            ->assertOk()
            ->assertSee('Insufficient stock')
            ->assertSee('Fix products and machines before import');

        $this->assertEquals(0, StockMovement::query()->count());
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $product->id,
            'quantity' => '1.00',
        ]);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
    }

    public function test_stock_out_bulk_upload_register_csv_issues_per_row_vehicle_and_date(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $bearing = Product::factory()->create(['name' => 'BEARING (6206)']);
        $oil = Product::factory()->create(['name' => 'GEAR OIL']);
        $conveyor = Vehicle::create(['name' => 'CONVEYOR', 'is_active' => true]);
        $loader = Vehicle::create(['name' => 'LOADER', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $bearing->id, 'quantity' => 5]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $oil->id, 'quantity' => 20]);

        $csv = "MONTH,DATE,Vehicle / Machine,FOR,ITEM TYPE,NAME OF PARTS,QTY,UNIT,RATE,AMOUNT,REMARKS\n"
            ."Apr-26,1-04-2026,CONVEYOR,CONVEYOR,BEARING,BEARING (6206),1,NOS,675,675,\n"
            ."Apr-26,2-04-2026,LOADER,LOADER,OIL,GEAR OIL,10,LTR,202.96,2029.6,MOCWAY\n";

        $response = $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'csv_file' => File::fake()->createWithContent('register.csv', $csv),
        ]);

        $response->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $bearing->id,
            'vehicle_id' => $conveyor->id,
            'quantity' => '1.00',
            'type' => StockMovementType::Out->value,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $oil->id,
            'vehicle_id' => $loader->id,
            'quantity' => '10.00',
            'type' => StockMovementType::Out->value,
            'reason' => 'MOCWAY',
        ]);
        $this->assertSame('2026-04-01', StockMovement::query()->where('product_id', $bearing->id)->first()->moved_at->format('Y-m-d'));
        $this->assertSame('2026-04-02', StockMovement::query()->where('product_id', $oil->id)->first()->moved_at->format('Y-m-d'));
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $bearing->id,
            'quantity' => '4.00',
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $oil->id,
            'quantity' => '10.00',
        ]);
    }

    public function test_stock_out_bulk_upload_empty_vehicle_machine_skips_vehicle_check(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'BEARING (6206)']);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 5]);

        $csv = "MONTH,DATE,Vehicle / Machine,FOR,ITEM TYPE,NAME OF PARTS,QTY,UNIT,RATE,AMOUNT,REMARKS\n"
            ."Apr-26,1-04-2026,,UNKNOWN-MACHINE,BEARING,BEARING (6206),1,NOS,675,675,\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'csv_file' => File::fake()->createWithContent('register.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'vehicle_id' => null,
            'quantity' => '1.00',
            'type' => StockMovementType::Out->value,
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $product->id,
            'quantity' => '4.00',
        ]);
    }

    public function test_stock_out_bulk_upload_unknown_vehicle_machine_must_be_fixed_from_list(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'BEARING (6206)']);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 5]);

        $csv = "MONTH,DATE,Vehicle / Machine,FOR,ITEM TYPE,NAME OF PARTS,QTY,UNIT,RATE,AMOUNT,REMARKS\n"
            ."Apr-26,1-04-2026,UNKNOWN-MACHINE,PLANT USE,BEARING,BEARING (6206),1,NOS,675,675,\n";

        $this->actingAs($ho)->from(route('ho.stocks.stock-out.bulk-upload'))
            ->post(route('ho.stocks.stock-out.bulk-upload.store'), [
                'plant_id' => $plant->id,
                'csv_file' => File::fake()->createWithContent('register.csv', $csv),
            ])
            ->assertRedirect(route('ho.stocks.stock-out.bulk-upload'))
            ->assertSessionHas('stock_out_bulk_pending');

        $this->get(route('ho.stocks.stock-out.bulk-upload'))
            ->assertOk()
            ->assertSee('UNKNOWN-MACHINE')
            ->assertSee('— Select vehicle / machine —')
            ->assertSee('Fix products and machines before import');

        $this->assertEquals(0, StockMovement::query()->count());
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $product->id,
            'quantity' => '5.00',
        ]);
        $this->assertDatabaseMissing('vehicles', ['name' => 'UNKNOWN-MACHINE']);
    }

    public function test_stock_out_bulk_upload_auto_creates_missing_vehicle_and_reuses_it(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'BEARING (6206)']);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 5]);

        $csv = "MONTH,DATE,Vehicle / Machine,FOR,ITEM TYPE,NAME OF PARTS,QTY,UNIT,RATE,AMOUNT,REMARKS\n"
            ."Apr-26,1-04-2026,NEW-CRUSHER,PLANT,BEARING,BEARING (6206),1,NOS,675,675,\n"
            ."Apr-26,2-04-2026,NEW-CRUSHER,PLANT,BEARING,BEARING (6206),1,NOS,675,675,\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'auto_create_vehicles' => '1',
            'out_mode' => 'adjust',
            'csv_file' => File::fake()->createWithContent('register.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $vehicle = Vehicle::query()->where('name', 'NEW-CRUSHER')->first();
        $this->assertNotNull($vehicle);
        $this->assertTrue($vehicle->is_active);
        $this->assertSame(1, Vehicle::query()->where('name', 'NEW-CRUSHER')->count());
        $this->assertSame(2, StockMovement::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $product->id,
            'quantity' => '3.00',
        ]);
    }

    public function test_stock_out_bulk_upload_simple_upload_records_without_changing_stock(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'BEARING (6206)']);
        $vehicle = Vehicle::create(['name' => 'CONVEYOR', 'is_active' => true]);

        $csv = "MONTH,DATE,Vehicle / Machine,FOR,ITEM TYPE,NAME OF PARTS,QTY,UNIT,RATE,AMOUNT,REMARKS\n"
            ."Apr-26,1-04-2026,CONVEYOR,CONVEYOR,BEARING,BEARING (6206),4,NOS,675,2700,\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'out_mode' => 'record',
            'csv_file' => File::fake()->createWithContent('register.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'vehicle_id' => $vehicle->id,
            'quantity' => '4.00',
            'type' => StockMovementType::Out->value,
        ]);
        $this->assertDatabaseMissing('stock_balances', [
            'plant_id' => $plant->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_stock_out_bulk_upload_adjust_mode_rejects_when_stock_is_short(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'BEARING (6206)']);
        Vehicle::create(['name' => 'CONVEYOR', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 1]);

        $csv = "MONTH,DATE,Vehicle / Machine,FOR,ITEM TYPE,NAME OF PARTS,QTY,UNIT,RATE,AMOUNT,REMARKS\n"
            ."Apr-26,1-04-2026,CONVEYOR,CONVEYOR,BEARING,BEARING (6206),5,NOS,675,3375,\n";

        $this->actingAs($ho)->from(route('ho.stocks.stock-out.bulk-upload'))
            ->post(route('ho.stocks.stock-out.bulk-upload.store'), [
                'plant_id' => $plant->id,
                'out_mode' => 'adjust',
                'csv_file' => File::fake()->createWithContent('register.csv', $csv),
            ])
            ->assertRedirect(route('ho.stocks.stock-out.bulk-upload'))
            ->assertSessionHas('stock_out_bulk_pending');

        $this->get(route('ho.stocks.stock-out.bulk-upload'))
            ->assertOk()
            ->assertSee('Insufficient stock')
            ->assertSee('name="out_mode"', false)
            ->assertSee('value="adjust"', false);

        $this->assertEquals(0, StockMovement::query()->count());
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $product->id,
            'quantity' => '1.00',
        ]);
    }

    public function test_stock_out_bulk_upload_unknown_vehicle_machine_can_be_fixed_from_list(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'BEARING (6206)']);
        $vehicle = Vehicle::create(['name' => 'BC-3', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 5]);

        $csv = "MONTH,DATE,Vehicle / Machine,FOR,ITEM TYPE,NAME OF PARTS,QTY,UNIT,RATE,AMOUNT,REMARKS\n"
            ."Apr-26,5-04-2026,UNKNOWN-MACHINE,BC-3,ROLLER,BEARING (6206),2,NOS,720,1440,\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'csv_file' => File::fake()->createWithContent('register.csv', $csv),
        ])->assertRedirect(route('ho.stocks.stock-out.bulk-upload'))
            ->assertSessionHas('stock_out_bulk_pending');

        $this->assertEquals(0, StockMovement::query()->count());

        $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'pending_rows' => [[
                'row_num' => 2,
                'product' => 'BEARING (6206)',
                'quantity' => '2',
                'date' => '5-04-2026',
                'vehicle_for' => 'UNKNOWN-MACHINE',
                'vehicle_id' => $vehicle->id,
                'subcategory' => 'ROLLER',
            ]],
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'vehicle_id' => $vehicle->id,
            'quantity' => '2.00',
            'type' => StockMovementType::Out->value,
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $product->id,
            'quantity' => '3.00',
        ]);
    }

    public function test_stock_out_bulk_upload_page_shows_register_columns(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)->get(route('ho.stocks.stock-out.bulk-upload'))
            ->assertOk()
            ->assertSee('NAME OF PARTS')
            ->assertSee('Vehicle / Machine')
            ->assertSee('FOR')
            ->assertSee('REMARKS')
            ->assertSee('Adjust current stock')
            ->assertSee('Simple upload')
            ->assertSee('Auto-create missing Vehicle / Machine')
            ->assertDontSee('name="vehicle_id"', false);

        $sample = $this->actingAs($ho)->get(route('ho.stocks.stock-out.bulk-upload.sample'));
        $sample->assertOk();
        $sampleContent = method_exists($sample, 'streamedContent') ? $sample->streamedContent() : $sample->getContent();
        $this->assertStringContainsString('Vehicle / Machine', $sampleContent);
        $this->assertStringContainsString('FOR', $sampleContent);
        $this->assertStringContainsString('CONVEYOR', $sampleContent);
    }

    public function test_stock_out_bulk_upload_reads_vehicle_machine_column(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'BEARING (6206)']);
        $vehicle = Vehicle::create(['name' => 'CONVEYOR', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 5]);

        $csv = "DATE,Vehicle / Machine,NAME OF PARTS,QTY\n"
            ."1-04-2026,CONVEYOR,BEARING (6206),1\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'csv_file' => File::fake()->createWithContent('stock_out.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'vehicle_id' => $vehicle->id,
            'quantity' => '1.00',
            'type' => StockMovementType::Out->value,
        ]);
    }

    public function test_stock_out_bulk_upload_reads_vehicle_machine_and_for_on_same_row(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'GEAR OIL']);
        $vehicle = Vehicle::create(['name' => 'LOADER', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 20]);

        $csv = "DATE,Vehicle / Machine,FOR,NAME OF PARTS,QTY\n"
            ."2-04-2026,LOADER,SITE WORK,GEAR OIL,10\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'csv_file' => File::fake()->createWithContent('stock_out.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'vehicle_id' => $vehicle->id,
            'quantity' => '10.00',
            'type' => StockMovementType::Out->value,
        ]);
    }

    public function test_stock_out_bulk_upload_prefers_machine_column_when_for_is_separate(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $product = Product::factory()->create(['name' => 'GEAR OIL']);
        $vehicle = Vehicle::create(['name' => 'LOADER', 'is_active' => true]);
        StockBalance::create(['plant_id' => $plant->id, 'product_id' => $product->id, 'quantity' => 20]);

        $csv = "DATE,FOR,MACHINE,NAME OF PARTS,QTY\n"
            ."2-04-2026,SITE WORK,LOADER,GEAR OIL,10\n";

        $this->actingAs($ho)->post(route('ho.stocks.stock-out.bulk-upload.store'), [
            'plant_id' => $plant->id,
            'csv_file' => File::fake()->createWithContent('stock_out.csv', $csv),
        ])->assertRedirect(route('ho.stocks.index'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'vehicle_id' => $vehicle->id,
            'quantity' => '10.00',
            'type' => StockMovementType::Out->value,
        ]);
    }

    public function test_stock_out_create_page_shows_multi_item_controls(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)->get(route('ho.stocks.stock-out'))
            ->assertOk()
            ->assertSee('Add product')
            ->assertSee('Vehicle / Machine')
            ->assertSee('Bulk Upload')
            ->assertSee('Stock Out records')
            ->assertSee('Export Excel');
    }

    public function test_stock_in_and_stock_out_pages_list_movements_and_export(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create(['name' => 'Jaipur Plant']);
        $inProduct = Product::factory()->create(['name' => 'Hydraulic Pump', 'sku' => 'HP-IN-1']);
        $outProduct = Product::factory()->create(['name' => 'Gear Oil', 'sku' => 'GO-OUT-1']);
        $vehicle = Vehicle::create(['name' => 'Loader-9', 'is_active' => true]);

        app(\App\Services\StockService::class)->recordIn(
            $plant->id,
            $inProduct->id,
            12,
            $ho->id,
            \App\Enums\StockReferenceType::StockIn,
            null,
            ['notes' => 'Manual stock in for list']
        );

        StockBalance::create([
            'plant_id' => $plant->id,
            'product_id' => $outProduct->id,
            'quantity' => 20,
        ]);

        app(\App\Services\StockService::class)->recordOut(
            $plant->id,
            $outProduct->id,
            4,
            \App\Enums\StockReferenceType::StockOut,
            null,
            $ho->id,
            'Issued to machine',
            ['vehicle_id' => $vehicle->id, 'reason' => 'Maintenance']
        );

        $this->actingAs($ho)
            ->get(route('ho.stocks.stock-in'))
            ->assertOk()
            ->assertSee('Stock In records')
            ->assertSee('Export Excel')
            ->assertSee('Hydraulic Pump')
            ->assertSee('HP-IN-1')
            ->assertSee('Jaipur Plant');

        $inExport = $this->actingAs($ho)
            ->get(route('ho.stocks.stock-in', ['export' => 'excel']));
        $inExport->assertOk();
        $inCsv = $inExport->streamedContent();
        $this->assertStringContainsString('Hydraulic Pump', $inCsv);
        $this->assertStringContainsString('HP-IN-1', $inCsv);

        $this->actingAs($ho)
            ->get(route('ho.stocks.stock-out'))
            ->assertOk()
            ->assertSee('Stock Out records')
            ->assertSee('Export Excel')
            ->assertSee('Gear Oil')
            ->assertSee('Loader-9')
            ->assertSee('Maintenance');

        $outExport = $this->actingAs($ho)
            ->get(route('ho.stocks.stock-out', ['export' => 'excel']));
        $outExport->assertOk();
        $outCsv = $outExport->streamedContent();
        $this->assertStringContainsString('Gear Oil', $outCsv);
        $this->assertStringContainsString('Loader-9', $outCsv);
    }

    public function test_ho_operator_can_view_profile(): void
    {
        $ho = $this->hoOperator();

        $this->actingAs($ho)->get(route('ho.profile.edit'))
            ->assertOk()
            ->assertSee('Profile Information')
            ->assertSee('Update Password');
    }

    public function test_ho_operator_can_update_profile(): void
    {
        $ho = $this->hoOperator();

        $response = $this->actingAs($ho)
            ->patch(route('ho.profile.update'), [
                'name' => 'New HO Name',
                'email' => 'newho@example.com',
            ]);

        $response->assertRedirect(route('ho.profile.edit'));
        $ho->refresh();
        $this->assertEquals('New HO Name', $ho->name);
        $this->assertEquals('newho@example.com', $ho->email);
    }

    public function test_ho_operator_can_delete_profile(): void
    {
        $ho = $this->hoOperator();

        $response = $this->actingAs($ho)
            ->delete(route('ho.profile.destroy'), [
                'password' => 'password',
            ]);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($ho->fresh());
    }

    public function test_ho_operator_can_view_product_purchase_report_with_filters_and_export(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $otherPlant = Plant::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['name' => 'Hydraulic Filter', 'sku' => 'HF-100']);
        $otherProduct = Product::factory()->create(['name' => 'Engine Oil', 'sku' => 'EO-200']);

        $purchase = Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-PROD-RPT-1',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 5000,
            'grand_total' => 5000,
            'ordered_at' => now(),
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 500,
            'line_total' => 5000,
        ]);

        $otherPurchase = Purchase::create([
            'plant_id' => $otherPlant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-PROD-RPT-2',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 800,
            'grand_total' => 800,
            'ordered_at' => now(),
        ]);

        PurchaseItem::create([
            'purchase_id' => $otherPurchase->id,
            'product_id' => $otherProduct->id,
            'quantity' => 4,
            'unit_price' => 200,
            'line_total' => 800,
        ]);

        $this->actingAs($ho)
            ->get(route('ho.reports.index'))
            ->assertOk()
            ->assertSee('Product-wise Purchase Summary');

        $this->actingAs($ho)
            ->get(route('ho.reports.product-purchase'))
            ->assertOk()
            ->assertSee('Product-wise Purchase Amount')
            ->assertSee('Hydraulic Filter')
            ->assertSee('Engine Oil')
            ->assertSee('Export Excel')
            ->assertSee('HF-100');

        $this->actingAs($ho)
            ->get(route('ho.reports.product-purchase', [
                'search' => 'Hydraulic',
                'plant_id' => $plant->id,
                'category_id' => $product->category_id,
                'product_id' => $product->id,
            ]))
            ->assertOk()
            ->assertSee('Hydraulic Filter')
            ->assertDontSee('EO-200');

        $export = $this->actingAs($ho)
            ->get(route('ho.reports.product-purchase', ['export' => 'excel']));

        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Product', $csv);
        $this->assertStringContainsString('Hydraulic Filter', $csv);
        $this->assertStringContainsString('Engine Oil', $csv);

        $this->actingAs($ho)
            ->get(route('ho.reports.product-purchase.ledger', $product))
            ->assertOk()
            ->assertSee('Purchase Ledger: Hydraulic Filter')
            ->assertSee('PO-PROD-RPT-1')
            ->assertDontSee('PO-PROD-RPT-2');

        $ledgerExport = $this->actingAs($ho)
            ->get(route('ho.reports.product-purchase.ledger', ['product' => $product, 'export' => 'excel']));

        $ledgerExport->assertOk();
        $this->assertStringContainsString('PO-PROD-RPT-1', $ledgerExport->streamedContent());
    }

    public function test_ho_operator_can_view_date_wise_purchase_item_list_with_filters_and_export(): void
    {
        $ho = $this->hoOperator();
        $plant = Plant::factory()->create();
        $otherPlant = Plant::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Acme Traders']);
        $product = Product::factory()->create(['name' => 'Hydraulic Filter', 'sku' => 'HF-100']);
        $otherProduct = Product::factory()->create(['name' => 'Engine Oil', 'sku' => 'EO-200']);

        $older = Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-ITEM-RPT-OLD',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 5000,
            'grand_total' => 5000,
            'ordered_at' => now()->subDay(),
        ]);

        PurchaseItem::create([
            'purchase_id' => $older->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 500,
            'line_total' => 5000,
        ]);

        $newer = Purchase::create([
            'plant_id' => $otherPlant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-ITEM-RPT-NEW',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 800,
            'grand_total' => 800,
            'ordered_at' => now(),
        ]);

        PurchaseItem::create([
            'purchase_id' => $newer->id,
            'product_id' => $otherProduct->id,
            'quantity' => 4,
            'unit_price' => 200,
            'line_total' => 800,
        ]);

        $this->actingAs($ho)
            ->get(route('ho.reports.index'))
            ->assertOk()
            ->assertSee('Date-wise Purchase Items');

        $list = $this->actingAs($ho)
            ->get(route('ho.reports.purchase-items'))
            ->assertOk()
            ->assertSee('Date-wise Purchase Items')
            ->assertSee('Export Excel')
            ->assertSee('Export All Purchases')
            ->assertSee('PO-ITEM-RPT-NEW')
            ->assertSee('PO-ITEM-RPT-OLD')
            ->assertSee('Hydraulic Filter')
            ->assertSee('Engine Oil')
            ->assertSee('Acme Traders');

        $html = $list->getContent();
        $this->assertTrue(
            strpos($html, 'PO-ITEM-RPT-NEW') < strpos($html, 'PO-ITEM-RPT-OLD'),
            'Newer purchase items should appear first.'
        );

        $this->actingAs($ho)
            ->get(route('ho.reports.purchase-items', [
                'search' => 'Hydraulic',
                'plant_id' => $plant->id,
                'product_id' => $product->id,
            ]))
            ->assertOk()
            ->assertSee('PO-ITEM-RPT-OLD')
            ->assertSee('HF-100')
            ->assertDontSee('EO-200')
            ->assertDontSee('PO-ITEM-RPT-NEW');

        $historic = Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-ITEM-RPT-OLD-FY',
            'status' => \App\Enums\PurchaseStatus::Ordered,
            'subtotal' => 100,
            'grand_total' => 100,
            'ordered_at' => now()->subYears(2),
        ]);

        PurchaseItem::create([
            'purchase_id' => $historic->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $export = $this->actingAs($ho)
            ->get(route('ho.reports.purchase-items', ['export' => 'excel']));

        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('PO Ref', $csv);
        $this->assertStringContainsString('PO-ITEM-RPT-NEW', $csv);
        $this->assertStringContainsString('Hydraulic Filter', $csv);
        $this->assertStringContainsString('Engine Oil', $csv);
        $this->assertStringNotContainsString('PO-ITEM-RPT-OLD-FY', $csv);

        $allExport = $this->actingAs($ho)
            ->get(route('ho.reports.purchase-items', ['export' => 'all', 'plant_id' => $otherPlant->id]));

        $allExport->assertOk();
        $allCsv = $allExport->streamedContent();
        $this->assertStringContainsString('PO-ITEM-RPT-OLD-FY', $allCsv);
        $this->assertStringContainsString('PO-ITEM-RPT-NEW', $allCsv);
        $this->assertTrue(
            strpos($allCsv, 'PO-ITEM-RPT-OLD-FY') < strpos($allCsv, 'PO-ITEM-RPT-NEW'),
            'All-purchase export should list items from first purchase to last.'
        );
    }
}
