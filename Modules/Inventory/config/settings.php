<?php

// Defaults for Inventory settings, read as "inventory.<key>"; both can be set per branch.
return [
    // Issue more than the stock on hand (costed at the last average). Off: issues fail instead.
    'allow_negative_stock' => false,
];
