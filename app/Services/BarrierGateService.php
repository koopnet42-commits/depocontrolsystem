<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\BarrierGate;

final class BarrierGateService implements BarrierGate
{
    public function open(): array
    {
        return [
            'success' => true,
            'message' => 'Giriş bariyeri simülasyon olarak açıldı.',
        ];
    }
}
