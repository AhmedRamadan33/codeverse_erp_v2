<?php

return [
    'from' => 'From',
    'to' => 'To',
    'print' => 'Print',
    'opening' => 'Opening balance',
    'movement' => 'Movement',
    'closing' => 'Closing balance',
    'opening_balance' => 'Opening balance',
    'closing_balance' => 'Totals and closing balance',
    'side' => 'Account',
    'open_debits' => 'Open items owed to us (unpaid invoices)',
    'open_credits' => 'Open items owed by us (unallocated payments, credit notes)',
    'total' => 'Total',
    'aging' => [
        'kind' => 'Report',
        'receivable' => 'Aged receivables',
        'payable' => 'Aged payables',
        'partner' => 'Customer / supplier',
        'balance' => 'Balance',
        'columns' => [
            'current' => 'Not due',
            'd30' => '1–30 days',
            'd60' => '31–60 days',
            'd90' => '61–90 days',
            'older' => 'Over 90 days',
            'unallocated' => 'Unallocated',
        ],
    ],
];
