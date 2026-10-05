<?php

return [
    'title' => 'ETA POS devices',
    'hint' => 'Every register that issues E-Receipts must be registered here with the serial number registered at ETA.',
    'fields' => [
        'register' => 'Register',
        'serial' => 'Device serial number',
        'os_version' => 'Operating system',
        'model_framework' => 'Model framework',
        'pre_shared_key' => 'Pre-shared key',
        'client_id' => 'Device client ID',
        'client_secret' => 'Device client secret',
        'branch_code' => 'ETA branch code',
        'credentials' => 'Credentials',
    ],
    'credentials_hint' => 'Leave empty to use the company\'s credentials.',
    'own_credentials' => 'Own',
    'company_credentials' => 'Company\'s',
    'address' => 'Branch address',
    'address_fields' => [
        'country' => 'Country (EG)',
        'governate' => 'Governorate',
        'regionCity' => 'City / region',
        'street' => 'Street',
        'buildingNumber' => 'Building number',
        'postalCode' => 'Postal code',
        'floor' => 'Floor',
        'room' => 'Room',
        'landmark' => 'Landmark',
        'additionalInformation' => 'Additional information',
    ],
];
