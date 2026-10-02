<?php

namespace Modules\Accounting\Exceptions;

use LogicException;
use Modules\Accounting\Models\JournalEntry;

/**
 * A programming error: posted entries are corrected by reversal, never edited.
 */
class PostedEntryImmutable extends LogicException
{
    public function __construct(?JournalEntry $entry)
    {
        parent::__construct("Journal entry [{$entry?->number}] is posted and cannot be changed; reverse it instead.");
    }
}
