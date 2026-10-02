<?php

namespace Modules\Accounting\Models;

use Modules\Accounting\Vouchers\VoucherKind;

class PaymentVoucher extends CashVoucher
{
    protected $table = 'payment_vouchers';

    public function kind(): VoucherKind
    {
        return VoucherKind::Payment;
    }
}
