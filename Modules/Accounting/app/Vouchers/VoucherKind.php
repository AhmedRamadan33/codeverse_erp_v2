<?php

namespace Modules\Accounting\Vouchers;

use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Models\CashVoucher;
use Modules\Accounting\Models\PaymentVoucher;
use Modules\Accounting\Models\ReceiptVoucher;

/**
 * Receipts bring money in from a partner (Dr cash/bank, Cr receivable);
 * payments send money out to a partner (Dr payable, Cr cash/bank).
 */
enum VoucherKind: string
{
    case Receipt = 'receipt';
    case Payment = 'payment';

    /**
     * @return class-string<CashVoucher>
     */
    public function model(): string
    {
        return match ($this) {
            self::Receipt => ReceiptVoucher::class,
            self::Payment => PaymentVoucher::class,
        };
    }

    public function sequenceKey(): string
    {
        return 'accounting.'.$this->value.'_voucher';
    }

    public function sequencePrefix(): string
    {
        return match ($this) {
            self::Receipt => 'RV-{yyyy}-',
            self::Payment => 'PV-{yyyy}-',
        };
    }

    /**
     * Account mapping of the partner side.
     */
    public function partnerMappingKey(): string
    {
        return match ($this) {
            self::Receipt => 'sales.receivable',
            self::Payment => 'purchases.payable',
        };
    }

    public function partnerSubtype(): AccountSubtype
    {
        return match ($this) {
            self::Receipt => AccountSubtype::Receivable,
            self::Payment => AccountSubtype::Payable,
        };
    }

    /**
     * Whether the partner line of this voucher is a credit (it settles debit lines such as invoices).
     */
    public function partnerLineIsCredit(): bool
    {
        return $this === self::Receipt;
    }

    public function routePrefix(): string
    {
        return 'accounting.'.$this->value.'s.';
    }

    public function label(): string
    {
        return __('accounting::vouchers.kinds.'.$this->value);
    }
}
