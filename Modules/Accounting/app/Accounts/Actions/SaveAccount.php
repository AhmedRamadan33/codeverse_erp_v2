<?php

namespace Modules\Accounting\Accounts\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;

/**
 * Creates or edits an account while keeping the chart consistent with what is already posted.
 */
class SaveAccount
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?Account $account = null): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[0-9A-Za-z.\-]+$/', Rule::unique('accounts', 'code')->ignore($account)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('is_group', true)],
            'type' => ['required', Rule::enum(AccountType::class)],
            'subtype' => ['nullable', Rule::enum(AccountSubtype::class)],
            'is_group' => ['boolean'],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?Account $account = null): Account
    {
        Gate::forUser($actor)->authorize('accounting.accounts.manage');

        $type = AccountType::from($data['type']);
        $subtype = ! empty($data['subtype']) ? AccountSubtype::from($data['subtype']) : null;
        $isGroup = (bool) ($data['is_group'] ?? false);
        $isActive = (bool) ($data['is_active'] ?? true);
        $parent = ! empty($data['parent_id']) ? Account::find($data['parent_id']) : null;
        $hasLines = $account?->lines()->exists() ?? false;

        $fail = fn (string $field, string $key) => throw ValidationException::withMessages([$field => __('accounting::accounts.errors.'.$key)]);

        if ($parent && $parent->type !== $type) {
            $fail('parent_id', 'parent_type');
        }

        if ($parent && $account && ($parent->is($account) || $this->isDescendant($parent, $account))) {
            $fail('parent_id', 'parent_cycle');
        }

        if ($subtype && $subtype->type() !== $type) {
            $fail('subtype', 'subtype_type');
        }

        if ($isGroup && $subtype) {
            $fail('subtype', 'group_subtype');
        }

        if ($account && $hasLines && ($account->type !== $type || $isGroup)) {
            $fail('type', 'has_entries');
        }

        if ($account && ! $isGroup && $account->is_group && $account->children()->exists()) {
            $fail('is_group', 'has_children');
        }

        if ($account && ! $isActive && AccountMapping::where('account_id', $account->id)->exists()) {
            $fail('is_active', 'mapped');
        }

        return DB::transaction(function () use ($data, $account, $type, $subtype, $isGroup, $isActive) {
            $account ??= new Account;
            $account->fill([
                'code' => $data['code'],
                'name' => array_filter(['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? null]),
                'parent_id' => $data['parent_id'] ?? null,
                'type' => $type,
                'subtype' => $subtype,
                'is_group' => $isGroup,
                'currency_id' => $isGroup ? null : ($data['currency_id'] ?? null),
                'requires_partner' => $subtype?->requiresPartner() ?? false,
                'is_active' => $isActive,
            ])->save();

            return $account;
        });
    }

    public function delete(User $actor, Account $account): void
    {
        Gate::forUser($actor)->authorize('accounting.accounts.manage');

        $inUse = $account->is_system
            || $account->lines()->exists()
            || $account->children()->exists()
            || AccountMapping::where('account_id', $account->id)->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['account' => __('accounting::accounts.errors.in_use')]);
        }

        DB::transaction(fn () => $account->delete());
    }

    private function isDescendant(Account $candidate, Account $ancestor): bool
    {
        for ($node = $candidate->parent; $node !== null; $node = $node->parent) {
            if ($node->is($ancestor)) {
                return true;
            }
        }

        return false;
    }
}
