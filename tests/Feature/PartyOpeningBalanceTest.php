<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_debit_opening_before_period_is_balance_brought_forward(): void
    {
        $plant = Plant::factory()->create();
        $supplier = Supplier::factory()->create([
            'opening_balance' => 1000,
            'opening_balance_type' => 'debit',
            'opening_balance_date' => '2026-03-31',
        ]);

        Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-OPEN-1',
            'grand_total' => 500,
            'ordered_at' => '2026-05-15',
        ]);

        SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'amount' => 200,
            'payment_date' => '2026-06-01',
        ]);

        $rows = app(ReportService::class)->supplierLedger($supplier->id, new ReportFilters(
            dateFrom: '2026-04-01',
            dateTo: '2027-03-31',
        ));

        $this->assertSame(['balance_bf', 'purchase', 'payment'], $rows->pluck('type')->all());
        $this->assertEquals(1000, $rows[0]->debit);
        $this->assertEquals(1000, $rows[0]->balance);
        $this->assertEquals(1500, $rows[1]->balance);
        $this->assertEquals(1300, $rows[2]->balance);

        $totals = app(ReportService::class)->supplierWisePurchaseTotals(new ReportFilters(
            dateFrom: '2026-04-01',
            dateTo: '2027-03-31',
        ));
        $row = $totals->firstWhere('supplier_id', $supplier->id);

        $this->assertEquals(1000, $row->brought_forward);
        $this->assertEquals(500, $row->total_amount);
        $this->assertEquals(200, $row->paid_amount);
        $this->assertEquals(1300, $row->balance);

        $admin = User::factory()->superAdmin()->create();
        $query = ['date_from' => '2026-04-01', 'date_to' => '2027-03-31'];

        $this->actingAs($admin)->get(route('ho.reports.party-ledger', ['supplier' => $supplier->id] + $query))
            ->assertOk()
            ->assertSee('Balance B/F')
            ->assertSee('ledgerParticularsModal')
            ->assertSee('1,300.00');

        $this->actingAs($admin)->get(route('ho.reports.party-purchase', $query))
            ->assertOk()
            ->assertSee('B/F')
            ->assertSee($supplier->name);

        $this->actingAs($admin)->get(route('ho.purchase-list.index', $query))
            ->assertOk()
            ->assertSee('B/F')
            ->assertSee($supplier->name);
    }

    public function test_credit_opening_inside_period_reduces_the_balance(): void
    {
        $plant = Plant::factory()->create();
        $supplier = Supplier::factory()->create([
            'name' => 'Advance Party',
            'opening_balance' => 400,
            'opening_balance_type' => 'credit',
            'opening_balance_date' => '2026-05-01',
        ]);

        Purchase::create([
            'plant_id' => $plant->id,
            'supplier_id' => $supplier->id,
            'reference_no' => 'PO-OPEN-2',
            'grand_total' => 100,
            'ordered_at' => '2026-06-01',
        ]);

        $rows = app(ReportService::class)->supplierLedger($supplier->id, new ReportFilters(
            dateFrom: '2026-04-01',
            dateTo: '2027-03-31',
        ));

        $this->assertSame(['opening', 'purchase'], $rows->pluck('type')->all());
        $this->assertEquals(400, $rows[0]->credit);
        $this->assertEquals(-400, $rows[0]->balance);
        $this->assertEquals(-300, $rows[1]->balance);

        $totals = app(ReportService::class)->supplierWisePurchaseTotals(new ReportFilters(
            dateFrom: '2026-04-01',
            dateTo: '2027-03-31',
        ));
        $row = $totals->firstWhere('supplier_id', $supplier->id);

        $this->assertEquals(0, $row->brought_forward);
        $this->assertEquals(-300, $row->balance);
    }

    public function test_party_with_only_an_opening_balance_appears_on_the_summary(): void
    {
        $supplier = Supplier::factory()->create([
            'opening_balance' => 250,
            'opening_balance_type' => 'debit',
            'opening_balance_date' => '2026-03-31',
        ]);

        $totals = app(ReportService::class)->supplierWisePurchaseTotals(new ReportFilters(
            dateFrom: '2026-04-01',
            dateTo: '2027-03-31',
        ));
        $row = $totals->firstWhere('supplier_id', $supplier->id);

        $this->assertNotNull($row);
        $this->assertSame(0, $row->order_count);
        $this->assertEquals(250, $row->brought_forward);
        $this->assertEquals(250, $row->balance);
    }

    public function test_supplier_forms_show_opening_balance_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $purchaser = User::factory()->create([
            'plant_id' => null,
            'role_id' => Role::where('slug', Role::SLUG_PURCHASER)->value('id'),
        ]);
        $supplier = Supplier::factory()->create([
            'opening_balance' => 900,
            'opening_balance_type' => 'debit',
            'opening_balance_date' => '2026-03-31',
        ]);

        $this->actingAs($admin)->get(route('admin.suppliers.create'))
            ->assertOk()
            ->assertSee('Opening Balance')
            ->assertSee('As-on date');

        $this->actingAs($admin)->get(route('admin.suppliers.edit', $supplier))
            ->assertOk()
            ->assertSee('900.00')
            ->assertSee('31 Mar 2026')
            ->assertDontSee('name="opening_balance"', false);

        $this->actingAs($purchaser)->get(route('plant.suppliers.edit', $supplier))
            ->assertOk()
            ->assertSee('900.00')
            ->assertDontSee('name="opening_balance"', false);
    }

    public function test_admin_can_set_opening_balance_once_and_a_later_edit_does_not_change_it(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $supplier = Supplier::factory()->create(['name' => 'Old Party']);

        $this->actingAs($admin)->put(route('admin.suppliers.update', $supplier), [
            'name' => 'Old Party',
            'is_active' => 1,
            'opening_balance' => 750,
            'opening_balance_type' => 'debit',
            'opening_balance_date' => '2026-03-31',
        ])->assertRedirect(route('admin.suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'opening_balance' => 750,
            'opening_balance_type' => 'debit',
        ]);

        $this->actingAs($admin)->put(route('admin.suppliers.update', $supplier), [
            'name' => 'Old Party',
            'is_active' => 1,
            'opening_balance' => 50,
            'opening_balance_type' => 'credit',
            'opening_balance_date' => '2026-04-01',
        ])->assertRedirect(route('admin.suppliers.index'));

        $supplier->refresh();
        $this->assertEquals(750, (float) $supplier->opening_balance);
        $this->assertSame('debit', $supplier->opening_balance_type);
        $this->assertSame('2026-03-31', $supplier->opening_balance_date->toDateString());
    }

    public function test_opening_amount_requires_a_type_and_date(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.suppliers.store'), [
            'name' => 'Incomplete Opening',
            'is_active' => 1,
            'opening_balance' => 100,
        ])->assertSessionHasErrors(['opening_balance_type', 'opening_balance_date']);

        $this->assertDatabaseMissing('suppliers', ['name' => 'Incomplete Opening']);
    }

    public function test_plant_update_cannot_change_opening_balance(): void
    {
        $purchaser = User::factory()->create([
            'plant_id' => null,
            'role_id' => Role::where('slug', Role::SLUG_PURCHASER)->value('id'),
        ]);
        $supplier = Supplier::factory()->create([
            'name' => 'Plant Party',
            'opening_balance' => 900,
            'opening_balance_type' => 'debit',
            'opening_balance_date' => '2026-03-31',
        ]);

        $this->actingAs($purchaser)->put(route('plant.suppliers.update', $supplier), [
            'name' => 'Plant Party Renamed',
            'is_active' => 1,
            'opening_balance' => 10,
            'opening_balance_type' => 'credit',
            'opening_balance_date' => '2026-04-02',
        ])->assertRedirect(route('plant.suppliers.index'));

        $supplier->refresh();
        $this->assertSame('Plant Party Renamed', $supplier->name);
        $this->assertEquals(900, (float) $supplier->opening_balance);
        $this->assertSame('debit', $supplier->opening_balance_type);
        $this->assertSame('2026-03-31', $supplier->opening_balance_date->toDateString());
    }
}
