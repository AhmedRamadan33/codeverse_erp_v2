<?php

// Defaults for Accounting settings, read as "accounting.<key>".
return [
    // Chart template chosen at installation (database/data/charts/<template>.php).
    'chart_template' => 'egypt',
    // Y-m-d. Entries dated on or before it need accounting.entries.post_before_lock.
    'lock_date' => null,
];
