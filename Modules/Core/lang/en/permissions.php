<?php

return [
    'resources' => [
        'users' => 'Users',
        'roles' => 'Roles',
        'branches' => 'Branches',
        'settings' => 'Company settings',
        'currencies' => 'Currencies',
        'exchange_rates' => 'Exchange rates',
        'sequences' => 'Document numbering',
        'partners' => 'Customers & suppliers',
        'audit' => 'Audit log',
        'modules' => 'Modules',
    ],
    // Shared action names; modules may add their own in <module>::permissions.actions.
    'actions' => [
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
        'manage' => 'Manage',
        'post' => 'Post',
        'cancel' => 'Cancel',
        'all_access' => 'Access all branches',
    ],
];
