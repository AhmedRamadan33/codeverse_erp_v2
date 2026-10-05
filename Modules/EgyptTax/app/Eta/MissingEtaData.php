<?php

namespace Modules\EgyptTax\Eta;

use RuntimeException;

/**
 * A receipt cannot be built yet: codes or buyer data are missing. Carries translated messages.
 */
class MissingEtaData extends RuntimeException
{
    /**
     * @param  string[]  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
