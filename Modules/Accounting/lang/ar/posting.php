<?php

return [
    'too_few_lines' => 'القيد يحتاج سطرين على الأقل.',
    'negative_amount' => 'السطر :line: لا يمكن أن تكون المبالغ سالبة.',
    'one_side_per_line' => 'السطر :line: أدخل مبلغاً مديناً أو دائناً فقط.',
    'too_many_decimals' => 'السطر :line: الحد الأقصى 4 خانات عشرية.',
    'account_not_postable' => 'السطر :line: الحساب :account موقوف أو حساب رئيسي.',
    'partner_required' => 'السطر :line: الحساب :account يحتاج عميل أو مورد.',
    'account_currency' => 'السطر :line: الحساب :account يقبل عملته فقط.',
    'amount_currency_required' => 'السطر :line: أدخل المبلغ بالعملة الأجنبية.',
    'unbalanced' => 'القيد غير متوازن: مدين :debit، دائن :credit.',
    'no_fiscal_period' => 'لا توجد فترة مالية لتاريخ :date. أنشئ السنة المالية أولاً.',
    'period_closed' => 'الفترة المالية لتاريخ :date مغلقة.',
    'before_lock_date' => 'القيود مقفلة حتى تاريخ :date.',
    'missing_mapping' => 'لم يتم تحديد حساب لـ ":key". حدده من ربط الحسابات.',
    'reverse_draft' => 'يمكن عكس القيود المرحّلة فقط.',
    'already_posted' => 'القيد :number مرحّل بالفعل.',
    'already_reversed' => 'القيد :number تم عكسه من قبل أو هو نفسه قيد عكسي.',
    'reversal_before_original' => 'لا يمكن أن يسبق تاريخ القيد العكسي تاريخ القيد الأصلي (:date).',
    'reconcile_mismatch' => 'يمكن الربط فقط بين حركات نفس العميل/المورد ونفس الحساب.',
    'reconcile_sides' => 'اربط حركة مدينة مرحّلة بحركة دائنة مرحّلة.',
    'reconcile_amount' => 'المبلغ :amount أكبر من المتبقي المفتوح.',
];
