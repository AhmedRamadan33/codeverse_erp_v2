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
use Modules\Accounting\Models\Reconciliation;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Posting\EntryBuilder;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Accounting\Posting\ReverseJournalEntry;
use Modules\Accounting\Pricing\Discount;
use Modules\Accounting\Pricing\DocumentTotals;
use Modules\Accounting\Pricing\PricedLine;
use Modules\Accounting\Vouchers\Actions\PostVoucher;
use Modules\Accounting\Vouchers\Actions\SaveVoucher;
use Modules\Accounting\Vouchers\VoucherKind;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Modules\Core\Sequences\NextNumber;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\Actions\IssueStock;
use Modules\Inventory\Stock\Actions\ReverseStock;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Products\Models\Product;
use Modules\Products\Support\UnitConverter;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Pricing\CreditLimit;
use Modules\Sales\Pricing\PriceResolver;

/**
 * Sales invoices (core-design.md §7): Dr customer (with due date) / Cr revenue / Cr output tax;
 * Inventory issues the stock at average cost (Dr COGS / Cr inventory). A payment taken at
 * posting becomes a receipt voucher allocated to the invoice.
 */
class InvoiceActions
{
    public function __construct(
        private readonly DocumentTotals $totals,
        private readonly UnitConverter $converter,
        private readonly PriceResolver $prices,
        private readonly CreditLimit $creditLimit,
        private readonly Currencies $currencies,
        private readonly AccountResolver $accounts,
        private readonly PostJournalEntry $postEntry,
        private readonly ReverseJournalEntry $reverseEntry,
        private readonly IssueStock $issueStock,
        private readonly ReverseStock $reverseStock,
        private readonly SaveVoucher $saveVoucher,
        private readonly PostVoucher $postVoucher,
        private readonly NextNumber $numbers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')->where('is_customer', true)->where('is_active', true)],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'exchange_rate' => ['nullable', 'decimal:0,6', 'gt:0'],
            'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')->where('is_active', true)],
            'discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', 'decimal:0,4', 'min:0'],
            'payment_method_id' => ['nullable', 'required_with:paid_amount', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'paid_amount' => ['nullable', 'decimal:0,4', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'lines.*.unit_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999999'],
            // Empty = the price list / product price.
            'lines.*.unit_price' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'lines.*.discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'lines.*.discount_value' => ['nullable', 'decimal:0,4', 'min:0'],
            'lines.*.tax_id' => ['nullable', 'integer', Rule::exists('taxes', 'id')->where('is_active', true)],
            'lines.*.batch_number' => ['nullable', 'string', 'max:64'],
            'lines.*.serials' => ['nullable', 'array'],
            'lines.*.serials.*' => ['string', 'max:128'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function save(User $actor, array $data, ?SalesInvoice $invoice = null): SalesInvoice
    {
        Gate::forUser($actor)->authorize('sales.invoices.create');

        if ($invoice && $invoice->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => __('core::documents.not_draft')]);
        }

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        if (! $actor->canAccessBranch($warehouse->branch_id)) {
            throw ValidationException::withMessages(['warehouse_id' => __('sales::invoices.warehouse_not_allowed')]);
        }

        $customer = Partner::visibleTo($actor)->find($data['partner_id'])
            ?? throw ValidationException::withMessages(['partner_id' => __('sales::invoices.customer_not_allowed')]);

        $currency = Currency::findOrFail($data['currency_id']);
        $isBase = $this->currencies->isBase($currency);
        if (! $isBase && empty($data['exchange_rate'])) {
            throw ValidationException::withMessages(['exchange_rate' => __('accounting::vouchers.rate_required')]);
        }

        $priceListId = $data['price_list_id'] ?? $this->prices->priceListFor($customer->id);
        $lines = array_values($data['lines']);
        $products = Product::with('units')->whereKey(array_column($lines, 'product_id'))->get()->keyBy('id');
        $taxes = Tax::whereKey(array_filter(array_column($lines, 'tax_id')))->get()->keyBy('id');

        foreach ($lines as &$line) {
            if (($line['unit_price'] ?? '') === '' || $line['unit_price'] === null) {
                $line['unit_price'] = (string) $this->prices->price($products[$line['product_id']], (int) $line['unit_id'], $priceListId);
            }
        }
        unset($line);

        $result = $this->totals->calculate(array_map(fn ($line) => new PricedLine(
            (string) $line['quantity'],
            (string) $line['unit_price'],
            Discount::fromInput($line['discount_type'] ?? null, $line['discount_value'] ?? null),
            ! empty($line['tax_id']) ? $taxes[$line['tax_id']] : null,
        ), $lines), $currency->decimal_places, Discount::fromInput($data['discount_type'] ?? null, $data['discount_value'] ?? null));

        $paid = ($data['paid_amount'] ?? '') === '' ? null : BigDecimal::of((string) $data['paid_amount']);
        if ($paid && $paid->isGreaterThan($result->total())) {
            throw ValidationException::withMessages(['paid_amount' => __('sales::invoices.paid_more_than_total')]);
        }

        return DB::transaction(function () use ($actor, $data, $invoice, $warehouse, $customer, $isBase, $priceListId, $lines, $products, $taxes, $result, $paid) {
            $invoice ??= new SalesInvoice(['status' => DocumentStatus::Draft, 'created_by' => $actor->id]);

            $invoice->fill([
                'date' => $data['date'],
                'due_date' => $data['due_date'] ?? CarbonImmutable::parse($data['date'])->addDays($customer->payment_term_days)->toDateString(),
                'partner_id' => $customer->id,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $isBase ? '1' : BigDecimal::of((string) $data['exchange_rate'])->toScale(6),
                'price_list_id' => $priceListId,
                'discount_type' => ($data['discount_value'] ?? '') !== '' ? ($data['discount_type'] ?? 'amount') : null,
                'discount_value' => ($data['discount_value'] ?? '') !== '' ? (string) $data['discount_value'] : null,
                'payment_method_id' => $paid && $paid->isPositive() ? $data['payment_method_id'] : null,
                'paid_amount' => $paid && $paid->isPositive() ? $paid->toScale(4) : null,
                'subtotal' => $result->subtotal()->toScale(4),
                'discount_total' => $result->discountTotal()->toScale(4),
                'tax_total' => $result->taxTotal()->toScale(4),
                'total' => $result->total()->toScale(4),
                'description' => $data['description'] ?? null,
            ])->save();

            $invoice->lines()->delete();

            foreach ($lines as $i => $line) {
                $product = $products[$line['product_id']];
                $totals = $result->lines[$i];
                $tax = ! empty($line['tax_id']) ? $taxes[$line['tax_id']] : null;

                $invoice->lines()->create([
                    'line_no' => $i + 1,
                    'product_id' => $product->id,
                    'unit_id' => $line['unit_id'],
                    'description' => $line['description'] ?? null,
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

            return $invoice;
        });
    }

    /**
     * @param  bool  $confirmOverLimit  the user confirmed posting past the customer's credit limit
     */
    public function post(User $actor, SalesInvoice $invoice, bool $confirmOverLimit = false): SalesInvoice
    {
        Gate::forUser($actor)->authorize('sales.invoices.post');

        return DB::transaction(function () use ($actor, $invoice, $confirmOverLimit) {
            $invoice = SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== DocumentStatus::Draft) {
                throw ValidationException::withMessages(['invoice' => __('core::documents.not_draft')]);
            }

            $invoice->load(['lines.product.category', 'lines.tax', 'partner', 'warehouse', 'branch']);
            $base = $this->currencies->base();
            $isForeign = $invoice->currency_id !== $base->id;
            $toBase = fn (BigDecimal $amount) => $amount->multipliedBy($invoice->exchange_rate)->toScale($base->decimal_places, RoundingMode::HalfUp);

            // Lock the customer so two invoices posted at once cannot both pass the credit limit.
            Partner::whereKey($invoice->partner_id)->lockForUpdate()->first();
            $this->creditLimit->check($actor, $invoice->partner, $toBase($invoice->total->minus($invoice->paid_amount ?? BigDecimal::zero())), $invoice->branch_id, $confirmOverLimit);

            $description = __('sales::invoices.entry_description', ['customer' => $invoice->partner->name]);
            $stockLines = $invoice->lines->filter(fn ($l) => $l->product->tracksStock())->values();

            if ($stockLines->isNotEmpty()) {
                $result = $this->issueStock->handle(new StockOperationData(
                    date: $invoice->date,
                    branchId: $invoice->branch_id,
                    type: StockMoveType::Sale,
                    source: $invoice,
                    lines: $stockLines->map(fn ($l) => new StockLineData(
                        productId: $l->product_id,
                        warehouseId: $invoice->warehouse_id,
                        quantity: $l->base_quantity,
                        batchNumber: $l->batch_number,
                        serials: $l->serials ?? [],
                        sourceLine: $l,
                    ))->all(),
                    counterAccountKey: 'inventory.cogs',
                    counterScopes: [$invoice->partner],
                    description: $description,
                    postedBy: $actor,
                ));

                foreach ($stockLines as $i => $line) {
                    $line->update(['cost' => $result->lineCost($i)]);
                }
            }

            $entry = new EntryBuilder($isForeign ? $invoice->currency_id : null);
            $docTotal = BigDecimal::zero();

            foreach ($invoice->lines as $line) {
                $product = $line->product;
                $entry->credit($this->accounts->resolve('sales.revenue', [$product, $product->category, $invoice->partner, $invoice->branch])->id, $toBase($line->net), $line->net);

                if ($line->tax_amount->isPositive()) {
                    $entry->credit($this->accounts->resolve('tax.output', [$line->tax, $invoice->branch])->id, $toBase($line->tax_amount), $line->tax_amount);
                }

                $docTotal = $docTotal->plus($line->line_total);
            }

            $receivable = $this->accounts->resolve('sales.receivable', [$invoice->partner, $invoice->branch]);
            $entry->debit($receivable->id, $entry->totalCredit(), $docTotal, $invoice->partner_id, $invoice->due_date);

            $journal = $entry->totalCredit()->isZero() ? null : $this->postEntry->handle(new JournalEntryData(
                date: $invoice->date,
                branchId: $invoice->branch_id,
                journalType: JournalType::Sales,
                lines: $entry->lines(),
                description: $description,
                source: $invoice,
                postedBy: $actor,
            ));

            $invoice->update([
                'status' => DocumentStatus::Posted,
                'journal_entry_id' => $journal?->id,
                'number' => $this->numbers->handle(SalesInvoice::SEQUENCE, $invoice->branch_id, $invoice->date),
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            if ($journal && $invoice->paid_amount?->isPositive()) {
                $this->collectPayment($actor, $invoice, $journal->lines()->where('account_id', $receivable->id)->where('debit', '>', 0)->firstOrFail()->id);
            }

            return $invoice;
        });
    }

    /**
     * Payment taken at the counter: a receipt voucher allocated to this invoice.
     */
    private function collectPayment(User $actor, SalesInvoice $invoice, int $receivableLineId): void
    {
        $voucher = $this->saveVoucher->handle($actor, VoucherKind::Receipt, [
            'date' => $invoice->date->toDateString(),
            'branch_id' => $invoice->branch_id,
            'partner_id' => $invoice->partner_id,
            'payment_method_id' => $invoice->payment_method_id,
            'currency_id' => $invoice->currency_id,
            'exchange_rate' => (string) $invoice->exchange_rate,
            'amount' => (string) $invoice->paid_amount->toScale($invoice->currency->decimal_places),
            'reference' => $invoice->number,
            'description' => __('sales::invoices.payment_description', ['number' => $invoice->number]),
        ]);

        $base = $this->currencies->base();
        $allocation = $invoice->paid_amount->multipliedBy($invoice->exchange_rate)->toScale($base->decimal_places, RoundingMode::HalfUp);

        $this->postVoucher->handle($actor, $voucher, [$receivableLineId => (string) $allocation]);
    }

    public function cancel(User $actor, SalesInvoice $invoice, string $reason): SalesInvoice
    {
        Gate::forUser($actor)->authorize('sales.invoices.cancel');

        return DB::transaction(function () use ($actor, $invoice, $reason) {
            $invoice = SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== DocumentStatus::Posted) {
                throw ValidationException::withMessages(['invoice' => __('core::documents.not_posted')]);
            }

            if ($invoice->returns()->where('status', '!=', DocumentStatus::Cancelled)->exists()) {
                throw ValidationException::withMessages(['invoice' => __('sales::invoices.has_returns')]);
            }

            $receivableLines = $invoice->journalEntry?->lines()->where('partner_id', $invoice->partner_id)->pluck('id') ?? collect();
            if (Reconciliation::whereIn('debit_line_id', $receivableLines)->exists()) {
                throw ValidationException::withMessages(['invoice' => __('sales::invoices.has_payments')]);
            }

            $date = CarbonImmutable::today()->max($invoice->date);
            $this->reverseStock->handle($invoice, $date, $reason, $invoice->branch_id, $actor);

            if ($invoice->journalEntry) {
                $this->reverseEntry->handle($invoice->journalEntry, $date, $reason, $actor);
            }

            $invoice->update(['status' => DocumentStatus::Cancelled, 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

            return $invoice;
        });
    }

    public function delete(User $actor, SalesInvoice $invoice): void
    {
        Gate::forUser($actor)->authorize('sales.invoices.create');

        if ($invoice->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => __('core::documents.not_draft')]);
        }

        DB::transaction(fn () => $invoice->delete());
    }
}
