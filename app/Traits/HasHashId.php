<?php

namespace App\Traits;

use Vinkla\Hashids\Facades\Hashids;

trait HasHashId
{
    /**
     * Get the encoded hashid attribute.
     */
    public function getHashidAttribute(): string
    {
        return Hashids::encode($this->getKey());
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field) {
            return parent::resolveRouteBinding($value, $field);
        }

        // Try decoding the hashid, if it's an integer fallback to normal ID logic just in case
        $decoded = Hashids::decode($value);
        $id = !empty($decoded) ? $decoded[0] : $value;

        return $this->where($this->getRouteKeyName(), $id)->first();
    }
}
