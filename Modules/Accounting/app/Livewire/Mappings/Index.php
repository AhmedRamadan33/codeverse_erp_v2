<?php

namespace Modules\Accounting\Livewire\Mappings;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Mappings\Actions\SaveMapping;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;

/**
 * Default accounts per purpose. Scoped overrides (per category, partner, ...) are set
 * from the screens of the things they scope to, as those modules arrive.
 */
#[Layout('core::layouts.app')]
class Index extends Component
{
    /** @var array<int, int|string> mapping id => account id */
    public array $selected = [];

    public function mount(): void
    {
        Gate::authorize('accounting.mappings.manage');

        $this->selected = AccountMapping::whereNull('scope_type')->pluck('account_id', 'id')->all();
    }

    public function save(int $mappingId, SaveMapping $action): void
    {
        $action->handle(auth()->user(), AccountMapping::findOrFail($mappingId), (int) $this->selected[$mappingId]);
        session()->flash('status', __('core::ui.saved'));
    }

    public static function label(string $key): string
    {
        $translation = 'accounting::mappings.keys.'.$key;

        return __($translation) === $translation ? $key : __($translation);
    }

    public function render()
    {
        return view('accounting::livewire.mappings.index', [
            'mappings' => AccountMapping::with('account')->whereNull('scope_type')->orderBy('key')->get(),
            'accounts' => Account::where('is_active', true)->where('is_group', false)->orderBy('code')->get(),
        ])->title(__('accounting::menu.mappings'));
    }
}
