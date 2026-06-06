<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\PlateReader;

final class PlateReaderService implements PlateReader
{
    public function normalize(string $plateNumber): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($plateNumber)) ?? $plateNumber);
    }
}
