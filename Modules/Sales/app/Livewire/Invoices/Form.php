<?php

namespace Modules\Sales\Livewire\Invoices;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Accounting\Enums\TaxScope;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Pricing\Discount;
use Modules\Accounting\Pricing\DocumentTotals;
use Modules\Accounting\Pricing\PricedLine;
use Modules\Accounting\Pricing\TotalsResult;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Modules\Core\Settings\Settings;
use Modules\Inventory\Models\Warehouse;
use Modules\Products\Models\Product;
use Modules\Sales\Actions\InvoiceActions;
use Modules\Sales\Models\PriceList;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Pricing\PriceResolver;
use Throwable;

#[Layout('core::layouts.app')]
class Form extends Component
{
    public ?int $invoiceId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(Currencies $currencies, Settings $settings, PriceResolver $prices, ?int $id = null): void
    {
        Gate::authorize('sales.invoices.create');

        if ($id === null) {
            $customerId = request()->integer('customer') ?: $settings->get('sales.walk_in_partner_id');
            $this->form = [
                'date' => now()->toDateString(), 'due_date' => null, 'partner_id' => $customerId,
                'price_list_id' => $prices->priceListFor($customerId),
                'warehouse_id' => $this->warehouses()->value('id'), 'currency_id' => $currencies->base()->id, 'exchange_rate' => '1',
                'discount_type' => 'amount', 'discount_value' => '', 'description' => '',
                'payment_method_id' => PaymentMethod::where('is_active', true)->orderBy('sort')->value('id'), 'paid_amount' => '',
                'lines' => [$this->blankLine()],
            ];

            return;
        }

        $invoice = SalesInvoice::with('lines')->findOrFail($id);
        abort_unless($invoice->status === DocumentStatus::Draft && auth()->user()->canAccessBranch($invoice->branch_id), 404);
        $this->invoiceId = $invoice->id;
        $strip = fn ($v) => $v === null ? '' : (string) $v->strippedOfTrailingZeros();

        $this->form = [
            'date' => $invoice->date->toDateString(), 'due_date' => $invoice->due_date?->toDateString(),
            'partner_id' => $invoice->partner_id, 'price_list_id' => $invoice->price_list_id,
            'warehouse_id' => $invoice->warehouse_id, 'currency_id' => $invoice->currency_id,
            'exchange_rate' => $strip($invoice->exchange_rate),
            'discount_type' => $invoice->discount_type ?? 'amount', 'discount_value' => $strip($invoice->discount_value),
            'description' => $invoice->description,
            'payment_method_id' => $invoice->payment_method_id ?? PaymentMethod::where('is_active', true)->orderBy('sort')->value('id'),
            'paid_amount' => $strip($invoice->paid_amount),
            'lines' => $invoice->lines->map(fn ($l) => [
                'product_id' => $l->product_id, 'unit_id' => $l->unit_id, 'description' => $l->description,
                'quantity' => $strip($l->quantity), 'unit_price' => $strip($l->unit_price),
                'discount_type' => $l->discount_type ?? 'percent', 'discount_value' => $strip($l->discount_value),
                'tax_id' => $l->tax_id, 'batch_number' => $l->batch_number,
                'serials' => implode(', ', $l->serials ?? []),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankLine(): array
    {
        return ['product_id' => null, 'unit_id' => null, 'description' => '', 'quantity' => '1', 'unit_price' => '',
            'discount_type' => 'percent', 'discount_value' => '', 'tax_id' => null, 'batch_number' => '', 'serials' => ''];
    }

    private function warehouses()
    {
        $user = auth()->user();

        return Warehouse::where('is_active', true)
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->orderBy('code');
    }

    public function addLine(): void
    {
        $this->form['lines'][] = $this->blankLine();
    }

    public function removeLine(int $i): void
    {
        unset($this->form['lines'][$i]);
        $this->form['lines'] = array_values($this->form['lines']);
    }

    private function priceOf(Product $product, int $unitId): string
    {
        $listId = ($this->form['price_list_id'] ?? '') === '' ? null : (int) $this->form['price_list_id'];

        return (string) app(PriceResolver::class)->price($product, $unitId, $listId)->strippedOfTrailingZeros();
    }

    /**
     * From the product picker: fill unit, price (from the price list) and tax.
     */
    #[On('product-picked')]
    public function productPicked(int $index, int $productId, ?int $unitId = null): void
    {
        $product = Product::with('units')->findOrFail($productId);
        $unit = $unitId ? $product->units->firstWhere('unit_id', $unitId) : ($product->units->firstWhere('is_default_sale', true) ?? $product->units->firstWhere('unit_id', $product->base_unit_id));
        $unitId = $unit?->unit_id ?? $product->base_unit_id;

        $this->form['lines'][$index]['product_id'] = $product->id;
        $this->form['lines'][$index]['unit_id'] = $unitId;
        $this->form['lines'][$index]['unit_price'] = $this->priceOf($product, $unitId);
        $this->form['lines'][$index]['tax_id'] = $product->sale_tax_id;
    }

    /**
     * A new customer brings their price list; a new unit or price list re-prices the lines.
     */
    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'partner_id') {
            $this->form['price_list_id'] = app(PriceResolver::class)->priceListFor($value ? (int) $value : null);
            $this->repriceLines();
        } elseif ($key === 'price_list_id') {
            $this->repriceLines();
        } elseif (preg_match('/^lines\.(\d+)\.unit_id$/', $key, $m) && $value) {
            $this->repriceLine((int) $m[1]);
        }
    }

    private function repriceLines(): void
    {
        foreach (array_keys($this->form['lines']) as $i) {
            $this->repriceLine($i);
        }
    }

    private function repriceLine(int $i): void
    {
        $line = &$this->form['lines'][$i];
        $product = $line['product_id'] ? Product::with('units')->find($line['product_id']) : null;

        if ($product && $product->units->firstWhere('unit_id', (int) $line['unit_id'])) {
            $line['unit_price'] = $this->priceOf($product, (int) $line['unit_id']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $data = $this->form;
        $data['lines'] = array_values(array_filter($data['lines'], fn ($l) => ! empty($l['product_id'])));
        foreach ($data['lines'] as &$line) {
            $line['serials'] = array_values(array_filter(array_map('trim', explode(',', (string) ($line['serials'] ?? '')))));
            foreach (['batch_number', 'discount_value', 'tax_id'] as $key) {
                $line[$key] = ($line[$key] ?? '') === '' ? null : $line[$key];
            }
        }
        foreach (['due_date', 'discount_value', 'price_list_id', 'paid_amount'] as $key) {
            $data[$key] = ($data[$key] ?? '') === '' ? null : $data[$key];
        }
        if ($data['paid_amount'] === null) {
            $data['payment_method_id'] = null;
        }

        return $data;
    }

    public function save(InvoiceActions $actions): void
    {
        $data = validator($this->payload(), InvoiceActions::rules())->validate();
        $invoice = $actions->save(auth()->user(), $data, $this->invoiceId ? SalesInvoice::findOrFail($this->invoiceId) : null);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('sales.invoices.show', $invoice->id);
    }

    private function preview(int $scale): ?TotalsResult
    {
        try {
            $taxes = Tax::whereKey(array_filter(array_column($this->form['lines'], 'tax_id')))->get()->keyBy('id');
            $lines = array_map(fn ($l) => new PricedLine(
                ($l['quantity'] ?? '') === '' ? '0' : (string) $l['quantity'],
                ($l['unit_price'] ?? '') === '' ? '0' : (string) $l['unit_price'],
                Discount::fromInput($l['discount_type'] ?? null, $l['discount_value'] ?? null),
                ! empty($l['tax_id']) ? $taxes[$l['tax_id']] ?? null : null,
            ), $this->form['lines']);

            return app(DocumentTotals::class)->calculate($lines, $scale, Discount::fromInput($this->form['discount_type'], $this->form['discount_value']));
        } catch (Throwable) {
            return null; // half-typed numbers; validation reports them on save
        }
    }

    public function render(Currencies $currencies)
    {
        $currency = Currency::find($this->form['currency_id']) ?? $currencies->base();

        return view('sales::livewire.invoices.form', [
            'customers' => Partner::visibleTo(auth()->user())->customers()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'priceLists' => PriceList::where('is_active', true)->get(),
            'warehouses' => $this->warehouses()->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'methods' => PaymentMethod::where('is_active', true)->orderBy('sort')->get(),
            'taxes' => Tax::where('is_active', true)->where('scope', '!=', TaxScope::Purchases)->get(),
            'products' => Product::with('units.unit')->whereKey(array_filter(array_column($this->form['lines'], 'product_id')))->get()->keyBy('id'),
            'isBase' => $currencies->isBase($currency),
            'preview' => $this->preview($currency->decimal_places),
            'scale' => $currency->decimal_places,
        ])->title(__('sales::invoices.new'));
    }
}
