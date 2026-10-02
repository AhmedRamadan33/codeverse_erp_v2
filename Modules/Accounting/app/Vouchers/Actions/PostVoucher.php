<?php

namespace Modules\Accounting\Vouchers\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Mappings\AccountResolver;
use Modules\Accounting\Models\CashVoucher;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\JournalLineData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Accounting\Vouchers\VoucherKind;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Sequences\NextNumber;

/**
 * Posts a draft voucher: one journal entry between the payment method's cash/bank account
 * and the partner's receivable/payable account, then optional allocations to open items.
 */
class PostVoucher
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly AccountResolver $accounts,
        private readonly Currencies $currencies,
        private readonly NextNumber $numbers,
        private readonly AllocateVoucher $allocate,
    ) {}

    /**
     * @param  array<int, string>  $allocations  journal line id => amount in base currency
     */
    public function handle(User $actor, CashVoucher $voucher, array $allocations = []): CashVoucher
    {
        Gate::forUser($actor)->authorize('accounting.vouchers.post');

        return DB::transaction(function () use ($actor, $voucher, $allocations) {
            $kind = $voucher->kind();
            $voucher = $kind->model()::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

            if ($voucher->status !== DocumentStatus::Draft) {
                throw ValidationException::withMessages(['voucher' => __('core::documents.not_draft')]);
            }

            $voucher->load(['partner', 'paymentMethod.account', 'currency']);
            $base = $this->currencies->base();
            $isForeign = $voucher->currency_id !== $base->id;
            $baseAmount = $voucher->amount->multipliedBy($voucher->exchange_rate)->toScale($base->decimal_places, RoundingMode::HalfUp);

            $cashAccount = $voucher->paymentMethod->account;
            $partnerAccount = $this->accounts->resolve($kind->partnerMappingKey(), [$voucher->partner, $voucher->branch]);

            $foreign = fn (bool $positive) => $isForeign
                ? ['currencyId' => $voucher->currency_id, 'amountCurrency' => $positive ? $voucher->amount : $voucher->amount->negated()]
                : [];

            $cashLine = ['accountId' => $cashAccount->id, 'description' => $voucher->description, ...$foreign($kind === VoucherKind::Receipt)];
            $partnerLine = ['accountId' => $partnerAccount->id, 'partnerId' => $voucher->partner_id, 'description' => $voucher->description, ...$foreign($kind === VoucherKind::Payment)];

            $lines = $kind === VoucherKind::Receipt
                ? [new JournalLineData(...$cashLine, debit: $baseAmount), new JournalLineData(...$partnerLine, credit: $baseAmount)]
                : [new JournalLineData(...$partnerLine, debit: $baseAmount), new JournalLineData(...$cashLine, credit: $baseAmount)];

            $entry = $this->post->handle(new JournalEntryData(
                date: $voucher->date,
                branchId: $voucher->branch_id,
                journalType: $cashAccount->subtype === AccountSubtype::Bank ? JournalType::Bank : JournalType::Cash,
                lines: $lines,
                description: $voucher->description ?? $kind->label().' — '.$voucher->partner->name,
                source: $voucher,
                postedBy: $actor,
            ));

            $voucher->update([
                'status' => DocumentStatus::Posted,
                'journal_entry_id' => $entry->id,
                'number' => $this->numbers->handle($kind->sequenceKey(), $voucher->branch_id, $voucher->date),
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            if ($allocations !== []) {
                $this->allocate->apply($actor, $voucher, $allocations);
            }

            return $voucher;
        });
    }
}
