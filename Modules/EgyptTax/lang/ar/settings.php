<?php

return [
    'title' => 'إعدادات مصلحة الضرائب',
    'fields' => [
        'environment' => 'البيئة',
        'rin' => 'رقم التسجيل الضريبي',
        'company_trade_name' => 'الاسم التجاري',
        'activity_code' => 'كود النشاط',
        'client_id' => 'Client ID',
        'client_secret' => 'Client Secret',
        'exempt_tax_type' => 'نوع الضريبة للأصناف غير الخاضعة',
        'exempt_sub_type' => 'النوع الفرعي للأصناف غير الخاضعة',
        'is_active' => 'إصدار إيصالات إلكترونية لنقاط البيع',
    ],
    'environments' => [
        'preprod' => 'تجريبية (Pre-production)',
        'production' => 'الإنتاج',
    ],
    'secret_kept' => 'محفوظ؛ اتركه فارغًا للإبقاء عليه',
    'credentials_hint' => 'بيانات دخول الشركة. يمكن لكل جهاز أن يحمل بيانات دخول خاصة به.',
    'exempt_hint' => 'تُستخدم للأصناف المبيعة دون ضريبة، مثل T1 / V003.',
];
