<?php

namespace Modules\Core\Livewire\ExchangeRates;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Currencies\Actions\SaveExchangeRate;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Models\Currency;
use Modules\Core\Models\ExchangeRate;

#[Layout('core::layouts.app')]
class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public ?int $currencyFilter = null;

    /** @var array<string, mixed> */
    public array $form = ['currency_id' => null, 'date' => null, 'rate' => ''];

    public function mount(): void
    {
        Gate::authorize('core.exchange_rates.manage');

        $this->form['date'] = now()->toDateString();
    }

    public function save(SaveExchangeRate $action): void
    {
        $rules = collect(SaveExchangeRate::rules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        $action->handle(auth()->user(), $this->validate($rules)['form']);

        $this->form['rate'] = '';
        session()->flash('status', __('core::ui.saved'));
    }

    public function delete(int $id, SaveExchangeRate $action): void
    {
        $action->delete(auth()->user(), ExchangeRate::findOrFail($id));
    }

    public function render(Currencies $currencies)
    {
        $foreign = Currency::where('is_active', true)->where('code', '!=', $currencies->base()->code)->orderBy('code')->get();

        $rates = ExchangeRate::with('currency')
            ->when($this->currencyFilter, fn ($q, $id) => $q->where('currency_id', $id))
            ->orderByDesc('date')->orderBy('currency_id')
            ->paginate(30);

        return view('core::livewire.exchange-rates.index', [
            'foreign' => $foreign,
            'rates' => $rates,
            'baseCode' => $currencies->base()->code,
        ])->title(__('core::menu.exchange_rates'));
    }
}
