<?php

namespace Modules\Accounting\Livewire\Entries;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Entries\Actions\SaveDraftEntry;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Partner;
use Throwable;

#[Layout('core::layouts.app')]
class Form extends Component
{
    public ?JournalEntry $entry = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(?int $id = null): void
    {
        Gate::authorize('accounting.entries.create');

        $user = auth()->user();

        if ($id === null) {
            $this->form = [
                'date' => now()->toDateString(),
                'branch_id' => $user->branches()->wherePivot('is_default', true)->value('branches.id') ?? $user->branches()->value('branches.id'),
                'journal_type' => JournalType::General->value,
                'description' => '',
                'lines' => [$this->blankLine(), $this->blankLine()],
            ];

            return;
        }

        $this->entry = JournalEntry::with('lines')->whereNull('source_type')->findOrFail($id);
        abort_if($this->entry->isPosted(), 404);

        $this->form = [
            'date' => $this->entry->date->toDateString(),
            'branch_id' => $this->entry->branch_id,
            'journal_type' => $this->entry->journal_type->value,
            'description' => $this->entry->description,
            'lines' => $this->entry->lines->map(fn (JournalLine $line) => [
                'account_id' => $line->account_id,
                'debit' => $line->debit->isZero() ? '' : (string) $line->debit->strippedOfTrailingZeros(),
                'credit' => $line->credit->isZero() ? '' : (string) $line->credit->strippedOfTrailingZeros(),
                'partner_id' => $line->partner_id,
                'description' => $line->description,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankLine(): array
    {
        return ['account_id' => null, 'debit' => '', 'credit' => '', 'partner_id' => null, 'description' => ''];
    }

    public function addLine(): void
    {
        $this->form['lines'][] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->form['lines'][$index]);
        $this->form['lines'] = array_values($this->form['lines']);
    }

    public function save(SaveDraftEntry $action): void
    {
        // Lines left completely empty are dropped before validation.
        $this->form['lines'] = array_values(array_filter($this->form['lines'], fn (array $l) => ! empty($l['account_id'])
            || ($l['debit'] ?? '') !== '' || ($l['credit'] ?? '') !== ''));

        $rules = collect(SaveDraftEntry::rules())->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();
        $data = $this->validate($rules)['form'];

        $entry = $action->handle(auth()->user(), $data, $this->entry);

        session()->flash('status', __('core::ui.saved'));
        $this->redirectRoute('accounting.entries.show', $entry->id);
    }

    /**
     * @return array{debit: BigDecimal, credit: BigDecimal}
     */
    private function totals(): array
    {
        $sum = fn (string $side) => collect($this->form['lines'])->reduce(function (BigDecimal $carry, array $line) use ($side) {
            try {
                return $carry->plus(BigDecimal::of($line[$side] !== '' && $line[$side] !== null ? $line[$side] : 0));
            } catch (Throwable) {
                return $carry; // half-typed number; validation reports it on save
            }
        }, BigDecimal::zero());

        return ['debit' => $sum('debit'), 'credit' => $sum('credit')];
    }

    public function render()
    {
        $user = auth()->user();

        return view('accounting::livewire.entries.form', [
            'accounts' => Account::where('is_active', true)->where('is_group', false)->orderBy('code')->get(),
            'partners' => Partner::visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $user->can('core.branches.all_access') ? Branch::where('is_active', true)->get() : $user->branches,
            'types' => [JournalType::General, JournalType::Opening],
            'totals' => $this->totals(),
        ])->title($this->entry ? __('accounting::entries.edit') : __('accounting::entries.new'));
    }
}
