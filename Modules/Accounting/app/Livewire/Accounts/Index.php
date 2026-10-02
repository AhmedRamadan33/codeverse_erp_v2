<?php

namespace Modules\Accounting\Livewire\Accounts;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Accounting\Accounts\Actions\SaveAccount;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\Account;
use Modules\Core\Models\Currency;

#[Layout('core::layouts.app')]
class Index extends Component
{
    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('accounting.accounts.view');
    }

    public function create(?int $parentId = null): void
    {
        Gate::authorize('accounting.accounts.manage');

        $parent = $parentId ? Account::findOrFail($parentId) : null;
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'code' => $parent ? $this->nextChildCode($parent) : '',
            'name_ar' => '', 'name_en' => '',
            'parent_id' => $parent?->id,
            'type' => $parent?->type->value ?? AccountType::Asset->value,
            'subtype' => null,
            'is_group' => false,
            'currency_id' => null,
            'is_active' => true,
        ];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('accounting.accounts.manage');

        $account = Account::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $account->id;
        $this->form = [
            'code' => $account->code,
            'name_ar' => $account->getTranslation('name', 'ar', false),
            'name_en' => $account->getTranslation('name', 'en', false),
            'parent_id' => $account->parent_id,
            'type' => $account->type->value,
            'subtype' => $account->subtype?->value,
            'is_group' => $account->is_group,
            'currency_id' => $account->currency_id,
            'is_active' => $account->is_active,
        ];
        $this->showForm = true;
    }

    public function updatedFormParentId($parentId): void
    {
        if ($parentId && $parent = Account::find($parentId)) {
            $this->form['type'] = $parent->type->value;
        }
    }

    public function save(SaveAccount $action): void
    {
        $account = $this->editingId ? Account::findOrFail($this->editingId) : null;
        $rules = collect(SaveAccount::rules($account))->mapWithKeys(fn ($r, $k) => ["form.{$k}" => $r])->all();

        $action->handle(auth()->user(), $this->validate($rules)['form'], $account);

        $this->showForm = false;
        session()->flash('status', __('core::ui.saved'));
    }

    public function delete(int $id, SaveAccount $action): void
    {
        $action->delete(auth()->user(), Account::findOrFail($id));
        session()->flash('status', __('core::ui.deleted'));
    }

    private function nextChildCode(Account $parent): string
    {
        $last = Account::where('parent_id', $parent->id)->orderByDesc('code')->value('code');

        if ($last !== null && ctype_digit($last)) {
            return (string) ((int) $last + 1);
        }

        return $parent->code.'01';
    }

    /**
     * Accounts in tree order with their depth, so the list reads like the chart.
     *
     * @return Collection<int, array{account: Account, depth: int}>
     */
    private function tree(Collection $accounts): Collection
    {
        $byParent = $accounts->groupBy(fn (Account $a) => $a->parent_id ?? 0);
        $rows = collect();

        $walk = function (int $parentId, int $depth) use (&$walk, $byParent, $rows) {
            foreach ($byParent->get($parentId, collect())->sortBy('code') as $account) {
                $rows->push(['account' => $account, 'depth' => $depth]);
                $walk($account->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $rows;
    }

    public function render(Ledger $ledger)
    {
        $accounts = Account::orderBy('code')->get();

        $rows = $this->search === ''
            ? $this->tree($accounts)
            : $accounts->filter(fn (Account $a) => str_contains($a->code, $this->search)
                || str_contains(mb_strtolower($a->name), mb_strtolower($this->search)))
                ->map(fn (Account $a) => ['account' => $a, 'depth' => 0])->values();

        $leafBalances = $ledger->lines()->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, sum(debit) - sum(credit) as balance')
            ->pluck('balance', 'account_id');

        // Roll leaf balances up to every ancestor so group accounts show their totals.
        $balances = [];
        $parents = $accounts->pluck('parent_id', 'id');
        foreach ($leafBalances as $accountId => $balance) {
            for ($id = $accountId; $id !== null; $id = $parents[$id] ?? null) {
                $balances[$id] = \Brick\Math\BigDecimal::of($balances[$id] ?? 0)->plus($balance);
            }
        }

        return view('accounting::livewire.accounts.index', [
            'rows' => $rows,
            'balances' => $balances,
            'groups' => $accounts->where('is_group', true),
            'types' => AccountType::cases(),
            'subtypes' => AccountSubtype::forType(AccountType::from($this->form['type'] ?? 'asset')),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
        ])->title(__('accounting::menu.accounts'));
    }
}
