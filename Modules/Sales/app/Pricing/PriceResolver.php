<?php

namespace Modules\Sales\Pricing;

use Brick\Math\BigDecimal;
use Modules\Products\Models\Product;
use Modules\Products\Support\UnitConverter;
use Modules\Sales\Models\CustomerProfile;
use Modules\Sales\Models\PriceListItem;

/**
 * The default selling price of a product unit: the price list's price when it has one,
 * otherwise the unit's own price, otherwise the base price × factor.
 */
class PriceResolver
{
    public function __construct(private readonly UnitConverter $converter) {}

    public function priceListFor(?int $partnerId): ?int
    {
        return $partnerId ? CustomerProfile::whereKey($partnerId)->value('price_list_id') : null;
    }

    public function price(Product $product, int $unitId, ?int $priceListId = null): BigDecimal
    {
        if ($priceListId) {
            $listed = PriceListItem::where(['price_list_id' => $priceListId, 'product_id' => $product->id, 'unit_id' => $unitId])->first();

            if ($listed) {
                return $listed->price;
            }
        }

        return $this->converter->productUnit($product, $unitId)->effectiveSalePrice();
    }
}
