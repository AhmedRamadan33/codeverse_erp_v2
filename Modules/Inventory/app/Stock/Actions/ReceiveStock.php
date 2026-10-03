<?php

namespace Modules\Inventory\Stock\Actions;

use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Core\Support\TransactionGuard;
use Modules\Inventory\Stock\StockEngine;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Inventory\Stock\StockResult;
use Modules\Inventory\Stock\Valuation;
use Modules\Products\Models\Product;

/**
 * Stock coming in (purchases, returns from customers, positive adjustments, opening stock).
 * Valuation entry: Dr inventory / Cr the counter account the caller names.
 */
#[ModuleApi]
class ReceiveStock
{
    public function __construct(
        private readonly StockEngine $engine,
        private readonly Valuation $valuation,
        private readonly StockLines $lines,
    ) {}

    public function handle(StockOperationData $op): StockResult
    {
        TransactionGuard::assertActive('ReceiveStock');

        $products = $this->lines->prepare($op);
        $movesByLine = [];

        foreach ($op->lines as $i => $line) {
            $movesByLine[$i] = $this->engine->receive($op, $line, $products[$line->productId]);
        }

        $result = new StockResult($movesByLine);

        if (! $op->deferValuation) {
            $result->journalEntry = $this->valuation->post($result->moves(), $op->date, $op->branchId, $op->source, $op->counterAccountKey, $op->counterScopes, $op->description, $op->postedBy);
        }

        return $result;
    }
}
