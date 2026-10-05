<?php

namespace Modules\EgyptTax\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\EgyptTax\Actions\SettingsActions;
use Modules\EgyptTax\Models\EtaDevice;
use Modules\Pos\Models\Register;

#[Layout('core::layouts.app')]
class Devices extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $hasKey = false;

    public bool $hasSecret = false;

    public function mount(): void
    {
        Gate::authorize('egypttax.settings.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->hasKey = $this->hasSecret = false;
        $this->form = [
            'register_id' => null, 'serial' => '', 'os_version' => 'windows', 'model_framework' => '', 'pre_shared_key' => '',
            'client_id' => '', 'client_secret' => '', 'branch_code' => '0', 'is_active' => true,
            'address' => array_merge(array_fill_keys(EtaDevice::ADDRESS_FIELDS, ''), ['country' => 'EG']),
        ];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $device = EtaDevice::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $device->id;
        $this->hasKey = filled($device->pre_shared_key);
        $this->hasSecret = filled($device->client_secret);
        $this->form = [
            'register_id' => $device->register_id,
            'serial' => $device->serial,
            'os_version' => $device->os_version,
            'model_framework' => $device->model_framework,
            'pre_shared_key' => '',
            'client_id' => $device->client_id,
            'client_secret' => '',
            'branch_code' => $device->branch_code,
            'is_active' => $device->is_active,
            'address' => array_merge(array_fill_keys(EtaDevice::ADDRESS_FIELDS, ''), $device->address),
        ];
        $this->showForm = true;
    }

    public function save(SettingsActions $actions): void
    {
        $device = $this->editingId ? EtaDevice::findOrFail($this->editingId) : null;
        $rules = collect(SettingsActions::deviceRules($device))->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        $actions->saveDevice(auth()->user(), validator(['form' => $this->form], $rules)->validate()['form'], $device);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public function render()
    {
        return view('egypttax::livewire.devices', [
            'devices' => EtaDevice::with('register')->orderBy('id')->get(),
            'registers' => Register::orderBy('code')->get(),
            'addressFields' => EtaDevice::ADDRESS_FIELDS,
        ])->title(__('egypttax::devices.title'));
    }
}
