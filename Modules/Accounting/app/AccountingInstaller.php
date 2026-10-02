<?php

namespace Modules\Accounting;

use Carbon\CarbonImmutable;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\FiscalYears\Actions\CreateFiscalYear;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Core\Modules\ModuleInstaller;
use Modules\Core\Sequences\Sequences;
use Modules\Core\Settings\Settings;

class AccountingInstaller implements ModuleInstaller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Sequences $sequences,
        private readonly CreateFiscalYear $fiscalYears,
    ) {}

    public function install(): void
    {
        $this->installChart($this->settings->get('accounting.chart_template'));
        $this->sequences->define(PostJournalEntry::SEQUENCE, 'JE-{yyyy}-', 6);

        if (! FiscalYear::exists()) {
            $this->fiscalYears->handle(null, CarbonImmutable::now()->startOfYear());
        }
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        //
    }

    private function installChart(string $template): void
    {
        $chart = require module_path('Accounting', "database/data/charts/{$template}.php");
        $systemCodes = array_values($chart['mappings']);
        $ids = [];

        foreach ($chart['accounts'] as [$code, $parent, $nameAr, $nameEn, $type, $subtype, $isGroup]) {
            $subtype = $subtype ? AccountSubtype::from($subtype) : null;

            $account = Account::firstOrCreate(['code' => $code], [
                'name' => ['ar' => $nameAr, 'en' => $nameEn],
                'parent_id' => $parent ? $ids[$parent] : null,
                'is_group' => $isGroup,
                'type' => AccountType::from($type),
                'subtype' => $subtype,
                'requires_partner' => $subtype?->requiresPartner() ?? false,
                'is_system' => in_array($code, $systemCodes, true),
            ]);

            $ids[$code] = $account->id;
        }

        foreach ($chart['mappings'] as $key => $code) {
            AccountMapping::firstOrCreate(
                ['key' => $key, 'scope_type' => null, 'scope_id' => null],
                ['account_id' => $ids[$code]],
            );
        }
    }
}
