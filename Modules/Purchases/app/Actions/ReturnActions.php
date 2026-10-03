<?php

namespace Modules\Purchases\Actions;

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
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Accounting\Posting\ReverseJournalEntry;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Sequences\NextNumber;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Stock\Actions\IssueStock;
use Modules\Inventory\Stock\Actions\ReverseStock;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Purchases\Models\PurchaseInvoice;
use Modules\Purchases\Models\PurchaseInvoiceLine;
use Modules\Purchases\Models\PurchaseReturn;
use Modules\Purchases\Posting\EntryBuilder;

/**
 * Goods sent back to the supplier, always against a posted invoice.
 * Dr supplier / Cr GRNI (at the stock cost that left) / Cr input tax; the difference between
 * the invoice price and the stock cost goes to inventory price differences (core-design.md §7).
 */
class ReturnActions
{
    public function __construct(
        private readonly Currencies $currencies,
        private readonly AccountResolver $accounts,
        private readonly PostJournalEntry $postEntry,
        private readonly ReverseJournalEntry $reverseEntry,
        private readonly IssueStock $issueStock,
        private readonly ReverseStock $reverseStock,
        private readonly Reconciler $reconciler,
        private readonly NextNumber $numbers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_invoice_line_id' => ['required', 'integer', 'distinct', Rule::exists('purchase_invoice_lines', 'id')],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'lines.*.serials' => ['nullable', 'array'],
            'lines.*.serials.*' => ['string', 'max:128'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function save(User $actor, PurchaseInvoice $invoice, array $data, ?PurchaseReturn $return = null): PurchaseReturn
    {
        Gate::forUser($actor)->authorize('purchases.returns.create');

        if ($invoice->status !== DocumentStatus::Posted) {
            throw ValidationException::withMessages(['invoice' => __('purchases::returns.invoice_not_posted')]);
        }

        if ($return && $return->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['return' => __('core::documents.not_draft')]);
        }

        if (CarbonImmutable::parse($data['date'])->lt($invoice->date)) {
            throw ValidationException::withMessages(['date' => __('purchases::returns.before_invoice')]);
        }

        $invoiceLines = $invoice->lines()->get()->keyBy('id');
        $scale = $invoice->currency->decimal_places;
        $rows = [];

        foreach (array_values($data['lines']) as $i => $input) {
            /** @var PurchaseInvoiceLine|null $line */
            $line = $invoiceLines[$input['purchase_invoice_line_id']] ?? null;
            if ($line === null) {
                throw ValidationException::withMessages(["lines.{$i}.purchase_invoice_line_id" => __('purchases::returns.line_not_on_invoice')]);
            }

            $quantity = BigDecimal::of((string) $input['quantity'])->toScale(4);
            $returnable = $line->returnableQuantity($return?->id);

            if ($quantity->isGreaterThan($returnable)) {
                throw ValidationException::withMessages(["lines.{$i}.quantity" => __('purchases::returns.too_much', [
                    'product' => $line->product->name, 'returnable' => (string) $returnable->strippedOfTrailingZeros(),
                ])]);
            }

            // The last return of a line takes exactly what is left of its amounts.
            if ($quantity->isEqualTo($returnable)) {
                $done = $line->returnLines()->whereHas('purchaseReturn', fn ($q) => $q->where('status', '!=', DocumentStatus::Cancelled)
                    ->when($return, fn ($q) => $q->whereKeyNot($return->id)));
                $net = $line->net->minus(BigDecimal::of($done->sum('net') ?: 0));
                $tax = $line->tax_amount->minus(BigDecimal::of((clone $done)->sum('tax_amount') ?: 0));
            } else {
                $net = $line->net->multipliedBy($quantity)->dividedBy($line->quantity, $scale, RoundingMode::HalfUp);
                $tax = $line->tax_amount->multipliedBy($quantity)->dividedBy($line->quantity, $scale, RoundingMode::HalfUp);
            }

            $rows[] = [
                'line_no' => $i + 1,
                'purchase_invoice_line_id' => $line->id,
                'product_id' => $line->product_id,
                'unit_id' => $line->unit_id,
                'quantity' => $quantity,
                'base_quantity' => $line->base_quantity->multipliedBy($quantity)->dividedBy($line->quantity, 4, RoundingMode::HalfUp),
                'net' => $net->toScale(4),
                'tax_id' => $line->tax_id,
                'tax_rate' => $line->tax_rate,
                'tax_amount' => $tax->toScale(4),
                'line_total' => $net->plus($tax)->toScale(4),
                'batch_number' => $line->batch_number,
                'serials' => array_values(array_filter($input['serials'] ?? [])) ?: null,
            ];
        }

        return DB::transaction(function () use ($actor, $invoice, $data, $return, $rows) {
            $sum = fn (string $key) => array_reduce($rows, fn (BigDecimal $c, $r) => $c->plus($r[$key]), BigDecimal::zero());

            $return ??= new PurchaseReturn(['status' => DocumentStatus::Draft, 'created_by' => $actor->id]);
            $return->fill([
                'purchase_invoice_id' => $invoice->id,
                'date' => $data['date'],
                'branch_id' => $invoice->branch_id,
                'warehouse_id' => $invoice->warehouse_id,
                'partner_id' => $invoice->partner_id,
                'currency_id' => $invoice->currency_id,
                'exchange_rate' => $invoice->exchange_rate,
                'subtotal' => $sum('net')->toScale(4),
                'discount_total' => '0',
                'tax_total' => $sum('tax_amount')->toScale(4),
                'total' => $sum('line_total')->toScale(4),
                'description' => $data['description'] ?? null,
            ])->save();

            $return->lines()->delete();
            $return->lines()->createMany($rows);

            return $return;
        });
    }

    public function post(User $actor, PurchaseReturn $return): PurchaseReturn
    {
        Gate::forUser($actor)->authorize('purchases.returns.post');

        return DB::transaction(function () use ($actor, $return) {
            $return = PurchaseReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($return->status !== DocumentStatus::Draft) {
                throw ValidationException::withMessages(['return' => __('core::documents.not_draft')]);
            }

            $return->load(['lines.product.category', 'lines.tax', 'partner', 'warehouse', 'branch', 'invoice.journalEntry']);
            $base = $this->currencies->base();
            $isForeign = $return->currency_id !== $base->id;
            $toBase = fn (BigDecimal $amount) => $amount->multipliedBy($return->exchange_rate)->toScale($base->decimal_places, RoundingMode::HalfUp);
            $description = __('purchases::returns.entry_description', ['supplier' => $return->partner->name]);

            $stockLines = $return->lines->filter(fn ($l) => $l->product->tracksStock())->values();
            $costs = [];

            if ($stockLines->isNotEmpty()) {
                $result = $this->issueStock->handle(new StockOperationData(
                    date: $return->date,
                    branchId: $return->branch_id,
                    type: StockMoveType::PurchaseReturn,
                    source: $return,
                    lines: $stockLines->map(fn ($l) => new StockLineData(
                        productId: $l->product_id,
                        warehouseId: $return->warehouse_id,
                        quantity: $l->base_quantity,
                        batchNumber: $l->batch_number,
                        serials: $l->serials ?? [],
                        sourceLine: $l,
                    ))->all(),
                    counterAccountKey: 'purchases.grni',
                    counterScopes: [$return->partner],
                    description: $description,
                    postedBy: $actor,
                ));

                foreach ($stockLines as $i => $line) {
                    $costs[$line->id] = $result->lineCost($i);
                    $line->update(['stock_cost' => $costs[$line->id]]);
                }
            }

            $entry = new EntryBuilder($isForeign ? $return->currency_id : null);
            $docTotal = BigDecimal::zero();

            foreach ($return->lines as $line) {
                $product = $line->product;
                $net = $toBase($line->net);

                if ($product->tracksStock()) {
                    $scopes = [$product, $product->category, $return->partner, $return->warehouse];
                    $entry->credit($this->accounts->resolve('purchases.grni', $scopes)->id, $costs[$line->id], $line->net);
                    // Refund above the stock cost is a gain, below it a loss.
                    $entry->credit($this->accounts->resolve('inventory.price_difference', [$product, $product->category, $return->warehouse])->id, $net->minus($costs[$line->id]));
                } else {
                    $entry->credit($this->accounts->resolve('purchases.expense', [$product, $product->category, $return->branch])->id, $net, $line->net);
                }

                if ($line->tax_amount->isPositive()) {
                    $entry->credit($this->accounts->resolve('tax.input', [$line->tax, $return->branch])->id, $toBase($line->tax_amount), $line->tax_amount);
                }

                $docTotal = $docTotal->plus($line->line_total);
            }

            $payableAccount = $this->accounts->resolve('purchases.payable', [$return->partner, $return->branch]);
            $entry->debit($payableAccount->id, $entry->totalCredit()->minus($entry->totalDebit()), $docTotal, $return->partner_id);

            $journal = $this->postEntry->handle(new JournalEntryData(
                date: $return->date,
                branchId: $return->branch_id,
                journalType: JournalType::Purchases,
                lines: $entry->lines(),
                description: $description,
                source: $return,
                postedBy: $actor,
            ));

            $return->update([
                'status' => DocumentStatus::Posted,
                'journal_entry_id' => $journal->id,
                'number' => $this->numbers->handle(PurchaseReturn::SEQUENCE, $return->branch_id, $return->date),
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $this->settleAgainstInvoice($return, $journal->lines()->where('account_id', $payableAccount->id)->where('debit', '>', 0)->first(), $actor);

            return $return;
        });
    }

    /**
     * The return reduces what is owed on its invoice: match it with the invoice's open amount.
     */
    private function settleAgainstInvoice(PurchaseReturn $return, ?JournalLine $returnLine, User $actor): void
    {
        $invoiceLine = $return->invoice->journalEntry?->lines()
            ->where('partner_id', $return->partner_id)->where('credit', '>', 0)->first();

        if ($returnLine === null || $invoiceLine === null || $returnLine->account_id !== $invoiceLine->account_id) {
            return;
        }

        $amount = $this->reconciler->residual($invoiceLine);
        $own = $this->reconciler->residual($returnLine);
        $amount = $amount->isLessThan($own) ? $amount : $own;

        if ($amount->isPositive()) {
            $this->reconciler->reconcile($returnLine, $invoiceLine, $amount, $actor->id);
        }
    }

    public function cancel(User $actor, PurchaseReturn $return, string $reason): PurchaseReturn
    {
        Gate::forUser($actor)->authorize('purchases.returns.cancel');

        return DB::transaction(function () use ($actor, $return, $reason) {
            $return = PurchaseReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($return->status !== DocumentStatus::Posted) {
                throw ValidationException::withMessages(['return' => __('core::documents.not_posted')]);
            }

            $date = CarbonImmutable::today()->max($return->date);

            foreach ($return->journalEntry->lines()->whereNotNull('partner_id')->get() as $line) {
                $this->reconciler->unreconcile($line);
            }

            $this->reverseStock->handle($return, $date, $reason, $return->branch_id, $actor);
            $this->reverseEntry->handle($return->journalEntry, $date, $reason, $actor);
            $return->update(['status' => DocumentStatus::Cancelled, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

            return $return;
        });
    }

    public function delete(User $actor, PurchaseReturn $return): void
    {
        Gate::forUser($actor)->authorize('purchases.returns.create');

        if ($return->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['return' => __('core::documents.not_draft')]);
        }

        DB::transaction(fn () => $return->delete());
    }
}
