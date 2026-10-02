<?php

namespace Modules\Accounting\Expenses\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Mappings\AccountResolver;
use Modules\Accounting\Models\ExpenseVoucher;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\JournalLineData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Accounting\Posting\ReverseJournalEntry;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Sequences\NextNumber;

/**
 * Dr each expense line (net) and the input tax, Cr the payment method's cash/bank account.
 */
class PostExpenseVoucher
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly ReverseJournalEntry $reverse,
        private readonly AccountResolver $accounts,
        private readonly Currencies $currencies,
        private readonly NextNumber $numbers,
    ) {}

    public function handle(User $actor, ExpenseVoucher $voucher): ExpenseVoucher
    {
        Gate::forUser($actor)->authorize('accounting.vouchers.post');

        return DB::transaction(function () use ($actor, $voucher) {
            $voucher = ExpenseVoucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

            if ($voucher->status !== DocumentStatus::Draft) {
                throw ValidationException::withMessages(['voucher' => __('core::documents.not_draft')]);
            }

            $voucher->load(['lines.tax', 'paymentMethod.account', 'branch']);
            $base = $this->currencies->base();
            $isForeign = $voucher->currency_id !== $base->id;
            $toBase = fn (BigDecimal $amount) => $amount->multipliedBy($voucher->exchange_rate)->toScale($base->decimal_places, RoundingMode::HalfUp);
            $foreign = fn (BigDecimal $amount) => $isForeign ? ['currencyId' => $voucher->currency_id, 'amountCurrency' => $amount] : [];

            $lines = [];
            $credit = BigDecimal::zero();

            foreach ($voucher->lines as $line) {
                $lines[] = new JournalLineData(...$foreign($line->amount), accountId: $line->account_id, debit: $toBase($line->amount), description: $line->description ?? $voucher->description);
                $credit = $credit->plus($toBase($line->amount));

                if ($line->tax_amount->isPositive()) {
                    $taxAccount = $this->accounts->resolve('tax.input', [$line->tax, $voucher->branch]);
                    $lines[] = new JournalLineData(...$foreign($line->tax_amount), accountId: $taxAccount->id, debit: $toBase($line->tax_amount), description: $line->tax->name);
                    $credit = $credit->plus($toBase($line->tax_amount));
                }
            }

            // Converting each line separately can differ from converting the total; the cash
            // line uses the sum of converted lines so the entry always balances.
            $lines[] = new JournalLineData(...$foreign($voucher->total->negated()), accountId: $voucher->paymentMethod->account_id, credit: $credit, description: $voucher->description);

            $entry = $this->post->handle(new JournalEntryData(
                date: $voucher->date,
                branchId: $voucher->branch_id,
                journalType: $voucher->paymentMethod->account->subtype === AccountSubtype::Bank ? JournalType::Bank : JournalType::Cash,
                lines: $lines,
                description: $voucher->description ?? __('accounting::expenses.title'),
                source: $voucher,
                postedBy: $actor,
            ));

            $voucher->update([
                'status' => DocumentStatus::Posted,
                'journal_entry_id' => $entry->id,
                'number' => $this->numbers->handle(ExpenseVoucher::SEQUENCE, $voucher->branch_id, $voucher->date),
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            return $voucher;
        });
    }

    public function cancel(User $actor, ExpenseVoucher $voucher, string $reason): ExpenseVoucher
    {
        Gate::forUser($actor)->authorize('accounting.vouchers.cancel');

        return DB::transaction(function () use ($actor, $voucher, $reason) {
            $voucher = ExpenseVoucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

            if ($voucher->status !== DocumentStatus::Posted) {
                throw ValidationException::withMessages(['voucher' => __('core::documents.not_posted')]);
            }

            $this->reverse->handle($voucher->journalEntry, CarbonImmutable::today()->max($voucher->date), $reason, $actor);
            $voucher->update(['status' => DocumentStatus::Cancelled, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

            return $voucher;
        });
    }
}
