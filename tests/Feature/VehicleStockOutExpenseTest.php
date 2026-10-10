<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Enums\StockReferenceType;
use App\Models\Category;
use App\Models\Plant;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleStockOutExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_expense_lists_each_product_amount_and_exports_them_separately(): void
    {
        $plant = Plant::factory()->create();
        $user = User::factory()->superAdmin()->create();
        $category = Category::factory()->create(['name' => 'Filters']);
        $filter = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Oil Filter',
        ]);
        $oil = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Engine Oil',
        ]);
        $vehicle = Vehicle::create([
            'name' => 'HYVA Test 01',
            'registration_no' => 'RJ01AB1234',
            'is_active' => true,
        ]);

        StockMovement::create([
            'plant_id' => $plant->id,
            'product_id' => $filter->id,
            'vehicle_id' => $vehicle->id,
            'type' => StockMovementType::Out,
            'quantity' => 2,
            'unit_price' => 100,
            'reference_type' => StockReferenceType::StockOut,
            'performed_by' => $user->id,
            'moved_at' => '2026-10-01',
        ]);
        StockMovement::create([
            'plant_id' => $plant->id,
            'product_id' => $oil->id,
            'vehicle_id' => $vehicle->id,
            'type' => StockMovementType::Out,
            'quantity' => 1,
            'unit_price' => 40,
            'reference_type' => StockReferenceType::StockOut,
            'performed_by' => $user->id,
            'moved_at' => '2026-10-02',
        ]);

        $filters = new ReportFilters(dateFrom: '2026-04-01', dateTo: '2027-03-31');
        $rows = app(ReportService::class)->vehicleStockOutExpense($filters);
        $row = $rows->firstWhere('vehicle_id', $vehicle->id);

        $this->assertNotNull($row);
        $this->assertEquals(2, $row->product_count);
        $this->assertEquals(240, $row->total_amount);

        $products = app(ReportService::class)->vehicleStockOutProducts(new ReportFilters(
            dateFrom: '2026-04-01',
            dateTo: '2027-03-31',
            vehicleId: $vehicle->id,
        ));
        $this->assertSame(['Oil Filter', 'Engine Oil'], $products->pluck('product_name')->all());
        $this->assertEquals(200, $products[0]->total_amount);
        $this->assertEquals(40, $products[1]->total_amount);

        $query = ['date_from' => '2026-04-01', 'date_to' => '2027-03-31'];
        $detailUrl = route('ho.reports.usage-by-category.vehicle', ['vehicle' => $vehicle->id] + $query);

        $this->actingAs($user)
            ->get(route('ho.reports.usage-by-category', $query))
            ->assertOk()
            ->assertSee('Export vehicles')
            ->assertSee('Export products')
            ->assertSee('HYVA Test 01')
            ->assertSee('usage-by-category/vehicles/'.$vehicle->id)
            ->assertDontSee('vehicleProductsModal');

        $this->actingAs($user)
            ->get($detailUrl)
            ->assertOk()
            ->assertSee('HYVA Test 01')
            ->assertSee('Export Excel')
            ->assertSee('Oil Filter')
            ->assertSee('Engine Oil')
            ->assertSee('200.00')
            ->assertSee('40.00');

        $detailExport = $this->actingAs($user)->get($detailUrl.'&export=excel');
        $detailExport->assertOk();
        $detailExport->assertDownload('vehicle-stock-out-products.csv');
        $detailCsv = $detailExport->streamedContent();
        $this->assertStringContainsString('Oil Filter', $detailCsv);
        $this->assertStringContainsString('200', $detailCsv);

        $vehicleExport = $this->actingAs($user)
            ->get(route('ho.reports.usage-by-category', $query + ['export' => 'vehicle-expense']));
        $vehicleExport->assertOk();
        $vehicleExport->assertDownload('vehicle-stock-out-expense.csv');
        $this->assertStringContainsString('HYVA Test 01 (RJ01AB1234)', $vehicleExport->streamedContent());

        $productExport = $this->actingAs($user)
            ->get(route('ho.reports.usage-by-category', $query + ['export' => 'vehicle-products']));
        $productExport->assertOk();
        $productExport->assertDownload('vehicle-stock-out-products.csv');
        $productCsv = $productExport->streamedContent();
        $this->assertStringContainsString('Oil Filter', $productCsv);
        $this->assertStringContainsString('Engine Oil', $productCsv);
        $this->assertStringContainsString('200', $productCsv);
        $this->assertStringContainsString('40', $productCsv);
    }
}
