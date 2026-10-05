<?php

namespace Modules\EgyptTax\Eta;

/**
 * ETA's canonical serialization of a document, hashed to give a receipt its uuid.
 *
 * Every property is written as its upper-cased name in quotes followed by its value; a
 * scalar value is quoted as it appears in the JSON, an object is serialized recursively,
 * and each element of an array repeats the array's name before its own serialization.
 */
class Serializer
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function uuid(array $document): string
    {
        $document['header']['uuid'] = '';

        return hash('sha256', $this->serialize($document));
    }

    /**
     * @param  array<mixed>  $data
     */
    public function serialize(array $data): string
    {
        $result = '';

        foreach ($data as $key => $value) {
            $name = '"'.strtoupper((string) $key).'"';

            if (is_array($value) && array_is_list($value)) {
                $result .= $name;
                foreach ($value as $item) {
                    $result .= $name.(is_array($item) ? $this->serialize($item) : $this->scalar($item));
                }
            } elseif (is_array($value)) {
                $result .= $name.$this->serialize($value);
            } else {
                $result .= $name.$this->scalar($value);
            }
        }

        return $result;
    }

    /**
     * Numbers as json_encode writes them, so the hash matches the JSON that is sent.
     */
    private function scalar(mixed $value): string
    {
        return '"'.(is_string($value) ? $value : json_encode($value)).'"';
    }
}
