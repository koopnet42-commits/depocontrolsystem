<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface BarrierGate
{
    public function open(): array;
}
