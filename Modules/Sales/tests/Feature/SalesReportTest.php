<?php

namespace Modules\Sales\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Products\Models\Product;
use Modules\Sales\Actions\InvoiceActions;
use Modules\Sales\Actions\ReturnActions;
use Modules\Sales\Livewire\Reports\SalesAnalysis;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use InstallsErp, MovesStock, RefreshDatabase;

    public function test_net_sales_cost_and_margin_after_returns_by_product_and_customer(): void
    {
        $this->installErp();
        $this->actingAs($this->admin);
        $warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $product = Product::factory()->create(['sale_price' => '30', 'name' => ['ar' => 'صنف التقرير']]);
        $customer = Partner::factory()->create(['name' => 'Report Customer']);
        $this->receive([new StockLineData($product->id, $warehouse->id, '10', '10')], null, StockMoveType::Opening, 'opening_balance_equity');

        $invoices = app(InvoiceActions::class);
        $invoice = $invoices->post($this->admin, $invoices->save($this->admin, [
            'date' => now()->toDateString(), 'partner_id' => $customer->id, 'warehouse_id' => $warehouse->id,
            'currency_id' => Currency::where('code', 'EGP')->value('id'),
            'lines' => [['product_id' => $product->id, 'unit_id' => $product->base_unit_id, 'quantity' => '4']],
        ]));
        $returns = app(ReturnActions::class);
        $returns->post($this->admin, $returns->save($this->admin, $invoice, [
            'date' => now()->toDateString(), 'lines' => [['sales_invoice_line_id' => $invoice->lines()->value('id'), 'quantity' => '1']],
        ]));

        // 3 units net: 90 sales, 30 cost, 60 margin.
        Livewire::test(SalesAnalysis::class)
            ->assertSee('صنف التقرير')->assertSee('90.00')->assertSee('30.00')->assertSee('60.00')->assertSee('66.7')
            ->set('groupBy', 'customer')
            ->assertSee('Report Customer');

        // Cost and margin only for users who may see costs.
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('sales.reports.view');
        $viewer->branches()->attach($this->branch);
        Livewire::actingAs($viewer)->test(SalesAnalysis::class)->assertSee('90.00')->assertDontSee('66.7');

        $this->actingAs(User::factory()->create())->get(route('sales.reports.analysis'))->assertForbidden();
    }
}
