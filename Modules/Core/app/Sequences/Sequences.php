<?php

namespace Modules\Core\Sequences;

use Modules\Core\Models\Sequence;
use Modules\Core\Support\Attributes\ModuleApi;

/**
 * Used by module installers to define their default numbering.
 */
#[ModuleApi]
class Sequences
{
    /**
     * Create the installation-wide sequence for a key unless it already exists
     * (an installation may have customised it).
     */
    public function define(string $key, string $prefix, int $padding = 5, SequenceReset $reset = SequenceReset::Yearly): Sequence
    {
        return Sequence::firstOrCreate(
            ['key' => $key, 'branch_id' => null],
            ['prefix' => $prefix, 'padding' => $padding, 'reset' => $reset],
        );
    }
}
