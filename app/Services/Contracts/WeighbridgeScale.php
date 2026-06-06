<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface WeighbridgeScale
{
    public function ticketNumber(): string;
}
