<?php

return [
    'created' => 'Database backup saved: :file',
    'failed' => 'The database backup failed. Check MYSQLDUMP_PATH in .env.',
    'upgrade_aborted' => 'Upgrade stopped before changing anything: the database backup failed (:error). Fix it, or run with --no-backup only if you have another fresh backup.',
];
