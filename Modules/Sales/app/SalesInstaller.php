<?php

namespace Modules\Sales;

use Modules\Accounting\Mappings\ChartTemplate;
use Modules\Core\Models\Partner;
use Modules\Core\Modules\ModuleInstaller;
use Modules\Core\Sequences\Sequences;
use Modules\Core\Settings\Settings;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesReturn;

class SalesInstaller implements ModuleInstaller
{
    public function __construct(
        private readonly Sequences $sequences,
        private readonly ChartTemplate $chart,
        private readonly Settings $settings,
    ) {}

    public function install(): void
    {
        $this->sequences->define(SalesInvoice::SEQUENCE, 'SI-{yyyy}-', 5);
        $this->sequences->define(SalesReturn::SEQUENCE, 'SR-{yyyy}-', 5);

        $this->chart->ensureMappings(['sales.receivable', 'sales.revenue', 'sales.returns', 'tax.output', 'inventory.cogs']);

        // Counter sales without a named customer post to this partner.
        if ($this->settings->get('sales.walk_in_partner_id') === null) {
            $name = __('sales::invoices.walk_in_customer', locale: $this->settings->get('core.default_locale'));
            $walkIn = Partner::create(['type' => 'person', 'name' => $name, 'is_customer' => true]);
            $this->settings->set('sales.walk_in_partner_id', $walkIn->id);
        }
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        //
    }
}
