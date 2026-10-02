<?php

// Permissions owned by Core: "<module>.<resource>.<action>". Synced on install and upgrade.
return [
    'core.users.view',
    'core.users.manage',
    'core.roles.manage',
    'core.branches.view',
    'core.branches.manage',
    // See and work in every branch, not only the ones assigned to the user.
    'core.branches.all_access',
    'core.settings.manage',
    'core.currencies.manage',
    'core.exchange_rates.manage',
    'core.sequences.manage',
    'core.partners.view',
    'core.partners.create',
    'core.partners.update',
    'core.partners.delete',
    'core.audit.view',
    'core.modules.manage',
];
