<?php

namespace Modules\Accounting\Enums;

enum PaymentMethodType: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Wallet = 'wallet';
    case Cheque = 'cheque';

    public function label(): string
    {
        return __('accounting::payment_methods.types.'.$this->value);
    }
}
