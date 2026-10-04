<?php

return [
    'name' => 'Core',

    // Database backups (`erp:backup`, and before every `erp:upgrade`).
    'backup' => [
        // Full path when mysqldump is not on the PATH, e.g. C:/wamp64/bin/mysql/mysql8.4.7/bin/mysqldump.exe
        'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
        'directory' => storage_path('app/backups'),
        // Older backups are deleted; 0 keeps them all.
        'keep' => (int) env('BACKUP_KEEP', 10),
    ],
];
