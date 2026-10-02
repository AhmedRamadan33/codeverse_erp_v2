<?php

namespace Modules\Core\Modules;

use RuntimeException;

class ModuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function make(string $key, array $replace = []): self
    {
        return new self(__('core::modules.'.$key, $replace));
    }
}
