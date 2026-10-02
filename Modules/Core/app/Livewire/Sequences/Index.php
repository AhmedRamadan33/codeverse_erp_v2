<?php

namespace Modules\Core\Livewire\Sequences;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Sequence;
use Modules\Core\Sequences\Actions\SaveSequence;
use Modules\Core\Sequences\SequenceReset;

#[Layout('core::layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('core.sequences.manage');
    }

    public function edit(int $id): void
    {
        $sequence = Sequence::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $sequence->id;
        $this->form = [
            'key' => $sequence->key,
            'branch_id' => $sequence->branch_id,
            'prefix' => $sequence->prefix,
            'padding' => $sequence->padding,
            'reset' => $sequence->reset->value,
        ];
        $this->showForm = true;
    }

    /**
     * Start a branch-specific sequence copied from the shared one.
     */
    public function addBranchOverride(int $id): void
    {
        $this->edit($id);
        $this->editingId = null;
        $this->form['branch_id'] = null;
        $this->form['prefix'] = '{branch}-'.$this->form['prefix'];
    }

    public function save(SaveSequence $action): void
    {
        $rules = collect(SaveSequence::rules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        if ($this->editingId === null) {
            $rules['form.branch_id'] = ['required', 'integer', 'exists:branches,id'];
        }

        $data = $this->validate($rules)['form'];

        if ($this->editingId === null && Sequence::where('key', $data['key'])->where('branch_id', $data['branch_id'])->exists()) {
            $this->addError('form.branch_id', __('validation.unique', ['attribute' => __('core::sequences.fields.branch')]));

            return;
        }

        $action->handle(auth()->user(), $data, $this->editingId ? Sequence::findOrFail($this->editingId) : null);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public static function label(string $key): string
    {
        [$module, $document] = explode('.', $key, 2);
        $translation = "{$module}::sequences.keys.{$document}";

        return __($translation) === $translation ? $key : __($translation);
    }

    public function render()
    {
        return view('core::livewire.sequences.index', [
            'sequences' => Sequence::with('branch')->orderBy('key')->orderBy('branch_id')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('code')->get(),
            'resets' => SequenceReset::cases(),
        ])->title(__('core::menu.sequences'));
    }
}
