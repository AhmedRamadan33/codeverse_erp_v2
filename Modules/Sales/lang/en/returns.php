<?php

return [
    'title' => 'Sales returns',
    'one' => 'Sales return',
    'new' => 'Customer return',
    'fields' => [
        'invoice' => 'Invoice',
        'invoiced' => 'Sold',
        'returnable' => 'Can return',
        'quantity' => 'Return qty',
        'cost' => 'Original cost',
    ],
    'post' => 'Post',
    'cancel' => 'Cancel return',
    'posted' => 'Return :number posted.',
    'cancelled' => 'Return cancelled.',
    'entry_description' => 'Return from :customer',
    'invoice_not_posted' => 'Only posted invoices can be returned.',
    'before_invoice' => 'The return cannot be dated before the invoice.',
    'line_not_on_invoice' => 'This line is not on the invoice.',
    'too_much' => 'At most :returnable of :product can still be returned.',
];
