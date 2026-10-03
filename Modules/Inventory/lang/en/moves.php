<?php

return [
    'types' => [
        'purchase' => 'Purchase',
        'purchase_return' => 'Purchase return',
        'sale' => 'Sale',
        'sale_return' => 'Sales return',
        'adjustment' => 'Adjustment',
        'opening' => 'Opening stock',
        'transfer_in' => 'Transfer in',
        'transfer_out' => 'Transfer out',
        'production_in' => 'Production output',
        'production_out' => 'Production input',
    ],
    'serial_status' => [
        'in_stock' => 'In stock',
        'out' => 'Out of stock',
    ],
    'no_lines' => 'Add at least one product.',
    'not_stockable' => ':product is not a stocked product.',
    'quantity_positive' => 'The quantity of :product must be more than zero.',
    'warehouse_inactive' => 'The warehouse is inactive or missing.',
    'same_warehouse' => 'Choose a different destination warehouse.',
    'insufficient' => 'Not enough :product in :warehouse: available :available, requested :requested.',
    'batch_required' => 'Enter the batch number of :product.',
    'batch_unknown' => 'Batch :batch was not found.',
    'serial_count' => ':product needs exactly :quantity serial numbers.',
    'serial_in_stock' => 'Serial :serial is already in stock.',
    'serial_not_available' => 'Serial :serial is not in stock in this warehouse.',
];
