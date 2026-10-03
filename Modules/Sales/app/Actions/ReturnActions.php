<?php

namespace Modules\Sales\Actions;

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
use Modules\Accounting\Posting\EntryBuilder;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Accounting\Posting\ReverseJournalEntry;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Sequences\NextNumber;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Stock\Actions\ReceiveStock;
use Modules\Inventory\Stock\Actions\ReverseStock;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoiceLine;
use Modules\Sales\Models\SalesReturn;

/**
 * Goods a customer brings back, always against a posted invoice.
 * Dr sales returns / Dr output tax / Cr customer; stock comes back at its original issue
 * cost (Dr inventory / Cr COGS), so the sale's margin is undone exactly.
 */
class ReturnActions
{
    public function __construct(
        private readonly Currencies $currencies,
        private readonly AccountResolver $accounts,
        private readonly PostJournalEntry $postEntry,
        private readonly ReverseJournalEntry $reverseEntry,
        private readonly ReceiveStock $receiveStock,
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
            'lines.*.sales_invoice_line_id' => ['required', 'integer', 'distinct', Rule::exists('sales_invoice_lines', 'id')],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'lines.*.serials' => ['nullable', 'array'],
            'lines.*.serials.*' => ['string', 'max:128'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function save(User $actor, SalesInvoice $invoice, array $data, ?SalesReturn $return = null): SalesReturn
    {
        Gate::forUser($actor)->authorize('sales.returns.create');

        if ($invoice->status !== DocumentStatus::Posted) {
            throw ValidationException::withMessages(['invoice' => __('sales::returns.invoice_not_posted')]);
        }

        if ($return && $return->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['return' => __('core::documents.not_draft')]);
        }

        if (CarbonImmutable::parse($data['date'])->lt($invoice->date)) {
            throw ValidationException::withMessages(['date' => __('sales::returns.before_invoice')]);
        }

        $invoiceLines = $invoice->lines()->get()->keyBy('id');
        $scale = $invoice->currency->decimal_places;
        $rows = [];

        foreach (array_values($data['lines']) as $i => $input) {
            /** @var SalesInvoiceLine|null $line */
            $line = $invoiceLines[$input['sales_invoice_line_id']] ?? null;
            if ($line === null) {
                throw ValidationException::withMessages(["lines.{$i}.sales_invoice_line_id" => __('sales::returns.line_not_on_invoice')]);
            }

            $quantity = BigDecimal::of((string) $input['quantity'])->toScale(4);
            $returnable = $line->returnableQuantity($return?->id);

            if ($quantity->isGreaterThan($returnable)) {
                throw ValidationException::withMessages(["lines.{$i}.quantity" => __('sales::returns.too_much', [
                    'product' => $line->product->name, 'returnable' => (string) $returnable->strippedOfTrailingZeros(),
                ])]);
            }

            $share = fn (BigDecimal $amount, int $s) => $amount->multipliedBy($quantity)->dividedBy($line->quantity, $s, RoundingMode::HalfUp);

            if ($quantity->isEqualTo($returnable)) {
                // The last return of a line takes exactly what is left.
                $done = $line->returnLines()->whereHas('salesReturn', fn ($q) => $q->where('status', '!=', DocumentStatus::Cancelled)
                    ->when($return, fn ($q) => $q->whereKeyNot($return->id)));
                $net = $line->net->minus(BigDecimal::of((clone $done)->sum('net') ?: 0));
                $tax = $line->tax_amount->minus(BigDecimal::of((clone $done)->sum('tax_amount') ?: 0));
                $cost = $line->cost?->minus(BigDecimal::of((clone $done)->sum('cost') ?: 0));
            } else {
                $net = $share($line->net, $scale);
                $tax = $share($line->tax_amount, $scale);
                $cost = $line->cost ? $share($line->cost, 4) : null;
            }

            $rows[] = [
                'line_no' => $i + 1,
                'sales_invoice_line_id' => $line->id,
                'product_id' => $line->product_id,
                'unit_id' => $line->unit_id,
                'quantity' => $quantity,
                'base_quantity' => $share($line->base_quantity, 4),
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

        return DB::transaction(function () use ($actor, $invoice, $data, $return, $rows) {
            $sum = fn (string $key) => array_reduce($rows, fn (BigDecimal $c, $r) => $c->plus($r[$key]), BigDecimal::zero());

            $return ??= new SalesReturn(['status' => DocumentStatus::Draft, 'created_by' => $actor->id]);
            $return->fill([
                'sales_invoice_id' => $invoice->id,
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

    public function post(User $actor, SalesReturn $return): SalesReturn
    {
        Gate::forUser($actor)->authorize('sales.returns.post');

        return DB::transaction(function () use ($actor, $return) {
            $return = SalesReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($return->status !== DocumentStatus::Draft) {
                throw ValidationException::withMessages(['return' => __('core::documents.not_draft')]);
            }

            $return->load(['lines.product.category', 'lines.tax', 'partner', 'warehouse', 'branch', 'invoice.journalEntry']);
            $base = $this->currencies->base();
            $isForeign = $return->currency_id !== $base->id;
            $toBase = fn (BigDecimal $amount) => $amount->multipliedBy($return->exchange_rate)->toScale($base->decimal_places, RoundingMode::HalfUp);
            $description = __('sales::returns.entry_description', ['customer' => $return->partner->name]);

            $stockLines = $return->lines->filter(fn ($l) => $l->product->tracksStock())->values();

            if ($stockLines->isNotEmpty()) {
                $this->receiveStock->handle(new StockOperationData(
                    date: $return->date,
                    branchId: $return->branch_id,
                    type: StockMoveType::SaleReturn,
                    source: $return,
                    lines: $stockLines->map(fn ($l) => new StockLineData(
                        productId: $l->product_id,
                        warehouseId: $return->warehouse_id,
                        quantity: $l->base_quantity,
                        batchNumber: $l->batch_number,
                        serials: $l->serials ?? [],
                        sourceLine: $l,
                        // Back at what it cost when it left.
                        totalCost: $l->cost ?? '0',
                    ))->all(),
                    counterAccountKey: 'inventory.cogs',
                    counterScopes: [$return->partner],
                    description: $description,
                    postedBy: $actor,
                ));
            }

            $entry = new EntryBuilder($isForeign ? $return->currency_id : null);
            $docTotal = BigDecimal::zero();

            foreach ($return->lines as $line) {
                $product = $line->product;
                $entry->debit($this->accounts->resolve('sales.returns', [$product, $product->category, $return->partner, $return->branch])->id, $toBase($line->net), $line->net);

                if ($line->tax_amount->isPositive()) {
                    $entry->debit($this->accounts->resolve('tax.output', [$line->tax, $return->branch])->id, $toBase($line->tax_amount), $line->tax_amount);
                }

                $docTotal = $docTotal->plus($line->line_total);
            }

            $receivable = $this->accounts->resolve('sales.receivable', [$return->partner, $return->branch]);
            $entry->credit($receivable->id, $entry->totalDebit(), $docTotal, $return->partner_id);

            $journal = $this->postEntry->handle(new JournalEntryData(
                date: $return->date,
                branchId: $return->branch_id,
                journalType: JournalType::Sales,
                lines: $entry->lines(),
                description: $description,
                source: $return,
                postedBy: $actor,
            ));

            $return->update([
                'status' => DocumentStatus::Posted,
                'journal_entry_id' => $journal->id,
                'number' => $this->numbers->handle(SalesReturn::SEQUENCE, $return->branch_id, $return->date),
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $this->settleAgainstInvoice($return, $journal->lines()->where('account_id', $receivable->id)->where('credit', '>', 0)->first(), $actor);

            return $return;
        });
    }

    /**
     * The return reduces what the customer owes on its invoice.
     */
    private function settleAgainstInvoice(SalesReturn $return, ?JournalLine $returnLine, User $actor): void
    {
        $invoiceLine = $return->invoice->journalEntry?->lines()
            ->where('partner_id', $return->partner_id)->where('debit', '>', 0)->first();

        if ($returnLine === null || $invoiceLine === null || $returnLine->account_id !== $invoiceLine->account_id) {
            return;
        }

        $open = $this->reconciler->residual($invoiceLine);
        $own = $this->reconciler->residual($returnLine);
        $amount = $open->isLessThan($own) ? $open : $own;

        if ($amount->isPositive()) {
            $this->reconciler->reconcile($invoiceLine, $returnLine, $amount, $actor->id);
        }
    }

    public function cancel(User $actor, SalesReturn $return, string $reason): SalesReturn
    {
        Gate::forUser($actor)->authorize('sales.returns.cancel');

        return DB::transaction(function () use ($actor, $return, $reason) {
            $return = SalesReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

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

    public function delete(User $actor, SalesReturn $return): void
    {
        Gate::forUser($actor)->authorize('sales.returns.create');

        if ($return->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['return' => __('core::documents.not_draft')]);
        }

        DB::transaction(fn () => $return->delete());
    }
}
