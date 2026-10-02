<?php

namespace Modules\Core\Livewire\Settings;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Settings\Actions\SaveCompanySettings;
use Modules\Core\Settings\Settings;

#[Layout('core::layouts.app')]
class Company extends Component
{
    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(Settings $settings): void
    {
        Gate::authorize('core.settings.manage');

        foreach (SaveCompanySettings::KEYS as $key) {
            $this->form[$key] = $settings->get("core.{$key}");
        }
    }

    public function save(SaveCompanySettings $action): void
    {
        $rules = collect(SaveCompanySettings::rules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        $action->handle(auth()->user(), $this->validate($rules)['form']);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('core.settings.edit');
    }

    public function render(Settings $settings)
    {
        return view('core::livewire.settings.company', ['baseCurrency' => $settings->get('core.base_currency')])
            ->title(__('core::menu.settings'));
    }
}
