<?php

return [
    'title' => 'Tax authority settings',
    'fields' => [
        'environment' => 'Environment',
        'rin' => 'Tax registration number (RIN)',
        'company_trade_name' => 'Trade name',
        'activity_code' => 'Activity code',
        'client_id' => 'Client ID',
        'client_secret' => 'Client secret',
        'exempt_tax_type' => 'Tax type for untaxed items',
        'exempt_sub_type' => 'Subtype for untaxed items',
        'is_active' => 'Issue E-Receipts for POS receipts',
    ],
    'environments' => [
        'preprod' => 'Pre-production',
        'production' => 'Production',
    ],
    'secret_kept' => 'Stored; leave empty to keep it',
    'credentials_hint' => 'The company\'s credentials. A device may have its own.',
    'exempt_hint' => 'Used for items sold without a tax, e.g. T1 / V003.',
];
