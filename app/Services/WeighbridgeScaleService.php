<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\WeighbridgeScale;

final class WeighbridgeScaleService implements WeighbridgeScale
{
    public function ticketNumber(): string
    {
        return 'KG-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    }

    public function status(?string $activePlate = null): array
    {
        $kg = $activePlate === null ? 0 : 42000 + (time() % 900);

        return [
            'mode' => 'Simülasyon',
            'status' => $activePlate === null ? 'Hazır' : 'Araç Üzerinde',
            'weight_kg' => $kg,
            'weight_ton' => $kg / 1000,
            'last_read_at' => date('Y-m-d H:i:s'),
            'active_plate' => $activePlate,
            'connection_status' => 'Simülasyon',
            'barrier_status' => $activePlate === null ? 'Kapalı / hazır' : 'İşlemde',
        ];
    }
}
