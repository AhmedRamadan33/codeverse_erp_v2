<?php

// Defaults for Sales settings, read as "sales.<key>".
return [
    // block | warn | off (core-design.md §5.2); can be set per branch.
    'credit_limit_mode' => 'warn',
    // Partner used for counter sales without a named customer; created by the installer.
    'walk_in_partner_id' => null,
];
