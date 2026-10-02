<?php

namespace Modules\Core\Livewire\Partners;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Partner;
use Modules\Core\Partners\Actions\SavePartner;
use Modules\Core\Partners\PartnerData;
use Modules\Core\Partners\PartnerType;

#[Layout('core::layouts.app')]
class Form extends Component
{
    public ?Partner $partner = null;

    /** @var array<string, mixed> snake_case input matching PartnerData::rules() */
    public array $form = [
        'type' => 'company',
        'name' => '',
        'is_customer' => true,
        'is_supplier' => false,
        'tax_number' => null,
        'national_id' => null,
        'commercial_register' => null,
        'phone' => null,
        'email' => null,
        'address' => null,
        'credit_limit' => null,
        'payment_term_days' => 0,
        'branch_id' => null,
        'is_active' => true,
    ];

    public function mount(?int $id = null): void
    {
        if ($id === null) {
            Gate::authorize('core.partners.create');

            return;
        }

        Gate::authorize('core.partners.update');
        $this->partner = Partner::visibleTo(auth()->user())->findOrFail($id);

        foreach (array_keys($this->form) as $key) {
            $value = $this->partner->{$key};
            $this->form[$key] = $value instanceof \BackedEnum ? $value->value : (is_object($value) ? (string) $value : $value);
        }
    }

    public function save(SavePartner $action): void
    {
        $validated = $this->validate($this->prefixed(PartnerData::rules()))['form'];

        $action->handle(auth()->user(), PartnerData::fromArray($validated), $this->partner);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('core.partners.index');
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function prefixed(array $rules): array
    {
        return collect($rules)->mapWithKeys(fn ($rule, $key) => ["form.{$key}" => $rule])->all();
    }

    public function render()
    {
        $user = auth()->user();
        $branches = $user->can('core.branches.all_access') ? Branch::where('is_active', true)->get() : $user->branches;

        return view('core::livewire.partners.form', [
            'types' => PartnerType::cases(),
            'branches' => $branches,
        ])->title($this->partner ? __('core::partners.edit') : __('core::partners.new'));
    }
}
