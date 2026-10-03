<?php

namespace Modules\Inventory\Documents;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
use Modules\Products\Models\Product;
use Modules\Products\Support\UnitConverter;

/**
 * Turns document lines entered in any product unit into base-unit quantities and costs.
 */
class DocumentLines
{
    public function __construct(private readonly UnitConverter $converter) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines  validated input lines
     * @return array<int, array{product: Product, factor: BigDecimal, quantity: BigDecimal, base_quantity: BigDecimal}>
     */
    public function convert(array $lines): array
    {
        $products = Product::with('units')->whereKey(array_column($lines, 'product_id'))->get()->keyBy('id');
        $converted = [];

        foreach (array_values($lines) as $i => $line) {
            $product = $products[$line['product_id']];

            if (! $product->tracksStock()) {
                throw ValidationException::withMessages(["lines.{$i}.product_id" => __('inventory::moves.not_stockable', ['product' => $product->name])]);
            }

            $quantity = BigDecimal::of((string) $line['quantity'])->toScale(4);
            $factor = $this->converter->productUnit($product, (int) $line['unit_id'])->factor;

            $converted[$i] = [
                'product' => $product,
                'factor' => $factor,
                'quantity' => $quantity,
                'base_quantity' => $quantity->multipliedBy($factor)->toScale(4),
            ];
        }

        return $converted;
    }

    /**
     * A cost entered per document unit, as a cost per base unit.
     */
    public function baseCost(BigDecimal $unitCost, BigDecimal $factor): BigDecimal
    {
        return $unitCost->dividedBy($factor, 4, RoundingMode::HalfUp);
    }
}
