<?php

namespace Modules\Accounting\Mappings;

use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;
use Modules\Core\Settings\Settings;
use Modules\Core\Support\Attributes\ModuleApi;

/**
 * Lets a module installed later make sure its mapping keys exist: missing default mappings
 * (and their accounts, with their parents) are created from the installation's chart template.
 */
#[ModuleApi]
class ChartTemplate
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  string[]  $keys
     */
    public function ensureMappings(array $keys): void
    {
        $chart = require module_path('Accounting', 'database/data/charts/'.$this->settings->get('accounting.chart_template').'.php');
        $rows = collect($chart['accounts'])->keyBy(0);

        foreach ($keys as $key) {
            if (AccountMapping::where('key', $key)->whereNull('scope_type')->exists() || ! isset($chart['mappings'][$key])) {
                continue;
            }

            $account = $this->ensureAccount($chart['mappings'][$key], $rows->all());
            AccountMapping::create(['key' => $key, 'account_id' => $account->id]);
        }
    }

    /**
     * @param  array<string, array<int, mixed>>  $rows
     */
    private function ensureAccount(string $code, array $rows): Account
    {
        if ($existing = Account::where('code', $code)->first()) {
            return $existing;
        }

        [, $parent, $nameAr, $nameEn, $type, $subtype, $isGroup] = $rows[$code];
        $subtype = $subtype ? AccountSubtype::from($subtype) : null;

        return Account::create([
            'code' => $code,
            'name' => ['ar' => $nameAr, 'en' => $nameEn],
            'parent_id' => $parent ? $this->ensureAccount($parent, $rows)->id : null,
            'is_group' => $isGroup,
            'type' => AccountType::from($type),
            'subtype' => $subtype,
            'requires_partner' => $subtype?->requiresPartner() ?? false,
            'is_system' => true,
        ]);
    }
}
