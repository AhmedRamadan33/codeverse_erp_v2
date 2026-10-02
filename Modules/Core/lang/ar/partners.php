<?php

return [
    'title' => 'العملاء والموردين',
    'new' => 'جهة تعامل جديدة',
    'edit' => 'تعديل جهة تعامل',
    'type' => [
        'person' => 'فرد',
        'company' => 'شركة',
    ],
    'fields' => [
        'type' => 'النوع',
        'name' => 'الاسم',
        'is_customer' => 'عميل',
        'is_supplier' => 'مورد',
        'roles' => 'الصفة',
        'tax_number' => 'رقم التسجيل الضريبي',
        'national_id' => 'الرقم القومي',
        'commercial_register' => 'السجل التجاري',
        'credit_limit' => 'حد الائتمان',
        'payment_term_days' => 'مدة السداد (أيام)',
        'branch' => 'الفرع',
    ],
    'all_branches' => 'كل الفروع',
    'role_required' => 'اختر عميل أو مورد أو الاثنين.',
    'branch_not_allowed' => 'لا يمكنك إضافة جهات تعامل لهذا الفرع.',
    'in_use' => 'جهة التعامل مستخدمة في مستندات ولا يمكن حذفها؛ قم بإيقافها بدلاً من ذلك.',
];
