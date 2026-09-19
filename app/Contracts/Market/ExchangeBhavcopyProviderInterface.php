<?php

namespace App\Contracts\Market;

use App\DTOs\Market\NormalizedBhavcopyRowDTO;
use Illuminate\Support\Collection;

interface ExchangeBhavcopyProviderInterface
{
    /**
     * Get the standardized exchange code (e.g., 'NCDEX', 'MCX').
     */
    public function getExchangeCode(): string;

    /**
     * Parse raw source payload or file into a collection of normalized Bhavcopy row DTOs.
     *
     * @return Collection<int, NormalizedBhavcopyRowDTO>
     */
    public function parseBhavcopy(string $sourcePathOrPayload): Collection;
}
