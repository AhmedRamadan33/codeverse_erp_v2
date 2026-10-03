<?php

namespace Modules\Purchases;

use Modules\Accounting\Mappings\ChartTemplate;
use Modules\Core\Modules\ModuleInstaller;
use Modules\Core\Sequences\Sequences;
use Modules\Purchases\Models\PurchaseInvoice;
use Modules\Purchases\Models\PurchaseReturn;

class PurchasesInstaller implements ModuleInstaller
{
    public function __construct(
        private readonly Sequences $sequences,
        private readonly ChartTemplate $chart,
    ) {}

    public function install(): void
    {
        $this->sequences->define(PurchaseInvoice::SEQUENCE, 'PI-{yyyy}-', 5);
        $this->sequences->define(PurchaseReturn::SEQUENCE, 'PR-{yyyy}-', 5);

        $this->chart->ensureMappings(['purchases.payable', 'purchases.grni', 'purchases.expense', 'inventory.price_difference', 'tax.input']);
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        //
    }
}
