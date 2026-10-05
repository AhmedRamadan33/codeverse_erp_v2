<?php

return [
    'not_found' => 'الموديول :module غير موجود.',
    'requires_missing' => 'الموديول :module يتطلب تفعيل :required أولاً.',
    'required_by' => 'لا يمكن تعطيل الموديول :module لأن :dependent يعتمد عليه.',
    'cannot_disable_required' => 'الموديول :module جزء أساسي من النظام ولا يمكن تعطيله.',
    'circular_dependency' => 'يوجد اعتماد دائري بين الموديولات عند :module.',
    'enabled' => 'تم تفعيل الموديول :module.',
    'disabled' => 'تم تعطيل الموديول :module.',
    'upgraded' => 'تم تحديث الموديول :module من :from إلى :to.',
    'nothing_to_upgrade' => 'كل الموديولات محدثة.',
    'already_installed' => 'تم إعداد هذا النظام من قبل.',
    'experimental' => 'الموديول :module تجريبي وغير متاح على هذا النظام بعد.',
    'experimental_badge' => 'تجريبي، غير متاح بعد',
    'fields' => [
        'name' => 'الموديول',
        'requires' => 'يعتمد على',
        'version' => 'الإصدار',
    ],
    'status' => [
        'enabled' => 'مفعّل',
        'disabled' => 'معطّل',
        'not_installed' => 'غير مثبت',
    ],
    'enable' => 'تفعيل',
    'disable' => 'تعطيل',
    'always_enabled' => 'جزء أساسي من النظام',
    'upgrade_pending' => 'المثبت :version؛ شغّل erp:upgrade',
    'confirm_disable' => 'تعطيل هذا الموديول؟ بياناته محفوظة ويمكن تفعيله مرة أخرى.',
];
