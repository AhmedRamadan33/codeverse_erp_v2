<?php

namespace Modules\EgyptTax\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\EgyptTax\Actions\SettingsActions;
use Modules\EgyptTax\Models\EtaSetting;

#[Layout('core::layouts.app')]
class Settings extends Component
{
    /** @var array<string, mixed> */
    public array $form = [];

    public bool $hasSecret = false;

    public function mount(): void
    {
        Gate::authorize('egypttax.settings.manage');

        $settings = EtaSetting::current();
        $this->hasSecret = filled($settings->client_secret);
        $this->form = [
            'environment' => $settings->environment,
            'rin' => $settings->rin,
            'company_trade_name' => $settings->company_trade_name,
            'activity_code' => $settings->activity_code,
            'client_id' => $settings->client_id,
            'client_secret' => '',
            'exempt_tax_type' => $settings->exempt_tax_type,
            'exempt_sub_type' => $settings->exempt_sub_type,
            'is_active' => $settings->is_active,
        ];
    }

    public function save(SettingsActions $actions): void
    {
        $rules = collect(SettingsActions::settingsRules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();
        $data = validator(['form' => $this->form], $rules)->validate()['form'];

        $settings = $actions->saveSettings(auth()->user(), $data);

        $this->form['client_secret'] = '';
        $this->hasSecret = filled($settings->client_secret);
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        return view('egypttax::livewire.settings', ['environments' => EtaSetting::ENVIRONMENTS])
            ->title(__('egypttax::settings.title'));
    }
}
