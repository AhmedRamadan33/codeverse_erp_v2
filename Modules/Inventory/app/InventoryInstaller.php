<?php

namespace Modules\Inventory;

use Modules\Core\Models\Branch;
use Modules\Core\Modules\ModuleInstaller;
use Modules\Core\Sequences\Sequences;
use Modules\Inventory\Models\StockAdjustment;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Warehouse;

class InventoryInstaller implements ModuleInstaller
{
    public function __construct(private readonly Sequences $sequences) {}

    public function install(): void
    {
        $this->sequences->define(StockAdjustment::SEQUENCE, 'ADJ-{yyyy}-', 5);
        $this->sequences->define(StockTransfer::SEQUENCE, 'TRF-{yyyy}-', 5);

        // One main warehouse per existing branch, so stock can be entered right away.
        foreach (Branch::all() as $branch) {
            if (! Warehouse::where('branch_id', $branch->id)->exists()) {
                Warehouse::create([
                    'name' => ['ar' => 'المخزن الرئيسي - '.$branch->getTranslation('name', 'ar'), 'en' => 'Main warehouse - '.$branch->getTranslation('name', 'en')],
                    'code' => 'WH-'.$branch->code,
                    'branch_id' => $branch->id,
                ]);
            }
        }
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        //
    }
}
