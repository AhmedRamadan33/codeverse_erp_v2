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
use Modules\Accounting\Models\Reconciliation;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Posting\JournalEntryData;
use Modules\Accounting\Posting\PostJournalEntry;
use Modules\Accounting\Posting\ReverseJournalEntry;
use Modules\Accounting\Pricing\Discount;
use Modules\Accounting\Pricing\DocumentTotals;
use Modules\Accounting\Pricing\PricedLine;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Modules\Core\Sequences\NextNumber;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\Actions\ReceiveStock;
use Modules\Inventory\Stock\Actions\ReverseStock;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Stock\StockOperationData;
use Modules\Products\Models\Product;
use Modules\Products\Support\UnitConverter;
use Modules\Purchases\Models\PurchaseInvoice;
use Modules\Purchases\Models\PurchaseInvoiceLine;
use Modules\Purchases\Posting\EntryBuilder;

/**
 * Purchase invoices (core-design.md §7): Dr GRNI (stock items) or purchases expense (others),
 * Dr input tax, Cr the supplier; Inventory receives the stock at the same net cost
 * (Dr inventory / Cr GRNI), so GRNI nets to zero.
 */
class InvoiceActions
{
    public function __construct(
        private readonly DocumentTotals $totals,
        private readonly UnitConverter $converter,
        private readonly Currencies $currencies,
        private readonly AccountResolver $accounts,
        private readonly PostJournalEntry $postEntry,
        private readonly ReverseJournalEntry $reverseEntry,
        private readonly ReceiveStock $receiveStock,
        private readonly ReverseStock $reverseStock,
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
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')->where('is_supplier', true)->where('is_active', true)],
            'supplier_reference' => ['nullable', 'string', 'max:64'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'exchange_rate' => ['nullable', 'decimal:0,6', 'gt:0'],
            'discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', 'decimal:0,4', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.unit_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999999'],
            'lines.*.unit_price' => ['required', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'lines.*.discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'lines.*.discount_value' => ['nullable', 'decimal:0,4', 'min:0'],
            'lines.*.tax_id' => ['nullable', 'integer', Rule::exists('taxes', 'id')->where('is_active', true)],
            'lines.*.batch_number' => ['nullable', 'string', 'max:64'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'lines.*.serials' => ['nullable', 'array'],
            'lines.*.serials.*' => ['string', 'max:128'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function save(User $actor, array $data, ?PurchaseInvoice $invoice = null): PurchaseInvoice
    {
        Gate::forUser($actor)->authorize('purchases.invoices.create');

        if ($invoice && $invoice->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => __('core::documents.not_draft')]);
        }

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        if (! $actor->canAccessBranch($warehouse->branch_id)) {
            throw ValidationException::withMessages(['warehouse_id' => __('purchases::invoices.warehouse_not_allowed')]);
        }

        Partner::visibleTo($actor)->find($data['partner_id'])
            ?? throw ValidationException::withMessages(['partner_id' => __('purchases::invoices.supplier_not_allowed')]);

        $currency = Currency::findOrFail($data['currency_id']);
        $isBase = $this->currencies->isBase($currency);
        if (! $isBase && empty($data['exchange_rate'])) {
            throw ValidationException::withMessages(['exchange_rate' => __('accounting::vouchers.rate_required')]);
        }

        $lines = array_values($data['lines']);
        $products = Product::with('units')->whereKey(array_column($lines, 'product_id'))->get()->keyBy('id');
        $taxes = Tax::whereKey(array_filter(array_column($lines, 'tax_id')))->get()->keyBy('id');

        $priced = array_map(fn ($line) => new PricedLine(
            (string) $line['quantity'],
            (string) $line['unit_price'],
            Discount::fromInput($line['discount_type'] ?? null, $line['discount_value'] ?? null),
            ! empty($line['tax_id']) ? $taxes[$line['tax_id']] : null,
        ), $lines);

        $result = $this->totals->calculate($priced, $currency->decimal_places, Discount::fromInput($data['discount_type'] ?? null, $data['discount_value'] ?? null));

        return DB::transaction(function () use ($actor, $data, $invoice, $warehouse, $isBase, $lines, $products, $taxes, $result) {
            $invoice ??= new PurchaseInvoice(['status' => DocumentStatus::Draft, 'created_by' => $actor->id]);
            $partner = Partner::find($data['partner_id']);

            $invoice->fill([
                'date' => $data['date'],
                'due_date' => $data['due_date'] ?? CarbonImmutable::parse($data['date'])->addDays($partner->payment_term_days)->toDateString(),
                'partner_id' => $partner->id,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $isBase ? '1' : BigDecimal::of((string) $data['exchange_rate'])->toScale(6),
                'discount_type' => ($data['discount_value'] ?? '') !== '' ? ($data['discount_type'] ?? 'amount') : null,
                'discount_value' => ($data['discount_value'] ?? '') !== '' ? (string) $data['discount_value'] : null,
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
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'serials' => array_values(array_filter($line['serials'] ?? [])) ?: null,
                ]);
            }

            return $invoice;
        });
    }

    public function post(User $actor, PurchaseInvoice $invoice): PurchaseInvoice
    {
        Gate::forUser($actor)->authorize('purchases.invoices.post');

        return DB::transaction(function () use ($actor, $invoice) {
            $invoice = PurchaseInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== DocumentStatus::Draft) {
                throw ValidationException::withMessages(['invoice' => __('core::documents.not_draft')]);
            }

            $invoice->load(['lines.product.category', 'lines.tax', 'partner', 'warehouse', 'branch']);
            $base = $this->currencies->base();
            $isForeign = $invoice->currency_id !== $base->id;
            $toBase = fn (BigDecimal $amount) => $amount->multipliedBy($invoice->exchange_rate)->toScale($base->decimal_places, RoundingMode::HalfUp);

            $entry = new EntryBuilder($isForeign ? $invoice->currency_id : null);
            $stockLines = [];
            $docTotal = BigDecimal::zero();

            foreach ($invoice->lines as $line) {
                $product = $line->product;
                $net = $toBase($line->net);
                $scopes = [$product, $product->category, $invoice->partner, $invoice->warehouse];

                if ($product->tracksStock()) {
                    $entry->debit($this->accounts->resolve('purchases.grni', $scopes)->id, $net, $line->net);
                    $stockLines[] = new StockLineData(
                        productId: $product->id,
                        warehouseId: $invoice->warehouse_id,
                        quantity: $line->base_quantity,
                        batchNumber: $line->batch_number,
                        expiryDate: $line->expiry_date,
                        serials: $line->serials ?? [],
                        sourceLine: $line,
                        totalCost: $net,
                    );
                } else {
                    $entry->debit($this->accounts->resolve('purchases.expense', [$product, $product->category, $invoice->branch])->id, $net, $line->net);
                }

                if ($line->tax_amount->isPositive()) {
                    $entry->debit($this->accounts->resolve('tax.input', [$line->tax, $invoice->branch])->id, $toBase($line->tax_amount), $line->tax_amount);
                }

                $docTotal = $docTotal->plus($line->line_total);
            }

            // The supplier is owed the sum of the converted lines, so the entry always balances.
            $entry->credit(
                $this->accounts->resolve('purchases.payable', [$invoice->partner, $invoice->branch])->id,
                $entry->totalDebit(), $docTotal, $invoice->partner_id, $invoice->due_date,
            );

            $description = __('purchases::invoices.entry_description', ['supplier' => $invoice->partner->name, 'reference' => $invoice->supplier_reference]);

            if ($stockLines !== []) {
                $this->receiveStock->handle(new StockOperationData(
                    date: $invoice->date,
                    branchId: $invoice->branch_id,
                    type: StockMoveType::Purchase,
                    source: $invoice,
                    lines: $stockLines,
                    counterAccountKey: 'purchases.grni',
                    counterScopes: [$invoice->partner],
                    description: $description,
                    postedBy: $actor,
                ));
            }

            $journal = $entry->totalDebit()->isZero() ? null : $this->postEntry->handle(new JournalEntryData(
                date: $invoice->date,
                branchId: $invoice->branch_id,
                journalType: JournalType::Purchases,
                lines: $entry->lines(),
                description: $description,
                source: $invoice,
                postedBy: $actor,
            ));

            $invoice->update([
                'status' => DocumentStatus::Posted,
                'journal_entry_id' => $journal?->id,
                'number' => $this->numbers->handle(PurchaseInvoice::SEQUENCE, $invoice->branch_id, $invoice->date),
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            return $invoice;
        });
    }

    public function cancel(User $actor, PurchaseInvoice $invoice, string $reason): PurchaseInvoice
    {
        Gate::forUser($actor)->authorize('purchases.invoices.cancel');

        return DB::transaction(function () use ($actor, $invoice, $reason) {
            $invoice = PurchaseInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== DocumentStatus::Posted) {
                throw ValidationException::withMessages(['invoice' => __('core::documents.not_posted')]);
            }

            if ($invoice->returns()->where('status', '!=', DocumentStatus::Cancelled)->exists()) {
                throw ValidationException::withMessages(['invoice' => __('purchases::invoices.has_returns')]);
            }

            $payableLines = $invoice->journalEntry?->lines()->where('partner_id', $invoice->partner_id)->pluck('id') ?? collect();
            if (Reconciliation::whereIn('credit_line_id', $payableLines)->exists()) {
                throw ValidationException::withMessages(['invoice' => __('purchases::invoices.has_payments')]);
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

    public function delete(User $actor, PurchaseInvoice $invoice): void
    {
        Gate::forUser($actor)->authorize('purchases.invoices.create');

        if ($invoice->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => __('core::documents.not_draft')]);
        }

        DB::transaction(fn () => $invoice->delete());
    }
}
