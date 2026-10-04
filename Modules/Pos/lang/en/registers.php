<?php

return [
    'title' => 'Registers',
    'fields' => [
        'code' => 'Code',
        'name' => 'Name',
        'warehouse' => 'Warehouse',
        'cash_method' => 'Cash drawer',
        'price_list' => 'Price list',
        'open_shift' => 'Open shift',
    ],
    'default_price_list' => 'Product prices',
    'drawer_not_cash' => 'The drawer must be a cash payment method.',
    'has_open_shift' => 'Close the open shift before moving the register to another warehouse.',
];
