<?php

return [
    'title' => 'Customers & suppliers',
    'new' => 'New partner',
    'edit' => 'Edit partner',
    'type' => [
        'person' => 'Individual',
        'company' => 'Company',
    ],
    'fields' => [
        'type' => 'Type',
        'name' => 'Name',
        'is_customer' => 'Customer',
        'is_supplier' => 'Supplier',
        'roles' => 'Role',
        'tax_number' => 'Tax registration number',
        'national_id' => 'National ID',
        'commercial_register' => 'Commercial register',
        'credit_limit' => 'Credit limit',
        'payment_term_days' => 'Payment term (days)',
        'branch' => 'Branch',
    ],
    'all_branches' => 'All branches',
    'role_required' => 'Choose customer, supplier or both.',
    'branch_not_allowed' => 'You cannot add partners to this branch.',
    'in_use' => 'This partner is used in documents and cannot be deleted; deactivate it instead.',
];
