<?php

namespace Modules\Accounting\Livewire\Entries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Accounting\Entries\Actions\DeleteDraftEntry;
use Modules\Accounting\Entries\Actions\PostDraftEntry;
use Modules\Accounting\Entries\Actions\ReverseManualEntry;
use Modules\Accounting\Models\JournalEntry;

#[Layout('core::layouts.app')]
class Show extends Component
{
    public int $entryId;

    public bool $showReverse = false;

    public string $reverseDate = '';

    public string $reverseReason = '';

    public function mount(int $id): void
    {
        Gate::authorize('accounting.entries.view');

        $entry = JournalEntry::findOrFail($id);
        abort_unless(auth()->user()->canAccessBranch($entry->branch_id), 404);

        $this->entryId = $entry->id;
        $this->reverseDate = now()->toDateString();
    }

    private function entry(): JournalEntry
    {
        return JournalEntry::findOrFail($this->entryId);
    }

    public function post(PostDraftEntry $action): void
    {
        $entry = $action->handle(auth()->user(), $this->entry());
        session()->flash('status', __('accounting::entries.posted', ['number' => $entry->number]));
    }

    public function reverse(ReverseManualEntry $action): void
    {
        $data = $this->validate([
            'reverseDate' => ['required', 'date'],
            'reverseReason' => ['required', 'string', 'max:255'],
        ]);

        $reversal = $action->handle(auth()->user(), $this->entry(), CarbonImmutable::parse($data['reverseDate']), $data['reverseReason']);

        session()->flash('status', __('accounting::entries.reversed', ['number' => $reversal->number]));
        $this->redirectRoute('accounting.entries.show', $reversal->id);
    }

    public function delete(DeleteDraftEntry $action): void
    {
        $action->handle(auth()->user(), $this->entry());
        session()->flash('status', __('core::ui.deleted'));
        $this->redirectRoute('accounting.entries.index');
    }

    public function render()
    {
        $entry = JournalEntry::with(['lines.account', 'lines.partner', 'branch', 'creator', 'poster', 'reversalOf', 'reversedBy'])->findOrFail($this->entryId);

        return view('accounting::livewire.entries.show', [
            'entry' => $entry,
            'totalDebit' => $entry->lines->reduce(fn ($c, $l) => $c->plus($l->debit), \Brick\Math\BigDecimal::zero()),
            'totalCredit' => $entry->lines->reduce(fn ($c, $l) => $c->plus($l->credit), \Brick\Math\BigDecimal::zero()),
        ])->title($entry->number ? __('accounting::entries.show', ['number' => $entry->number]) : __('accounting::entries.draft'));
    }
}
