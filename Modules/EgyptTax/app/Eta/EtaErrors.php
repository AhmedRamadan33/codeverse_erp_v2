<?php

namespace Modules\EgyptTax\Eta;

use Illuminate\Support\Str;

/**
 * Flattens ETA error objects (message, details, nested errors) into readable lines.
 */
class EtaErrors
{
    /**
     * @return string[]
     */
    public static function messages(mixed $error): array
    {
        if (is_string($error)) {
            return [Str::limit($error, 500)];
        }
        if (! is_array($error) || $error === []) {
            return [];
        }
        if (array_is_list($error)) {
            return array_values(array_unique(array_merge(...array_map(self::messages(...), $error))));
        }

        $messages = [];
        foreach (['message', 'error', 'details', 'errors', 'innerError'] as $key) {
            if (isset($error[$key])) {
                array_push($messages, ...self::messages($error[$key]));
            }
        }

        return array_values(array_unique(array_filter($messages)));
    }
}
