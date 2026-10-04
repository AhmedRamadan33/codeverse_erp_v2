<?php

namespace Modules\Pos\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Tax;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Pos\Enums\ReceiptKind;
use Modules\Pos\Enums\ShiftStatus;
use Modules\Pos\Livewire\Registers\Index as Registers;
use Modules\Pos\Livewire\Returns\Form as ReturnForm;
use Modules\Pos\Livewire\Shifts\Show as ShiftShow;
use Modules\Pos\Livewire\Terminal;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Models\Register;
use Modules\Pos\Models\Shift;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class PosScreensTest extends TestCase
{
    use InstallsErp, MovesStock, RefreshDatabase;

    private Product $product;

    private Unit $carton;

    private Warehouse $warehouse;

    private int $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->actingAs($this->admin);
        $this->warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $this->cash = PaymentMethod::where('type', 'cash')->value('id');
        $this->carton = Unit::where('name->en', 'Carton')->firstOrFail();
        $this->product = Product::factory()->withUnit($this->carton, '6')->create([
            'sale_price' => '10', 'sale_tax_id' => Tax::firstWhere('code', 'VAT14')->id,
        ]);
        $this->product->units()->where('unit_id', $this->carton->id)->first()->barcodes()->create(['product_id' => $this->product->id, 'barcode' => '622111']);
        $this->receive([new StockLineData($this->product->id, $this->warehouse->id, '60', '6')], null, StockMoveType::Opening, 'opening_balance_equity');
    }

    private function register(): Register
    {
        Livewire::test(Registers::class)
            ->call('create')
            ->set('form.code', 'R1')
            ->set('form.name_ar', 'كاشير 1')
            ->call('save')
            ->assertHasNoErrors();

        return Register::sole();
    }

    public function test_a_cashier_opens_a_shift_scans_pays_and_gets_change(): void
    {
        $register = $this->register();

        $terminal = Livewire::test(Terminal::class)
            ->assertSee(__('pos::shifts.open'))
            ->set('opening.register_id', $register->id)
            ->set('opening.opening_float', '200')
            ->call('openShift')
            ->assertHasNoErrors();

        $terminal->set('search', '622111')->call('scan')
            ->set('search', '622111')->call('scan')
            ->assertCount('cart', 1)
            ->assertSet('cart.0.unit_id', $this->carton->id)
            ->assertSet('cart.0.quantity', '2')
            ->assertSet('cart.0.unit_price', '60')
            ->call('pay', $this->cash)
            ->assertSet('payments.0.amount', '136.80')
            ->set('payments.0.amount', '150')
            ->call('complete')
            ->assertHasNoErrors()
            ->assertSet('cart', [])
            ->assertSee('13.20');

        $receipt = Receipt::sole();
        $this->assertSame('136.8000', (string) $receipt->total);
        $this->assertSame('13.2000', (string) $receipt->change);
        $this->assertSame('48.0000', $this->onHand($this->product->id, $this->warehouse->id));

        $this->get(route('pos.receipts.print', $receipt->id))->assertOk()->assertSee($receipt->number);
    }

    public function test_a_return_by_receipt_number_then_the_shift_is_counted_and_closed(): void
    {
        $register = $this->register();
        Livewire::test(Terminal::class)->set('opening.register_id', $register->id)->call('openShift')
            ->call('add', $this->product->id)
            ->set('cart.0.quantity', '3')
            ->call('pay', $this->cash)
            ->call('complete')
            ->assertHasNoErrors();
        $sale = Receipt::sole();

        Livewire::withQueryParams(['receipt' => $sale->number])->test(ReturnForm::class)
            ->assertSee($this->product->name)
            ->set('lines.'.$sale->lines()->value('id').'.quantity', '1')
            ->call('save')
            ->assertHasNoErrors();

        $return = Receipt::where('kind', ReceiptKind::Return)->sole();
        $this->assertSame('11.4000', (string) $return->total);

        $shift = Shift::sole();
        Livewire::test(ShiftShow::class, ['id' => $shift->id])
            ->assertSee('22.80')
            ->set('countedCash', '22.80')
            ->call('close')
            ->assertHasNoErrors();

        $this->assertSame(ShiftStatus::Closed, $shift->fresh()->status);
        $this->assertSame('0.0000', (string) $shift->fresh()->cash_difference);
    }

    public function test_pages_render_and_need_permissions(): void
    {
        $this->register();

        foreach (['/pos', '/pos/receipts', '/pos/returns/create', '/pos/shifts', '/pos/registers'] as $url) {
            $this->get($url)->assertOk();
            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
            $this->actingAs($this->admin);
        }
    }

    public function test_a_cashier_reaches_their_own_shift_but_not_others(): void
    {
        $register = $this->register();
        $cashier = User::factory()->create();
        $cashier->givePermissionTo('pos.terminal.sell');
        $cashier->branches()->attach($this->branch);

        $this->actingAs($cashier);
        Livewire::test(Terminal::class)->set('opening.register_id', $register->id)->call('openShift')->assertHasNoErrors();
        $own = Shift::sole();
        $this->get(route('pos.shifts.show', $own->id))->assertOk();

        $this->actingAs($this->admin);
        $other = Register::create(['code' => 'R2', 'name' => ['ar' => 'كاشير 2'], 'branch_id' => $this->branch->id, 'warehouse_id' => $this->warehouse->id, 'cash_payment_method_id' => $this->cash]);
        Livewire::test(Terminal::class)->set('opening.register_id', $other->id)->call('openShift');
        $adminShift = Shift::where('user_id', $this->admin->id)->sole();

        $this->actingAs($cashier)->get(route('pos.shifts.show', $adminShift->id))->assertForbidden();
    }
}
