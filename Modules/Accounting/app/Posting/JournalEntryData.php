<?php

namespace Modules\Accounting\Posting;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Enums\JournalType;

final readonly class JournalEntryData
{
    /**
     * @param  JournalLineData[]  $lines
     * @param  User|null  $postedBy  the user whose action posts the entry; used for the lock-date permission
     */
    public function __construct(
        public CarbonImmutable $date,
        public int $branchId,
        public JournalType $journalType,
        public array $lines,
        public ?string $description = null,
        public ?Model $source = null,
        public ?User $postedBy = null,
        public ?int $reversalOfId = null,
    ) {}
}
