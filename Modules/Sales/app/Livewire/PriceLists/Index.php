<?php

namespace Modules\Sales\Livewire\PriceLists;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Sales\Models\CustomerProfile;
use Modules\Sales\Models\PriceList;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public function mount(): void
    {
        Gate::authorize('sales.price_lists.manage');
    }

    public function render()
    {
        return view('sales::livewire.price-lists.index', [
            'lists' => PriceList::withCount('items')->orderBy('id')->get(),
            'customers' => CustomerProfile::whereNotNull('price_list_id')->selectRaw('price_list_id, count(*) as total')->groupBy('price_list_id')->pluck('total', 'price_list_id'),
        ])->title(__('sales::price_lists.title'));
    }
}
