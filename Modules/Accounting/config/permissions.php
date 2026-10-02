<?php

// Permissions owned by Accounting. Synced on install and upgrade.
return [
    'accounting.accounts.view',
    'accounting.accounts.manage',
    'accounting.entries.view',
    'accounting.entries.create',
    'accounting.entries.post',
    'accounting.entries.reverse',
    // Post or reverse with a date on or before the lock date (adjusting entries).
    'accounting.entries.post_before_lock',
    'accounting.fiscal_years.manage',
    'accounting.mappings.manage',
    'accounting.taxes.manage',
    'accounting.payment_methods.manage',
    'accounting.vouchers.view',
    'accounting.vouchers.create',
    'accounting.vouchers.post',
    'accounting.vouchers.cancel',
    'accounting.reports.view',
];
