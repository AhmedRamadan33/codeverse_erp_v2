<?php

namespace Modules\Pos\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Tax;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Pos\Actions\RegisterActions;
use Modules\Pos\Models\Register;
use Modules\Products\Models\Product;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class PosApiTest extends TestCase
{
    use InstallsErp, MovesStock, RefreshDatabase;

    private Product $product;

    private Register $register;

    private int $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $this->cash = PaymentMethod::where('type', 'cash')->value('id');
        $this->product = Product::factory()->create(['sale_price' => '10', 'sale_tax_id' => Tax::firstWhere('code', 'VAT14')->id]);
        $this->product->units()->first()->barcodes()->create(['product_id' => $this->product->id, 'barcode' => '622333']);
        $this->receive([new StockLineData($this->product->id, $warehouse->id, '10', '6')], null, StockMoveType::Opening, 'opening_balance_equity');
        $this->register = app(RegisterActions::class)->save($this->admin, [
            'code' => 'R1', 'name_ar' => 'كاشير 1', 'warehouse_id' => $warehouse->id, 'cash_payment_method_id' => $this->cash,
        ]);
    }

    public function test_a_mobile_cashier_sells_returns_and_closes_the_shift(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/pos/setup')->assertOk()->assertJsonPath('data.registers.0.busy', false);
        $this->getJson('/api/v1/pos/shifts/current')->assertOk()->assertJsonPath('data', null);
        $shiftId = $this->postJson('/api/v1/pos/shifts', ['register_id' => $this->register->id, 'opening_float' => '50'])
            ->assertCreated()->json('data.id');

        $this->getJson('/api/v1/pos/products?barcode=622333')->assertOk()
            ->assertJsonPath('data.0.id', $this->product->id)
            ->assertJsonPath('data.0.units.0.price', '10.0000')
            ->assertJsonPath('data.0.tax_rate', '14.0000');

        $receipt = $this->postJson('/api/v1/pos/receipts', [
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->product->base_unit_id, 'quantity' => '3']],
            'payments' => [['payment_method_id' => $this->cash, 'amount' => '40']],
        ])->assertCreated()
            ->assertJsonPath('data.total', '34.2000')
            ->assertJsonPath('data.change', '5.8000')
            ->assertJsonPath('data.lines.0.returnable_quantity', '3.0000')
            ->json('data');

        $this->postJson("/api/v1/pos/receipts/{$receipt['id']}/returns", [
            'lines' => [['original_line_id' => $receipt['lines'][0]['id'], 'quantity' => '1']],
        ])->assertCreated()->assertJsonPath('data.kind', 'return')->assertJsonPath('data.total', '11.4000');

        $this->getJson("/api/v1/pos/shifts/{$shiftId}")->assertOk()
            ->assertJsonPath('data.expected_cash', '72.8000');

        $this->postJson("/api/v1/pos/shifts/{$shiftId}/close", ['counted_cash' => '72.80'])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.cash_difference', '0.0000');
    }

    public function test_the_api_needs_a_token_and_the_permissions(): void
    {
        $this->getJson('/api/v1/pos/setup')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/pos/setup')->assertForbidden();
        $this->postJson('/api/v1/pos/shifts', ['register_id' => $this->register->id, 'opening_float' => '0'])->assertForbidden();
    }
}
