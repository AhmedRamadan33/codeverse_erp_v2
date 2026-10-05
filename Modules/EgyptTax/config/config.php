<?php

return [
    'name' => 'EgyptTax',

    // ETA endpoints per environment (eta_settings.environment).
    'environments' => [
        'preprod' => [
            'identity' => 'https://id.preprod.eta.gov.eg',
            'api' => 'https://api.preprod.invoicing.eta.gov.eg',
            'portal' => 'https://preprod.invoicing.eta.gov.eg',
        ],
        'production' => [
            'identity' => 'https://id.eta.gov.eg',
            'api' => 'https://api.invoicing.eta.gov.eg',
            'portal' => 'https://invoicing.eta.gov.eg',
        ],
    ],

    // A person buyer must be identified from this receipt total (EGP).
    'buyer_id_threshold' => '150000',

    // Receipts sent per submission, in chain order.
    'batch_size' => 50,

    // Retry delay after a failed submission: doubles per attempt up to the maximum (minutes).
    'retry_minutes' => 1,
    'retry_max_minutes' => 60,

    'timeout' => 30,
];
