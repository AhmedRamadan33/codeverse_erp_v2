<?php

return [
    'title' => 'Tax codes',
    'tabs' => [
        'items' => 'Products',
        'units' => 'Units',
        'taxes' => 'Taxes',
    ],
    'missing_only' => 'Only products without a code',
    'items_hint' => 'The EGS or GS1 code registered at ETA. Leave the code empty to remove it.',
    'units_hint' => 'The ETA unit type, e.g. EA for a piece, KGM for a kilogram.',
    'taxes_hint' => 'The ETA tax type and subtype, e.g. T1 / V009 for VAT.',
    'fields' => [
        'sku' => 'SKU',
        'product' => 'Product',
        'code_type' => 'Code type',
        'item_code' => 'Item code',
        'unit' => 'Unit',
        'unit_type' => 'Unit type',
        'tax' => 'Tax',
        'tax_type' => 'Tax type',
        'sub_type' => 'Subtype',
    ],
];
