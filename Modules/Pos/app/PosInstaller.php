<?php

namespace Modules\Pos;

use Modules\Accounting\Mappings\ChartTemplate;
use Modules\Core\Modules\ModuleInstaller;
use Modules\Core\Sequences\Sequences;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Models\Shift;

class PosInstaller implements ModuleInstaller
{
    public function __construct(
        private readonly Sequences $sequences,
        private readonly ChartTemplate $chart,
    ) {}

    public function install(): void
    {
        $this->sequences->define(Shift::SEQUENCE, 'SH-{yyyy}-', 5);
        $this->sequences->define(Receipt::SEQUENCE, 'R-{yyyy}-', 6);

        $this->chart->ensureMappings(['pos.cash_difference', 'sales.receivable', 'sales.revenue', 'sales.returns', 'tax.output', 'inventory.cogs']);
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        //
    }
}
