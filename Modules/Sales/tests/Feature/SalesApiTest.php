<?php

namespace Modules\Sales\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Products\Models\Product;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use InstallsErp, MovesStock, RefreshDatabase;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $this->product = Product::factory()->create(['sale_price' => '25']);
        $this->receive([new StockLineData($this->product->id, $this->warehouse->id, '10', '10')], null, StockMoveType::Opening, 'opening_balance_equity');
    }

    private function invoicePayload(Partner $customer, string $quantity = '2'): array
    {
        return [
            'date' => now()->toDateString(),
            'partner_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => Currency::where('code', 'EGP')->value('id'),
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->product->base_unit_id, 'quantity' => $quantity]],
        ];
    }

    public function test_an_invoice_is_drafted_posted_once_and_returned_through_the_api(): void
    {
        Sanctum::actingAs($this->admin);
        $customer = Partner::factory()->create();

        $id = $this->postJson('/api/v1/sales/invoices', $this->invoicePayload($customer))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.lines.0.unit_price', '25.0000')
            ->assertJsonPath('data.total', '50.0000')
            ->json('data.id');

        $this->putJson("/api/v1/sales/invoices/{$id}", $this->invoicePayload($customer, '3'))->assertOk()->assertJsonPath('data.total', '75.0000');

        $invoice = $this->postJson("/api/v1/sales/invoices/{$id}/post")->assertOk()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.lines.0.returnable_quantity', '3.0000')
            ->json('data');
        $this->postJson("/api/v1/sales/invoices/{$id}/post")->assertConflict();

        $returnId = $this->postJson("/api/v1/sales/invoices/{$id}/returns", [
            'date' => now()->toDateString(),
            'lines' => [['sales_invoice_line_id' => $invoice['lines'][0]['id'], 'quantity' => '1']],
        ])->assertCreated()->assertJsonPath('data.total', '25.0000')->json('data.id');
        $this->postJson("/api/v1/sales/returns/{$returnId}/post")->assertOk()->assertJsonPath('data.status', 'posted');

        $this->getJson('/api/v1/sales/invoices?status=posted')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/sales/returns?sales_invoice_id={$id}")->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('8.0000', $this->onHand($this->product->id, $this->warehouse->id));
    }

    public function test_posting_over_the_credit_limit_asks_for_confirmation(): void
    {
        Sanctum::actingAs($this->admin);
        $customer = Partner::factory()->create(['credit_limit' => '40']);
        $id = $this->postJson('/api/v1/sales/invoices', $this->invoicePayload($customer))->json('data.id');

        $this->postJson("/api/v1/sales/invoices/{$id}/post")->assertUnprocessable()->assertJsonValidationErrors('credit_limit_confirm');
        $this->postJson("/api/v1/sales/invoices/{$id}/post", ['confirm_over_limit' => true])->assertOk()->assertJsonPath('data.status', 'posted');
    }

    public function test_the_api_needs_a_token_and_permissions(): void
    {
        $this->getJson('/api/v1/sales/invoices')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/sales/invoices')->assertForbidden();
        $this->postJson('/api/v1/sales/invoices', $this->invoicePayload(Partner::factory()->create()))->assertForbidden();
    }
}
