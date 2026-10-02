<?php

namespace Modules\Accounting\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * A business rule stopped a posting (unbalanced, closed period, inactive account...).
 * It is a ValidationException so the web UI and the API show it like any input error.
 */
class PostingException extends ValidationException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function because(string $key, array $replace = [], string $field = 'entry'): self
    {
        $exception = static::withMessages([$field => __('accounting::posting.'.$key, $replace)]);

        return $exception;
    }
}
