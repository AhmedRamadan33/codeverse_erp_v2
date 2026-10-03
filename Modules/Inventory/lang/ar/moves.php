<?php

return [
    'types' => [
        'purchase' => 'مشتريات',
        'purchase_return' => 'مرتجع مشتريات',
        'sale' => 'مبيعات',
        'sale_return' => 'مرتجع مبيعات',
        'adjustment' => 'تسوية',
        'opening' => 'رصيد افتتاحي',
        'transfer_in' => 'تحويل وارد',
        'transfer_out' => 'تحويل صادر',
        'production_in' => 'إنتاج تام',
        'production_out' => 'خامات للإنتاج',
    ],
    'serial_status' => [
        'in_stock' => 'في المخزن',
        'out' => 'خارج المخزن',
    ],
    'no_lines' => 'أضف صنفاً واحداً على الأقل.',
    'not_stockable' => ':product ليس صنفاً مخزنياً.',
    'quantity_positive' => 'يجب أن تكون كمية :product أكبر من صفر.',
    'warehouse_inactive' => 'المخزن موقوف أو غير موجود.',
    'same_warehouse' => 'اختر مخزناً آخر للتحويل إليه.',
    'insufficient' => 'رصيد :product في :warehouse غير كافٍ: المتاح :available والمطلوب :requested.',
    'batch_required' => 'أدخل رقم التشغيلة للصنف :product.',
    'batch_unknown' => 'التشغيلة :batch غير موجودة.',
    'serial_count' => 'الصنف :product يحتاج :quantity رقم مسلسل بالضبط.',
    'serial_in_stock' => 'الرقم المسلسل :serial موجود بالفعل في المخزن.',
    'serial_not_available' => 'الرقم المسلسل :serial غير موجود في هذا المخزن.',
];
