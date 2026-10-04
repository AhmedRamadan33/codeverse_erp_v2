<?php

namespace Modules\Pos\Livewire;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Pricing\Discount;
use Modules\Accounting\Pricing\DocumentTotals;
use Modules\Accounting\Pricing\PricedLine;
use Modules\Accounting\Pricing\TotalsResult;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Models\Partner;
use Modules\Core\Settings\Settings;
use Modules\Pos\Actions\ReceiptActions;
use Modules\Pos\Actions\ShiftActions;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Models\Register;
use Modules\Pos\Models\Shift;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductLookup;
use Modules\Sales\Pricing\PriceResolver;
use Throwable;

/**
 * The selling screen: scan or search products into the cart, take payments, complete.
 * Without an open shift it asks for a register and the opening cash first.
 */
#[Layout('core::layouts.app')]
class Terminal extends Component
{
    public ?int $shiftId = null;

    /** @var array{register_id: int|null, opening_float: string} */
    public array $opening = ['register_id' => null, 'opening_float' => '0'];

    public string $search = '';

    /** @var array<int, array<string, mixed>> */
    public array $cart = [];

    public ?int $partnerId = null;

    public string $discountType = 'percent';

    public string $discountValue = '';

    /** @var array<int, array{payment_method_id: int, amount: string}> */
    public array $payments = [];

    public ?string $overLimitWarning = null;

    public ?int $lastReceiptId = null;

    public function mount(ShiftActions $shifts, Settings $settings): void
    {
        Gate::authorize('pos.terminal.sell');

        $this->shiftId = $shifts->current(auth()->user())?->id;
        $this->partnerId = $settings->get('sales.walk_in_partner_id');
        $this->opening['register_id'] = $this->registers()->first()?->id;
    }

    private function registers()
    {
        $user = auth()->user();

        return Register::where('is_active', true)
            ->whereDoesntHave('shifts', fn ($q) => $q->where('status', 'open'))
            ->when(! $user->can('core.branches.all_access'), fn ($q) => $q->whereIn('branch_id', $user->branches()->select('branches.id')))
            ->orderBy('code')
            ->get();
    }

    public function openShift(ShiftActions $shifts): void
    {
        $this->validate([
            'opening.register_id' => ['required', 'integer'],
            'opening.opening_float' => ['required', 'decimal:0,4', 'min:0'],
        ]);

        $shift = $shifts->open(auth()->user(), Register::findOrFail($this->opening['register_id']), (string) $this->opening['opening_float']);
        $this->shiftId = $shift->id;
        session()->flash('status', __('pos::shifts.opened', ['number' => $shift->number]));
    }

    private function shift(): ?Shift
    {
        return $this->shiftId ? Shift::with('register')->find($this->shiftId) : null;
    }

    public function updatedSearch(): void
    {
        $this->resetErrorBag('search');
    }

    /**
     * Enter in the scan box: an exact barcode adds its unit, otherwise the only/first match.
     */
    public function scan(ProductLookup $lookup): void
    {
        $term = trim($this->search);
        if ($term === '') {
            return;
        }

        if ($hit = $lookup->byBarcode($term)) {
            $this->add($hit['product']->id, $hit['unit']->unit_id);

            return;
        }

        $first = $lookup->search($term)->orderBy('sku')->first();
        $first ? $this->add($first->id) : $this->addError('search', __('pos::terminal.not_found'));
    }

    public function add(int $productId, ?int $unitId = null): void
    {
        $product = Product::with('units')->findOrFail($productId);
        $unitId ??= $product->units->firstWhere('is_default_sale', true)?->unit_id ?? $product->base_unit_id;

        foreach ($this->cart as $i => $line) {
            if ($line['product_id'] === $product->id && $line['unit_id'] === $unitId && ($line['serials'] ?? '') === '') {
                $this->cart[$i]['quantity'] = (string) BigDecimal::of($line['quantity'])->plus(1)->strippedOfTrailingZeros();
                $this->search = '';

                return;
            }
        }

        $this->cart[] = [
            'product_id' => $product->id, 'unit_id' => $unitId, 'quantity' => '1',
            'unit_price' => $this->priceOf($product, $unitId), 'discount_type' => 'percent', 'discount_value' => '', 'serials' => '',
        ];
        $this->search = '';
        $this->payments = [];
    }

    private function priceOf(Product $product, int $unitId): string
    {
        $prices = app(PriceResolver::class);
        $listId = $prices->priceListFor($this->partnerId) ?? $this->shift()?->register->price_list_id;

        return (string) $prices->price($product, $unitId, $listId)->strippedOfTrailingZeros();
    }

    public function increment(int $i, int $by): void
    {
        $quantity = BigDecimal::of($this->cart[$i]['quantity'] ?: '0')->plus($by);

        if (! $quantity->isPositive()) {
            $this->remove($i);

            return;
        }

        $this->cart[$i]['quantity'] = (string) $quantity->strippedOfTrailingZeros();
        $this->payments = [];
    }

    public function remove(int $i): void
    {
        unset($this->cart[$i]);
        $this->cart = array_values($this->cart);
        $this->payments = [];
    }

    /**
     * A new unit or customer re-prices from the price list.
     */
    public function updated(string $property): void
    {
        if (preg_match('/^cart\.(\d+)\.unit_id$/', $property, $m)) {
            $line = &$this->cart[(int) $m[1]];
            $line['unit_id'] = (int) $line['unit_id'];
            $line['unit_price'] = $this->priceOf(Product::with('units')->findOrFail($line['product_id']), $line['unit_id']);
        } elseif ($property === 'partnerId') {
            foreach ($this->cart as $i => $line) {
                $this->cart[$i]['unit_price'] = $this->priceOf(Product::with('units')->findOrFail($line['product_id']), (int) $line['unit_id']);
            }
        }

        if (str_starts_with($property, 'cart') || in_array($property, ['partnerId', 'discountValue', 'discountType'], true)) {
            $this->payments = [];
            $this->overLimitWarning = null;
        }
    }

    /**
     * Adds a payment row for what is still due (or the whole total for the first one).
     */
    public function pay(int $methodId, Currencies $currencies): void
    {
        $due = $this->due($currencies->base()->decimal_places);
        $this->payments[] = ['payment_method_id' => $methodId, 'amount' => (string) ($due->isPositive() ? $due : BigDecimal::zero())];
    }

    public function removePayment(int $i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments);
    }

    private function due(int $scale): BigDecimal
    {
        $total = $this->preview($scale)?->total() ?? BigDecimal::zero();

        return $total->minus($this->paid())->toScale($scale);
    }

    private function paid(): BigDecimal
    {
        return array_reduce($this->payments, function (BigDecimal $sum, array $p) {
            try {
                return $sum->plus(BigDecimal::of($p['amount'] === '' ? '0' : $p['amount']));
            } catch (Throwable) {
                return $sum;
            }
        }, BigDecimal::zero());
    }

    public function complete(ReceiptActions $actions, bool $confirmOverLimit = false): void
    {
        $data = [
            'partner_id' => $this->partnerId,
            'discount_type' => $this->discountValue === '' ? null : $this->discountType,
            'discount_value' => $this->discountValue === '' ? null : $this->discountValue,
            'lines' => array_map(fn ($l) => [
                'product_id' => $l['product_id'], 'unit_id' => $l['unit_id'], 'quantity' => $l['quantity'], 'unit_price' => $l['unit_price'],
                'discount_type' => $l['discount_value'] === '' ? null : $l['discount_type'],
                'discount_value' => $l['discount_value'] === '' ? null : $l['discount_value'],
                'serials' => array_values(array_filter(array_map('trim', explode(',', (string) $l['serials'])))),
            ], $this->cart),
            'payments' => array_values(array_filter($this->payments, fn ($p) => $p['amount'] !== '' && $p['amount'] !== '0')),
            'confirm_over_limit' => $confirmOverLimit,
        ];

        try {
            $receipt = $actions->sell(auth()->user(), validator($data, ReceiptActions::saleRules())->validate());
        } catch (ValidationException $e) {
            if ($warning = $e->errors()['credit_limit_confirm'][0] ?? null) {
                $this->overLimitWarning = $warning;

                return;
            }

            throw $e;
        }

        $this->lastReceiptId = $receipt->id;
        $this->cart = [];
        $this->payments = [];
        $this->discountValue = '';
        $this->overLimitWarning = null;
        $this->partnerId = app(Settings::class)->get('sales.walk_in_partner_id');
        $this->dispatch('receipt-reset');
    }

    private function preview(int $scale): ?TotalsResult
    {
        if ($this->cart === []) {
            return null;
        }

        try {
            $products = Product::with('saleTax')->whereKey(array_column($this->cart, 'product_id'))->get()->keyBy('id');
            $lines = array_map(fn ($l) => new PricedLine(
                (string) ($l['quantity'] ?: '0'),
                (string) ($l['unit_price'] === '' ? '0' : $l['unit_price']),
                Discount::fromInput($l['discount_type'], $l['discount_value']),
                $products[$l['product_id']]->saleTax,
            ), $this->cart);

            return app(DocumentTotals::class)->calculate($lines, $scale, Discount::fromInput($this->discountType, $this->discountValue));
        } catch (Throwable) {
            return null; // half-typed numbers; the action validates on completion
        }
    }

    public function render(Currencies $currencies, ProductLookup $lookup)
    {
        $shift = $this->shift();
        $scale = $currencies->base()->decimal_places;
        $preview = $this->preview($scale);

        if ($shift === null) {
            return view('pos::livewire.open-shift', ['registers' => $this->registers()])->title(__('pos::shifts.open'));
        }

        $term = trim($this->search);

        return view('pos::livewire.terminal', [
            'shift' => $shift,
            'products' => Product::with('units.unit')->whereKey(array_column($this->cart, 'product_id'))->get()->keyBy('id'),
            'results' => mb_strlen($term) >= 2 ? $lookup->search($term)->orderBy('sku')->limit(8)->get() : collect(),
            'customers' => Partner::visibleTo(auth()->user())->customers()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::where('is_active', true)->orderBy('sort')->get(),
            'preview' => $preview,
            'paid' => $this->paid(),
            'due' => $preview ? $preview->total()->minus($this->paid()) : BigDecimal::zero(),
            'scale' => $scale,
            'lastReceipt' => $this->lastReceiptId ? Receipt::find($this->lastReceiptId) : null,
            'canPrice' => auth()->user()->can('pos.prices.override'),
            'canDiscount' => auth()->user()->can('pos.discounts.give'),
        ])->title(__('pos::menu.terminal'));
    }
}
