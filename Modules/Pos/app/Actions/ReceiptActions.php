<?php

namespace Modules\Pos\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Mappings\AccountResolver;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Posting\EntryBuilder;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Accounting\Pricing\Discount;
use Modules\Accounting\Pricing\DocumentTotals;
use Modules\Accounting\Pricing\PricedLine;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Models\Partner;
use Modules\Core\Sequences\NextNumber;
use Modules\Core\Settings\Settings;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Stock\Actions\IssueStock;
use Modules\Inventory\Stock\Actions\ReceiveStock;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Pos\Enums\ReceiptKind;
use Modules\Pos\Enums\ShiftStatus;
use Modules\Pos\Events\PosReceiptCompleted;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Models\ReceiptLine;
use Modules\Pos\Models\Shift;
use Modules\Products\Models\Product;
use Modules\Products\Support\UnitConverter;
use Modules\Sales\Pricing\CreditLimit;
use Modules\Sales\Pricing\PriceResolver;

/**
 * POS receipts (core-design.md §7.1). A receipt is posted when it is completed:
 * - paid: stock leaves with its valuation deferred; the shift close posts the money;
 * - credit (named customer, paid less than the total): its own entry and valuation at once;
 * - return: against a sale receipt, netted in the current shift unless the sale was on credit.
 */
class ReceiptActions
{
    public function __construct(
        private readonly DocumentTotals $totals,
        private readonly UnitConverter $converter,
        private readonly PriceResolver $prices,
        private readonly CreditLimit $creditLimit,
        private readonly Currencies $currencies,
        private readonly Settings $settings,
        private readonly AccountResolver $accounts,
        private readonly PostJournalEntry $postEntry,
        private readonly IssueStock $issueStock,
        private readonly ReceiveStock $receiveStock,
        private readonly NextNumber $numbers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function saleRules(): array
    {
        return [
            'partner_id' => ['nullable', 'integer', Rule::exists('partners', 'id')->where('is_customer', true)->where('is_active', true)],
            'discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', 'decimal:0,4', 'min:0'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'lines.*.unit_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999999'],
            // Empty = the price list / product price; a different price needs pos.prices.override.
            'lines.*.unit_price' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'lines.*.discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'lines.*.discount_value' => ['nullable', 'decimal:0,4', 'min:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:64'],
            'lines.*.serials' => ['nullable', 'array'],
            'lines.*.serials.*' => ['string', 'max:128'],
            'payments' => ['array', 'max:10'],
            'payments.*.payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payments.*.amount' => ['required', 'decimal:0,4', 'gt:0'],
            'confirm_over_limit' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function returnRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.original_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'lines.*.serials' => ['nullable', 'array'],
            'lines.*.serials.*' => ['string', 'max:128'],
            // Empty = the whole refund by refund_method_id, else in cash from the drawer.
            'refund_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payments' => ['array', 'max:10'],
            'payments.*.payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'payments.*.amount' => ['required', 'decimal:0,4', 'gt:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against saleRules()
     */
    public function sell(User $actor, array $data): Receipt
    {
        Gate::forUser($actor)->authorize('pos.terminal.sell');

        $receipt = DB::transaction(function () use ($actor, $data) {
            $shift = $this->lockOpenShift($actor);
            $register = $shift->register;
            $scale = $this->currencies->base()->decimal_places;

            $walkInId = (int) $this->settings->get('sales.walk_in_partner_id');
            $customer = Partner::visibleTo($actor)->find($data['partner_id'] ?? $walkInId)
                ?? throw ValidationException::withMessages(['partner_id' => __('pos::receipts.customer_not_allowed')]);
            $priceListId = $this->prices->priceListFor($customer->id) ?? $register->price_list_id;

            $lines = array_values($data['lines']);
            $products = Product::with(['units', 'saleTax'])->whereKey(array_column($lines, 'product_id'))->get()->keyBy('id');
            $discounted = ($data['discount_value'] ?? '') !== '' && ! BigDecimal::of((string) $data['discount_value'])->isZero();

            foreach ($lines as $i => &$line) {
                $product = $products[$line['product_id']];
                $listed = $this->prices->price($product, (int) $line['unit_id'], $priceListId);

                if (($line['unit_price'] ?? '') === '' || $line['unit_price'] === null) {
                    $line['unit_price'] = (string) $listed;
                } elseif (! BigDecimal::of((string) $line['unit_price'])->isEqualTo($listed) && ! $actor->can('pos.prices.override')) {
                    throw ValidationException::withMessages(["lines.{$i}.unit_price" => __('pos::receipts.price_override_denied')]);
                }

                $discounted = $discounted || (($line['discount_value'] ?? '') !== '' && ! BigDecimal::of((string) $line['discount_value'])->isZero());
            }
            unset($line);

            if ($discounted && ! $actor->can('pos.discounts.give')) {
                throw ValidationException::withMessages(['discount_value' => __('pos::receipts.discount_denied')]);
            }

            $result = $this->totals->calculate(array_map(fn ($line) => new PricedLine(
                (string) $line['quantity'],
                (string) $line['unit_price'],
                Discount::fromInput($line['discount_type'] ?? null, $line['discount_value'] ?? null),
                $products[$line['product_id']]->saleTax,
            ), $lines), $scale, Discount::fromInput($data['discount_type'] ?? null, $data['discount_value'] ?? null));

            $total = $result->total();
            [$payments, $paid, $change] = $this->applyPayments($data['payments'] ?? [], $total, $register->cash_payment_method_id);
            $isCredit = $paid->isLessThan($total);

            if ($isCredit) {
                if ($customer->id === $walkInId) {
                    throw ValidationException::withMessages(['payments' => __('pos::receipts.underpaid')]);
                }

                // Lock the customer so two credit sales at once cannot both pass the limit (§3.4).
                Partner::whereKey($customer->id)->lockForUpdate()->first();
                $this->creditLimit->check($actor, $customer, $total->minus($paid), $shift->branch_id, (bool) ($data['confirm_over_limit'] ?? false));
            }

            $today = CarbonImmutable::today();
            $receipt = Receipt::create([
                'shift_id' => $shift->id,
                'register_id' => $register->id,
                'branch_id' => $shift->branch_id,
                'warehouse_id' => $register->warehouse_id,
                'partner_id' => $customer->id,
                'kind' => ReceiptKind::Sale,
                'date' => $today,
                'price_list_id' => $priceListId,
                'discount_type' => ($data['discount_value'] ?? '') !== '' ? ($data['discount_type'] ?? 'amount') : null,
                'discount_value' => ($data['discount_value'] ?? '') !== '' ? (string) $data['discount_value'] : null,
                'subtotal' => $result->subtotal()->toScale(4),
                'discount_total' => $result->discountTotal()->toScale(4),
                'tax_total' => $result->taxTotal()->toScale(4),
                'total' => $total->toScale(4),
                'paid_total' => $paid->toScale(4),
                'tendered' => $change->isPositive() ? $paid->plus($change)->toScale(4) : null,
                'change' => $change->toScale(4),
                'is_credit' => $isCredit,
                'due_date' => $isCredit ? $today->addDays($customer->payment_term_days) : null,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $i => $line) {
                $product = $products[$line['product_id']];
                $totals = $result->lines[$i];
                $tax = $product->saleTax;

                $receipt->lines()->create([
                    'line_no' => $i + 1,
                    'product_id' => $product->id,
                    'unit_id' => $line['unit_id'],
                    'quantity' => (string) $line['quantity'],
                    'base_quantity' => $this->converter->toBase($product, (int) $line['unit_id'], (string) $line['quantity']),
                    'unit_price' => (string) $line['unit_price'],
                    'discount_type' => ($line['discount_value'] ?? '') !== '' ? ($line['discount_type'] ?? 'amount') : null,
                    'discount_value' => ($line['discount_value'] ?? '') !== '' ? (string) $line['discount_value'] : null,
                    'gross' => $totals->gross->toScale(4),
                    'line_discount' => $totals->lineDiscount->toScale(4),
                    'document_discount' => $totals->documentDiscount->toScale(4),
                    'net' => $totals->net->toScale(4),
                    'tax_id' => $tax?->id,
                    'tax_rate' => $tax?->rate,
                    'tax_amount' => $totals->tax->toScale(4),
                    'line_total' => $totals->total->toScale(4),
                    'batch_number' => $line['batch_number'] ?? null,
                    'serials' => array_values(array_filter($line['serials'] ?? [])) ?: null,
                ]);
            }

            $receipt->payments()->createMany($payments);
            $receipt->load(['lines.product.category', 'lines.tax', 'partner', 'branch', 'payments']);

            $this->moveStock($receipt, $actor);

            if ($isCredit) {
                $this->postCreditEntry($receipt, $actor);
            }

            $receipt->update(['number' => $this->numbers->handle(Receipt::SEQUENCE, $receipt->branch_id, $today)]);

            return $receipt;
        });

        PosReceiptCompleted::dispatch($receipt);

        return $receipt;
    }

    /**
     * @param  array<string, mixed>  $data  validated against returnRules()
     */
    public function return(User $actor, Receipt $original, array $data): Receipt
    {
        Gate::forUser($actor)->authorize('pos.returns.create');

        $receipt = DB::transaction(function () use ($actor, $original, $data) {
            $shift = $this->lockOpenShift($actor);
            // Locks the sale so two returns of it cannot both take the same quantity.
            $original = Receipt::whereKey($original->id)->lockForUpdate()->firstOrFail();

            if ($original->kind !== ReceiptKind::Sale || ! $actor->canAccessBranch($original->branch_id)) {
                throw ValidationException::withMessages(['receipt' => __('pos::receipts.not_returnable')]);
            }

            $originalLines = $original->lines()->get()->keyBy('id');
            $scale = $this->currencies->base()->decimal_places;
            $rows = [];

            foreach (array_values($data['lines']) as $i => $input) {
                /** @var ReceiptLine|null $line */
                $line = $originalLines[$input['original_line_id']] ?? null;
                if ($line === null) {
                    throw ValidationException::withMessages(["lines.{$i}.original_line_id" => __('pos::receipts.line_not_on_receipt')]);
                }

                $quantity = BigDecimal::of((string) $input['quantity'])->toScale(4);
                $returnable = $line->returnableQuantity();
                if ($quantity->isGreaterThan($returnable)) {
                    throw ValidationException::withMessages(["lines.{$i}.quantity" => __('pos::receipts.too_much', [
                        'product' => $line->product->name, 'returnable' => (string) $returnable->strippedOfTrailingZeros(),
                    ])]);
                }

                $share = fn (BigDecimal $amount, int $s) => $amount->multipliedBy($quantity)->dividedBy($line->quantity, $s, RoundingMode::HalfUp);

                if ($quantity->isEqualTo($returnable)) {
                    // The last return of a line takes exactly what is left.
                    $done = $line->returnLines();
                    $net = $line->net->minus(BigDecimal::of((clone $done)->sum('net') ?: 0));
                    $tax = $line->tax_amount->minus(BigDecimal::of((clone $done)->sum('tax_amount') ?: 0));
                    $cost = $line->cost?->minus(BigDecimal::of((clone $done)->sum('cost') ?: 0));
                    $base = $line->base_quantity->minus(BigDecimal::of((clone $done)->sum('base_quantity') ?: 0));
                } else {
                    $net = $share($line->net, $scale);
                    $tax = $share($line->tax_amount, $scale);
                    $cost = $line->cost ? $share($line->cost, 4) : null;
                    $base = $share($line->base_quantity, 4);
                }

                $rows[] = [
                    'line_no' => $i + 1,
                    'original_line_id' => $line->id,
                    'product_id' => $line->product_id,
                    'unit_id' => $line->unit_id,
                    'quantity' => $quantity,
                    'base_quantity' => $base->toScale(4),
                    'unit_price' => $line->unit_price,
                    'gross' => $net->toScale(4),
                    'net' => $net->toScale(4),
                    'tax_id' => $line->tax_id,
                    'tax_rate' => $line->tax_rate,
                    'tax_amount' => $tax->toScale(4),
                    'line_total' => $net->plus($tax)->toScale(4),
                    'batch_number' => $line->batch_number,
                    'serials' => array_values(array_filter($input['serials'] ?? [])) ?: null,
                    'cost' => $cost?->toScale(4),
                ];
            }

            $sum = fn (string $key) => array_reduce($rows, fn (BigDecimal $c, $r) => $c->plus($r[$key]), BigDecimal::zero());
            $total = $sum('line_total');

            // A credit sale is returned against the customer's balance; a paid one is refunded.
            $payments = [];
            if (! $original->is_credit) {
                $payments = ($data['payments'] ?? []) ?: [[
                    'payment_method_id' => $data['refund_method_id'] ?? $shift->register->cash_payment_method_id, 'amount' => (string) $total,
                ]];
                $refunded = array_reduce($payments, fn (BigDecimal $c, $p) => $c->plus((string) $p['amount']), BigDecimal::zero());
                if (! $refunded->isEqualTo($total)) {
                    throw ValidationException::withMessages(['payments' => __('pos::receipts.refund_mismatch', ['total' => (string) $total->toScale($scale)])]);
                }
            }

            $today = CarbonImmutable::today();
            $receipt = Receipt::create([
                'shift_id' => $shift->id,
                'register_id' => $shift->register_id,
                'branch_id' => $shift->branch_id,
                'warehouse_id' => $shift->register->warehouse_id,
                'partner_id' => $original->partner_id,
                'kind' => ReceiptKind::Return,
                'original_receipt_id' => $original->id,
                'date' => $today,
                'subtotal' => $sum('net')->toScale(4),
                'discount_total' => '0',
                'tax_total' => $sum('tax_amount')->toScale(4),
                'total' => $total->toScale(4),
                'paid_total' => $original->is_credit ? '0' : $total->toScale(4),
                'change' => '0',
                'is_credit' => $original->is_credit,
                'created_by' => $actor->id,
            ]);

            $receipt->lines()->createMany($rows);
            $receipt->payments()->createMany(array_map(fn ($p) => [
                'payment_method_id' => $p['payment_method_id'], 'amount' => BigDecimal::of((string) $p['amount'])->toScale(4),
            ], $payments));
            $receipt->load(['lines.product.category', 'lines.tax', 'partner', 'branch', 'payments']);

            $this->moveStock($receipt, $actor);

            if ($receipt->is_credit) {
                $this->postCreditEntry($receipt, $actor);
            }

            $receipt->update(['number' => $this->numbers->handle(Receipt::SEQUENCE, $receipt->branch_id, $today)]);

            return $receipt;
        });

        PosReceiptCompleted::dispatch($receipt);

        return $receipt;
    }

    private function lockOpenShift(User $actor): Shift
    {
        $shift = Shift::where('user_id', $actor->id)->where('status', ShiftStatus::Open)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['shift' => __('pos::receipts.no_open_shift')]);

        return $shift->load('register');
    }

    /**
     * Payments above the total are change, given back from the cash in the drawer.
     *
     * @param  array<int, array{payment_method_id: int, amount: string}>  $input
     * @return array{0: array<int, array<string, mixed>>, 1: BigDecimal, 2: BigDecimal} rows, paid, change
     */
    private function applyPayments(array $input, BigDecimal $total, int $cashMethodId): array
    {
        $rows = [];
        $paid = BigDecimal::zero();
        foreach ($input as $payment) {
            $amount = BigDecimal::of((string) $payment['amount'])->toScale(4);
            $rows[] = ['payment_method_id' => (int) $payment['payment_method_id'], 'amount' => $amount];
            $paid = $paid->plus($amount);
        }

        $change = $paid->minus($total);
        if (! $change->isPositive()) {
            return [$rows, $paid, BigDecimal::zero()];
        }

        $cash = array_reduce($rows, fn (BigDecimal $c, $r) => $r['payment_method_id'] === $cashMethodId ? $c->plus($r['amount']) : $c, BigDecimal::zero());
        if ($change->isGreaterThan($cash)) {
            throw ValidationException::withMessages(['payments' => __('pos::receipts.overpaid')]);
        }

        // Keep only what was applied: take the change off the cash rows.
        $left = $change;
        foreach ($rows as $i => $row) {
            if ($row['payment_method_id'] !== $cashMethodId || $left->isZero()) {
                continue;
            }
            $take = $row['amount']->isLessThan($left) ? $row['amount'] : $left;
            $rows[$i]['amount'] = $row['amount']->minus($take);
            $left = $left->minus($take);
        }

        return [array_values(array_filter($rows, fn ($r) => $r['amount']->isPositive())), $total, $change];
    }

    /**
     * Sales leave stock, returns bring it back at the sale's cost. Valuation waits for the
     * shift close, except on credit receipts which post at once.
     */
    private function moveStock(Receipt $receipt, User $actor): void
    {
        $lines = $receipt->lines->filter(fn (ReceiptLine $l) => $l->product->tracksStock())->values();
        if ($lines->isEmpty()) {
            return;
        }

        $op = new StockOperationData(
            date: $receipt->date,
            branchId: $receipt->branch_id,
            type: $receipt->isReturn() ? StockMoveType::SaleReturn : StockMoveType::Sale,
            source: $receipt,
            lines: $lines->map(fn (ReceiptLine $l) => new StockLineData(
                productId: $l->product_id,
                warehouseId: $receipt->warehouse_id,
                quantity: $l->base_quantity,
                batchNumber: $l->batch_number,
                serials: $l->serials ?? [],
                sourceLine: $l,
                totalCost: $receipt->isReturn() ? ($l->cost ?? '0') : null,
            ))->all(),
            counterAccountKey: 'inventory.cogs',
            counterScopes: [$receipt->partner],
            description: __('pos::receipts.entry_description', ['customer' => $receipt->partner->name]),
            postedBy: $actor,
            deferValuation: ! $receipt->is_credit,
        );

        if ($receipt->isReturn()) {
            $this->receiveStock->handle($op);

            return;
        }

        $result = $this->issueStock->handle($op);
        foreach ($lines as $i => $line) {
            $line->update(['cost' => $result->lineCost($i)]);
        }
    }

    /**
     * A credit sale: Dr payments / Dr customer (due date) / Cr revenue / Cr output tax.
     * Its return: Dr sales returns / Dr output tax / Cr customer.
     */
    private function postCreditEntry(Receipt $receipt, User $actor): void
    {
        $sale = ! $receipt->isReturn();
        $entry = new EntryBuilder;
        $methodAccounts = PaymentMethod::pluck('account_id', 'id');

        foreach ($receipt->lines as $line) {
            $product = $line->product;
            $revenue = $this->accounts->resolve($sale ? 'sales.revenue' : 'sales.returns', [$product, $product->category, $receipt->partner, $receipt->branch])->id;
            $sale ? $entry->credit($revenue, $line->net) : $entry->debit($revenue, $line->net);

            if ($line->tax_amount->isPositive()) {
                $tax = $this->accounts->resolve('tax.output', [$line->tax, $receipt->branch])->id;
                $sale ? $entry->credit($tax, $line->tax_amount) : $entry->debit($tax, $line->tax_amount);
            }
        }

        foreach ($receipt->payments as $payment) {
            $entry->debit($methodAccounts[$payment->payment_method_id], $payment->amount);
        }

        $receivable = $this->accounts->resolve('sales.receivable', [$receipt->partner, $receipt->branch])->id;
        $owed = $receipt->total->minus($receipt->paid_total);
        $sale
            ? $entry->debit($receivable, $owed, null, $receipt->partner_id, $receipt->due_date)
            : $entry->credit($receivable, $owed, null, $receipt->partner_id);

        $journal = $this->postEntry->handle(new JournalEntryData(
            date: $receipt->date,
            branchId: $receipt->branch_id,
            journalType: JournalType::Sales,
            lines: $entry->lines(),
            description: __('pos::receipts.entry_description', ['customer' => $receipt->partner->name]),
            source: $receipt,
            postedBy: $actor,
        ));

        $receipt->update(['journal_entry_id' => $journal->id]);
    }
}
