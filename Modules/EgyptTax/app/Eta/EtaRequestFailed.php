<?php

namespace Modules\EgyptTax\Eta;

use RuntimeException;

/**
 * An ETA request failed (network, authentication, server or refused submission). The message
 * never contains credentials or tokens.
 */
class EtaRequestFailed extends RuntimeException
{
    /**
     * @param  int|null  $retryAfter  seconds ETA asked to wait (duplicate submission)
     */
    public function __construct(string $message, public readonly ?int $status = null, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
