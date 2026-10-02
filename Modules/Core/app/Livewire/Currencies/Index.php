<?php

namespace Modules\Core\Livewire\Currencies;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Currencies\Actions\UpdateCurrency;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Models\Currency;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public ?int $editingId = null;

    public string $symbol = '';

    public function mount(): void
    {
        Gate::authorize('core.currencies.manage');
    }

    public function toggle(int $id, UpdateCurrency $action): void
    {
        $currency = Currency::findOrFail($id);
        $action->handle(auth()->user(), $currency, ['symbol' => $currency->symbol, 'is_active' => ! $currency->is_active]);
    }

    public function edit(int $id): void
    {
        $this->editingId = $id;
        $this->symbol = Currency::findOrFail($id)->symbol;
    }

    public function save(UpdateCurrency $action): void
    {
        $currency = Currency::findOrFail($this->editingId);
        $data = $this->validate(['symbol' => UpdateCurrency::rules()['symbol']]);

        $action->handle(auth()->user(), $currency, $data);
        $this->editingId = null;
    }

    public function render(Currencies $currencies)
    {
        return view('core::livewire.currencies.index', [
            'currencies' => Currency::orderByDesc('is_active')->orderBy('code')->get(),
            'baseCode' => $currencies->base()->code,
        ])->title(__('core::menu.currencies'));
    }
}
