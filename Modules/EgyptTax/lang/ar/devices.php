<?php

return [
    'title' => 'أجهزة نقاط البيع لدى المصلحة',
    'hint' => 'كل نقطة بيع تصدر إيصالات إلكترونية يجب تسجيلها هنا بالرقم التسلسلي المسجل لدى مصلحة الضرائب.',
    'fields' => [
        'register' => 'نقطة البيع',
        'serial' => 'الرقم التسلسلي للجهاز',
        'os_version' => 'نظام التشغيل',
        'model_framework' => 'إطار عمل الجهاز',
        'pre_shared_key' => 'المفتاح المشترك (Pre-shared key)',
        'client_id' => 'Client ID خاص بالجهاز',
        'client_secret' => 'Client Secret خاص بالجهاز',
        'branch_code' => 'كود الفرع لدى المصلحة',
        'credentials' => 'بيانات الدخول',
    ],
    'credentials_hint' => 'اتركه فارغًا لاستخدام بيانات دخول الشركة.',
    'own_credentials' => 'خاصة بالجهاز',
    'company_credentials' => 'بيانات الشركة',
    'address' => 'عنوان الفرع',
    'address_fields' => [
        'country' => 'الدولة (EG)',
        'governate' => 'المحافظة',
        'regionCity' => 'المدينة / المنطقة',
        'street' => 'الشارع',
        'buildingNumber' => 'رقم المبنى',
        'postalCode' => 'الرمز البريدي',
        'floor' => 'الدور',
        'room' => 'الغرفة',
        'landmark' => 'علامة مميزة',
        'additionalInformation' => 'معلومات إضافية',
    ],
];
