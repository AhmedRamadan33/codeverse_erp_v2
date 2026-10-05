<?php

namespace Modules\EgyptTax;

use Modules\Accounting\Models\Tax;
use Modules\Core\Modules\ModuleInstaller;
use Modules\EgyptTax\Models\EtaSetting;
use Modules\EgyptTax\Models\EtaTaxCode;

class EgyptTaxInstaller implements ModuleInstaller
{
    // ETA codes of the taxes in the Egyptian chart template.
    private const TAX_CODES = [
        'VAT14' => ['T1', 'V009'],
        'VAT0' => ['T1', 'V003'],
    ];

    public function install(): void
    {
        if (! EtaSetting::exists()) {
            EtaSetting::create(['environment' => 'preprod', 'is_active' => false]);
        }

        foreach (self::TAX_CODES as $code => [$type, $subType]) {
            if ($taxId = Tax::where('code', $code)->value('id')) {
                EtaTaxCode::firstOrCreate(['tax_id' => $taxId], ['tax_type' => $type, 'sub_type' => $subType]);
            }
        }
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        //
    }
}
