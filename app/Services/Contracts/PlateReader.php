<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface PlateReader
{
    public function normalize(string $plateNumber): string;
}
