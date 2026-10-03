<?php

namespace Modules\Products\Support;

use Closure;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Products\Models\Product;

/**
 * Lets modules that depend on Products (Inventory, Sales...) report that a product is in
 * use, without Products depending on them. Products asks before changes that would break
 * existing records (base unit, type, tracking) and before deleting.
 */
#[ModuleApi]
class ProductUsage
{
    /** @var array<int, Closure(Product): bool> */
    private array $checks = [];

    /**
     * @param  Closure(Product): bool  $check  true when the product is used by the registering module
     */
    public function register(Closure $check): void
    {
        $this->checks[] = $check;
    }

    public function inUse(Product $product): bool
    {
        foreach ($this->checks as $check) {
            if ($check($product)) {
                return true;
            }
        }

        return false;
    }
}
