<?php

namespace Modules\Core\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('core::layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        return view('core::livewire.dashboard')->title(__('core::menu.dashboard'));
    }
}
