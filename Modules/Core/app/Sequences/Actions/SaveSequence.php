<?php

namespace Modules\Core\Sequences\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Sequence;
use Modules\Core\Sequences\SequenceReset;

/**
 * Edits a sequence's format, or adds a branch-specific sequence for an existing key.
 * Counters are never edited here: numbers already issued must stay unique.
 */
class SaveSequence
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'key' => ['required', 'string', Rule::exists('sequences', 'key')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'prefix' => ['nullable', 'string', 'max:64', 'regex:/^[^{}]*(\{(branch|yyyy|yy|mm)\}[^{}]*)*$/'],
            'padding' => ['required', 'integer', 'min:1', 'max:12'],
            'reset' => ['required', Rule::enum(SequenceReset::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     */
    public function handle(User $actor, array $data, ?Sequence $sequence = null): Sequence
    {
        Gate::forUser($actor)->authorize('core.sequences.manage');

        $attributes = [
            'prefix' => $data['prefix'] ?? '',
            'padding' => (int) $data['padding'],
            'reset' => SequenceReset::from($data['reset']),
        ];

        // A shared sequence numbers every branch; a yearly or monthly reset without a
        // year/month token in the prefix would issue the same number twice.
        if ($attributes['reset'] !== SequenceReset::Never && ! str_contains($attributes['prefix'], '{yy')) {
            throw ValidationException::withMessages(['prefix' => __('core::sequences.reset_needs_year')]);
        }

        if ($attributes['reset'] === SequenceReset::Monthly && ! str_contains($attributes['prefix'], '{mm}')) {
            throw ValidationException::withMessages(['prefix' => __('core::sequences.reset_needs_month')]);
        }

        return DB::transaction(function () use ($data, $sequence, $attributes) {
            if ($sequence !== null) {
                $sequence->update($attributes);

                return $sequence;
            }

            return Sequence::create($attributes + ['key' => $data['key'], 'branch_id' => $data['branch_id'] ?? null]);
        });
    }
}
