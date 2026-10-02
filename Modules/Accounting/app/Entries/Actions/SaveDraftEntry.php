<?php

namespace Modules\Accounting\Entries\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Enums\JournalType;
use Modules\Accounting\Models\JournalEntry;

/**
 * Creates or updates a manual journal entry as a draft. Drafts need not balance yet;
 * PostDraftEntry applies every posting rule.
 */
class SaveDraftEntry
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'journal_type' => ['required', Rule::in([JournalType::General->value, JournalType::Opening->value])],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:500'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')],
            'lines.*.debit' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'lines.*.credit' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'lines.*.partner_id' => ['nullable', 'integer', Rule::exists('partners', 'id')],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?JournalEntry $draft = null): JournalEntry
    {
        Gate::forUser($actor)->authorize('accounting.entries.create');

        if ($draft !== null && ($draft->isPosted() || $draft->source_type !== null)) {
            throw ValidationException::withMessages(['entry' => __('accounting::entries.not_editable')]);
        }

        if (! $actor->canAccessBranch((int) $data['branch_id'])) {
            throw ValidationException::withMessages(['branch_id' => __('accounting::entries.branch_not_allowed')]);
        }

        return DB::transaction(function () use ($actor, $data, $draft) {
            $draft ??= new JournalEntry(['status' => EntryStatus::Draft, 'created_by' => $actor->id]);
            $draft->fill([
                'date' => $data['date'],
                'branch_id' => $data['branch_id'],
                'journal_type' => $data['journal_type'],
                'description' => $data['description'] ?? null,
            ])->save();

            $draft->lines()->delete();

            $lineNo = 0;
            foreach ($data['lines'] as $line) {
                $debit = (string) ($line['debit'] ?? '') ?: '0';
                $credit = (string) ($line['credit'] ?? '') ?: '0';

                $draft->lines()->create([
                    'line_no' => ++$lineNo,
                    'account_id' => $line['account_id'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'partner_id' => $line['partner_id'] ?? null,
                    'branch_id' => $data['branch_id'],
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $draft;
        });
    }
}
