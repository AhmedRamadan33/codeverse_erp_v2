<?php

namespace Modules\Accounting\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    /**
     * Whether a balance on this side increases the account (assets and expenses are debit-normal).
     */
    public function isDebitNormal(): bool
    {
        return in_array($this, [self::Asset, self::Expense], true);
    }

    /**
     * Income and expense balances are closed into retained earnings at year end.
     */
    public function isProfitAndLoss(): bool
    {
        return in_array($this, [self::Income, self::Expense], true);
    }

    public function label(): string
    {
        return __('accounting::accounts.types.'.$this->value);
    }
}
