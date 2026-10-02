<?php

namespace Modules\Accounting\Enums;

/**
 * Finer classification used by documents and reports (which accounts are cash boxes,
 * which hold customer balances, ...). Each subtype belongs to one account type.
 */
enum AccountSubtype: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Receivable = 'receivable';
    case Inventory = 'inventory';
    case TaxAsset = 'tax_asset';
    case CurrentAsset = 'current_asset';
    case FixedAsset = 'fixed_asset';
    case Payable = 'payable';
    case TaxLiability = 'tax_liability';
    case CurrentLiability = 'current_liability';
    case LongTermLiability = 'long_term_liability';
    case Capital = 'capital';
    case RetainedEarnings = 'retained_earnings';
    case OtherEquity = 'other_equity';
    case Revenue = 'revenue';
    case OtherIncome = 'other_income';
    case CostOfSales = 'cost_of_sales';
    case OperatingExpense = 'operating_expense';
    case OtherExpense = 'other_expense';

    public function type(): AccountType
    {
        return match ($this) {
            self::Cash, self::Bank, self::Receivable, self::Inventory, self::TaxAsset,
            self::CurrentAsset, self::FixedAsset => AccountType::Asset,
            self::Payable, self::TaxLiability, self::CurrentLiability, self::LongTermLiability => AccountType::Liability,
            self::Capital, self::RetainedEarnings, self::OtherEquity => AccountType::Equity,
            self::Revenue, self::OtherIncome => AccountType::Income,
            self::CostOfSales, self::OperatingExpense, self::OtherExpense => AccountType::Expense,
        };
    }

    /**
     * Customer and supplier balances live on these accounts, so every line needs a partner.
     */
    public function requiresPartner(): bool
    {
        return in_array($this, [self::Receivable, self::Payable], true);
    }

    /**
     * @return self[]
     */
    public static function forType(AccountType $type): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->type() === $type));
    }

    public function label(): string
    {
        return __('accounting::accounts.subtypes.'.$this->value);
    }
}
