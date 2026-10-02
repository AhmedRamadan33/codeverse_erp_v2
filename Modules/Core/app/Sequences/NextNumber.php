<?php

namespace Modules\Core\Sequences;

use DateTimeInterface;
use LogicException;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Sequence;
use Modules\Core\Models\SequenceCounter;
use Modules\Core\Support\Attributes\ModuleApi;
use Modules\Core\Support\TransactionGuard;

/**
 * Assigns the next document number. Called while posting, as the last lock taken
 * (docs/architecture/core-design.md §3.4), so the counter row is held briefly.
 */
#[ModuleApi]
class NextNumber
{
    public function handle(string $key, ?int $branchId, DateTimeInterface $date): string
    {
        TransactionGuard::assertActive('NextNumber');

        $sequence = $this->sequence($key, $branchId);
        $period = $sequence->reset->period($date);

        // Create the period's counter if needed, then lock it; insertOrIgnore is safe under concurrency.
        SequenceCounter::insertOrIgnore([
            'sequence_id' => $sequence->id,
            'period' => $period,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = SequenceCounter::where('sequence_id', $sequence->id)
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();

        $number = $counter->next_number;
        $counter->increment('next_number');

        return $this->format($sequence, $branchId, $date)
            .str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT);
    }

    private function sequence(string $key, ?int $branchId): Sequence
    {
        return Sequence::where('key', $key)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->orderByRaw('branch_id is null') // the branch's own sequence first
            ->first()
            ?? throw new LogicException("No sequence defined for [{$key}]; the owning module's installer must define it.");
    }

    private function format(Sequence $sequence, ?int $branchId, DateTimeInterface $date): string
    {
        return strtr($sequence->prefix, [
            '{branch}' => $branchId ? Branch::whereKey($branchId)->value('code') : '',
            '{yyyy}' => $date->format('Y'),
            '{yy}' => $date->format('y'),
            '{mm}' => $date->format('m'),
        ]);
    }
}
