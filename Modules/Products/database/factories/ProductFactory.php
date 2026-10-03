<?php

namespace Modules\Products\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Products\Enums\ProductType;
use Modules\Products\Enums\Tracking;
use Modules\Products\Models\Product;
use Modules\Products\Models\Unit;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-#####')),
            'name' => ['ar' => 'صنف '.$this->faker->unique()->numberBetween(1, 99999), 'en' => 'Item'],
            'type' => ProductType::Stockable,
            'tracking' => Tracking::None,
            'base_unit_id' => fn () => Unit::query()->value('id'),
            'sale_price' => '100',
            'purchase_price' => '70',
            'is_active' => true,
        ];
    }

    /**
     * Every product has a row for its base unit with factor 1.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            $product->units()->firstOrCreate(['unit_id' => $product->base_unit_id], [
                'factor' => '1', 'is_default_sale' => true, 'is_default_purchase' => true,
            ]);
        });
    }

    /**
     * Adds a bigger unit, e.g. withUnit($carton, '12').
     */
    public function withUnit(Unit $unit, string $factor): static
    {
        return $this->afterCreating(fn (Product $product) => $product->units()->create(['unit_id' => $unit->id, 'factor' => $factor]));
    }

    public function tracking(Tracking $tracking): static
    {
        return $this->state(['tracking' => $tracking]);
    }
}
