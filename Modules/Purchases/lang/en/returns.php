<?php

return [
    'title' => 'Purchase returns',
    'one' => 'Purchase return',
    'new' => 'Return goods to supplier',
    'fields' => [
        'invoice' => 'Invoice',
        'invoiced' => 'Invoiced',
        'returnable' => 'Can return',
        'quantity' => 'Return qty',
        'stock_cost' => 'Stock cost',
    ],
    'post' => 'Post',
    'cancel' => 'Cancel return',
    'posted' => 'Return :number posted.',
    'cancelled' => 'Return cancelled.',
    'entry_description' => 'Return to :supplier',
    'invoice_not_posted' => 'Only posted invoices can be returned.',
    'before_invoice' => 'The return cannot be dated before the invoice.',
    'line_not_on_invoice' => 'This line is not on the invoice.',
    'too_much' => 'At most :returnable of :product can still be returned.',
];
