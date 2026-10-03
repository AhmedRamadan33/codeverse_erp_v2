<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Products\Actions\SaveProduct;
use Modules\Products\Livewire\Products\Form;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;
use Modules\Products\Support\ProductLookup;
use Modules\Products\Support\ProductUsage;
use Modules\Products\Support\UnitConverter;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use InstallsErp, RefreshDatabase;

    private Unit $piece;

    private Unit $carton;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->piece = Unit::where('name->en', 'Piece')->firstOrFail();
        $this->carton = Unit::where('name->en', 'Carton')->firstOrFail();
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'MILK-1L', 'name_ar' => 'لبن كامل الدسم لتر', 'name_en' => 'Full cream milk 1L',
            'type' => 'stockable', 'tracking' => 'batch', 'base_unit_id' => $this->piece->id,
            'sale_price' => '45.5', 'purchase_price' => '38',
            'base_barcodes' => ['6221000000011'],
            'units' => [['unit_id' => $this->carton->id, 'factor' => '12', 'sale_price' => '520', 'barcodes' => ['6221000000028']]],
            'default_purchase_unit_id' => $this->carton->id,
        ], $overrides);
    }

    public function test_a_product_is_saved_with_units_barcodes_and_conversions(): void
    {
        $product = app(SaveProduct::class)->handle($this->admin, $this->data());

        $this->assertCount(2, $product->units);
        $this->assertSame('144.0000', (string) app(UnitConverter::class)->toBase($product, $this->carton->id, '12'));
        $this->assertSame('546.0000', (string) app(UnitConverter::class)->priceFromBase($product, $this->carton->id, '45.5'));

        $scan = app(ProductLookup::class)->byBarcode('6221000000028');
        $this->assertTrue($scan['product']->is($product));
        $this->assertSame($this->carton->id, $scan['unit']->unit_id);
        $this->assertSame('520.0000', (string) $scan['unit']->effectiveSalePrice());

        $this->assertTrue(app(ProductLookup::class)->search('كامل')->get()->contains($product));
        $this->assertTrue($product->units->firstWhere('unit_id', $this->carton->id)->is_default_purchase);
    }

    public function test_editing_keeps_unit_rows_and_replaces_barcodes(): void
    {
        $product = app(SaveProduct::class)->handle($this->admin, $this->data());
        $cartonRow = $product->units->firstWhere('unit_id', $this->carton->id)->id;

        app(SaveProduct::class)->handle($this->admin, $this->data([
            'units' => [['unit_id' => $this->carton->id, 'factor' => '24', 'barcodes' => ['999']]],
        ]), $product);

        $carton = $product->fresh()->units->firstWhere('unit_id', $this->carton->id);
        $this->assertSame($cartonRow, $carton->id);
        $this->assertSame('24.0000', (string) $carton->factor);
        $this->assertNull(app(ProductLookup::class)->byBarcode('6221000000028'));
    }

    public function test_the_base_unit_cannot_be_repeated_and_barcodes_must_be_unique(): void
    {
        $this->expectException(ValidationException::class);

        app(SaveProduct::class)->handle($this->admin, $this->data([
            'units' => [['unit_id' => $this->piece->id, 'factor' => '2']],
        ]));
    }

    public function test_a_barcode_of_another_product_is_refused(): void
    {
        app(SaveProduct::class)->handle($this->admin, $this->data());

        $this->expectException(ValidationException::class);
        app(SaveProduct::class)->handle($this->admin, $this->data(['sku' => 'OTHER', 'units' => [], 'base_barcodes' => ['6221000000011']]));
    }

    public function test_core_fields_are_locked_once_another_module_uses_the_product(): void
    {
        $product = app(SaveProduct::class)->handle($this->admin, $this->data());
        app(ProductUsage::class)->register(fn (Product $p) => $p->is($product));

        $this->expectException(ValidationException::class);
        app(SaveProduct::class)->handle($this->admin, $this->data(['tracking' => 'none']), $product);
    }

    public function test_services_never_track_stock(): void
    {
        $service = app(SaveProduct::class)->handle($this->admin, $this->data(['sku' => 'INSTALL', 'type' => 'service', 'tracking' => 'serial', 'units' => [], 'base_barcodes' => []]));

        $this->assertSame('none', $service->tracking->value);
        $this->assertFalse($service->tracksStock());
    }

    public function test_the_form_saves_comma_separated_barcodes(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Form::class)
            ->set('form.sku', 'SUGAR')
            ->set('form.name_ar', 'سكر')
            ->set('form.base_barcodes', '111, 222')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['111', '222'], Product::firstWhere('sku', 'SUGAR')->barcodes()->orderBy('barcode')->pluck('barcode')->all());
        $this->get('/products')->assertOk()->assertSee('SUGAR');
        $this->get('/products/units')->assertOk();
        $this->get('/products/categories')->assertOk();
    }

    public function test_the_api_lists_and_scans_products(): void
    {
        app(SaveProduct::class)->handle($this->admin, $this->data());
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/products?search=MILK')->assertOk()
            ->assertJsonPath('data.0.sku', 'MILK-1L')
            ->assertJsonPath('data.0.units.1.factor', '12.0000');
        $this->getJson('/api/v1/products/barcode/6221000000028')->assertOk()->assertJsonPath('data.unit_id', $this->carton->id);
        $this->getJson('/api/v1/products/barcode/000')->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/products')->assertForbidden();
    }
}
