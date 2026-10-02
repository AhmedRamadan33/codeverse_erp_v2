<?php

namespace Modules\Accounting\Models;

use Modules\Accounting\Vouchers\VoucherKind;

class ReceiptVoucher extends CashVoucher
{
    protected $table = 'receipt_vouchers';

    public function kind(): VoucherKind
    {
        return VoucherKind::Receipt;
    }
}
