<?php

return [
    'title' => 'مرتجعات المشتريات',
    'one' => 'مرتجع مشتريات',
    'new' => 'مرتجع للمورد',
    'fields' => [
        'invoice' => 'الفاتورة',
        'invoiced' => 'المشترى',
        'returnable' => 'المتاح للرد',
        'quantity' => 'كمية المرتجع',
        'stock_cost' => 'تكلفة المخزون',
    ],
    'post' => 'ترحيل',
    'cancel' => 'إلغاء المرتجع',
    'posted' => 'تم ترحيل المرتجع :number.',
    'cancelled' => 'تم إلغاء المرتجع.',
    'entry_description' => 'مرتجع إلى :supplier',
    'invoice_not_posted' => 'يمكن عمل مرتجع للفواتير المرحّلة فقط.',
    'before_invoice' => 'لا يمكن أن يسبق تاريخ المرتجع تاريخ الفاتورة.',
    'line_not_on_invoice' => 'هذا السطر ليس على الفاتورة.',
    'too_much' => 'المتاح للرد من :product هو :returnable فقط.',
];
