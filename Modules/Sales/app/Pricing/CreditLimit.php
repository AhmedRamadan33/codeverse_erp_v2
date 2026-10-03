<?php

namespace Modules\Sales\Pricing;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Ledger\Ledger;
use Modules\Core\Models\Partner;
use Modules\Core\Settings\Settings;

/**
 * Credit-limit check when posting a credit sale (core-design.md §5.2).
 * Mode "block" refuses unless the user may override; "warn" needs an explicit confirmation.
 */
class CreditLimit
{
    public const MODE_BLOCK = 'block';

    public const MODE_WARN = 'warn';

    public const MODE_OFF = 'off';

    public function __construct(
        private readonly Settings $settings,
        private readonly Ledger $ledger,
    ) {}

    /**
     * @param  BigDecimal  $newDebt  base-currency amount the posting adds to the customer's balance
     */
    public function check(User $actor, Partner $customer, BigDecimal $newDebt, int $branchId, bool $confirmed): void
    {
        $mode = $this->settings->get('sales.credit_limit_mode', $branchId);

        if ($mode === self::MODE_OFF || $customer->credit_limit === null || ! $newDebt->isPositive()) {
            return;
        }

        $balance = $this->ledger->partnerBalance($customer, AccountSubtype::Receivable);
        $after = $balance->plus($newDebt);

        if (! $after->isGreaterThan($customer->credit_limit)) {
            return;
        }

        $replace = ['customer' => $customer->name, 'limit' => (string) $customer->credit_limit->toScale(2), 'balance' => (string) $after->toScale(2)];

        if ($mode === self::MODE_BLOCK && ! $actor->can('sales.credit_limit.override')) {
            throw ValidationException::withMessages(['credit_limit' => __('sales::invoices.credit_limit_blocked', $replace)]);
        }

        if (! $confirmed) {
            throw ValidationException::withMessages(['credit_limit_confirm' => __('sales::invoices.credit_limit_warning', $replace)]);
        }
    }
}
