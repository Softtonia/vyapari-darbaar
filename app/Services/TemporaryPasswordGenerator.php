<?php

namespace App\Services;

use Illuminate\Support\Str;

class TemporaryPasswordGenerator
{
    /**
     * Generate a cryptographically secure temporary password of at least 16 characters.
     *
     * @param  int  $length
     * @return string
     */
    public function generate(int $length = 16): string
    {
        $length = max(16, $length);

        return Str::password(
            length: $length,
            letters: true,
            numbers: true,
            symbols: true,
            spaces: false
        );
    }
}
