<?php

namespace Modules\Inventory\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Core\Documents\DocumentStatus;
use Modules\Inventory\Documents\Actions\AdjustmentActions;
use Modules\Inventory\Documents\Actions\TransferActions;
use Modules\Inventory\Livewire\Adjustments\Form as AdjustmentForm;
use Modules\Inventory\Livewire\Adjustments\Show as AdjustmentShow;
use Modules\Inventory\Models\StockAdjustment;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class StockDocumentsTest extends TestCase
{
    use InstallsErp, MovesStock, PostsEntries, RefreshDatabase;

    private Warehouse $main;

    private Product $product;

    private Unit $carton;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->main = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $this->carton = Unit::where('name->en', 'Carton')->firstOrFail();
        $this->product = Product::factory()->withUnit($this->carton, '12')->create();
    }

    private function opening(string $cartons = '10', string $cartonCost = '600'): StockAdjustment
    {
        $adjustment = app(AdjustmentActions::class)->save($this->admin, [
            'date' => now()->toDateString(), 'warehouse_id' => $this->main->id, 'kind' => 'opening',
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->carton->id, 'quantity' => $cartons, 'unit_cost' => $cartonCost]],
        ]);

        return app(AdjustmentActions::class)->post($this->admin, $adjustment);
    }

    public function test_the_installer_created_a_main_warehouse_for_the_branch(): void
    {
        $this->assertSame('WH-MAIN', $this->main->code);
    }

    public function test_opening_stock_converts_units_and_posts_against_opening_balances(): void
    {
        $adjustment = $this->opening();

        $this->assertSame(DocumentStatus::Posted, $adjustment->status);
        $this->assertStringStartsWith('ADJ-', $adjustment->number);
        $this->assertSame('120.0000', $this->onHand($this->product->id, $this->main->id));
        $this->assertSame('50.0000', (string) $this->cost($this->product->id)->average_cost);
        $this->assertSame('6000.0000', (string) $adjustment->lines()->first()->total_cost);
        $this->assertSame('-6000.0000', (string) app(Ledger::class)->accountBalance($this->account('3104')));
        $this->assertStockConsistent();
    }

    public function test_opening_lines_need_a_cost(): void
    {
        $this->expectException(ValidationException::class);

        app(AdjustmentActions::class)->save($this->admin, [
            'date' => now()->toDateString(), 'warehouse_id' => $this->main->id, 'kind' => 'opening',
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->product->base_unit_id, 'quantity' => '5', 'unit_cost' => null]],
        ]);
    }

    public function test_a_count_shortage_goes_to_stock_losses_and_cancelling_restores_it(): void
    {
        $this->opening();

        $shortage = app(AdjustmentActions::class)->post($this->admin, app(AdjustmentActions::class)->save($this->admin, [
            'date' => now()->toDateString(), 'warehouse_id' => $this->main->id, 'kind' => 'adjustment', 'description' => 'Count',
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->product->base_unit_id, 'quantity' => '-3']],
        ]));

        $this->assertSame('117.0000', $this->onHand($this->product->id, $this->main->id));
        $this->assertSame('150.0000', (string) app(Ledger::class)->accountBalance($this->account('5401')));

        app(AdjustmentActions::class)->cancel($this->admin, $shortage, 'Found the cartons');

        $this->assertSame('120.0000', $this->onHand($this->product->id, $this->main->id));
        $this->assertTrue(app(Ledger::class)->accountBalance($this->account('5401'))->isZero());
        $this->assertStockConsistent();
        $this->assertLedgerBalanced();
    }

    public function test_a_transfer_moves_cartons_to_another_warehouse(): void
    {
        $this->opening();
        $shop = Warehouse::factory()->create(['branch_id' => $this->branch->id]);

        $transfer = app(TransferActions::class)->post($this->admin, app(TransferActions::class)->save($this->admin, [
            'date' => now()->toDateString(), 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $shop->id,
            'lines' => [['product_id' => $this->product->id, 'unit_id' => $this->carton->id, 'quantity' => '2']],
        ]));

        $this->assertStringStartsWith('TRF-', $transfer->number);
        $this->assertSame('96.0000', $this->onHand($this->product->id, $this->main->id));
        $this->assertSame('24.0000', $this->onHand($this->product->id, $shop->id));
        $this->assertSame('1200.0000', (string) $transfer->lines()->first()->total_cost);

        app(TransferActions::class)->cancel($this->admin, $transfer, 'Wrong shop');
        $this->assertSame('0.0000', $this->onHand($this->product->id, $shop->id));
        $this->assertStockConsistent();
    }

    public function test_the_adjustment_form_saves_a_draft_and_the_page_posts_it(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AdjustmentForm::class)
            ->set('form.kind', 'opening')
            ->set('form.lines.0.product_id', $this->product->id)
            ->assertSet('form.lines.0.unit_id', $this->product->base_unit_id)
            ->set('form.lines.0.quantity', '5')
            ->set('form.lines.0.unit_cost', '40')
            ->call('save')
            ->assertHasNoErrors();

        $adjustment = StockAdjustment::sole();
        Livewire::test(AdjustmentShow::class, ['id' => $adjustment->id])->call('post')->assertHasNoErrors();

        $this->assertSame('5.0000', $this->onHand($this->product->id, $this->main->id));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'stock' => ['/inventory/stock'],
            'moves' => ['/inventory/moves'],
            'warehouses' => ['/inventory/warehouses'],
            'adjustments' => ['/inventory/adjustments'],
            'adjustment form' => ['/inventory/adjustments/create'],
            'transfers' => ['/inventory/transfers'],
            'transfer form' => ['/inventory/transfers/create'],
        ];
    }

    #[DataProvider('pages')]
    public function test_pages_render_for_an_admin_and_are_refused_without_permission(string $url): void
    {
        $this->opening();

        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }
}
