<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Modules\Inventory\Stock\StockConsistency;

class CheckStockCommand extends Command
{
    protected $signature = 'inventory:check';

    protected $description = 'Compare stock balances and product costs with the stock moves';

    public function handle(StockConsistency $consistency): int
    {
        $issues = $consistency->differences();

        foreach ($issues as $issue) {
            $this->components->error($issue);
        }

        if ($issues === []) {
            $this->components->info(__('inventory::stock.consistent'));
        }

        return $issues === [] ? self::SUCCESS : self::FAILURE;
    }
}
