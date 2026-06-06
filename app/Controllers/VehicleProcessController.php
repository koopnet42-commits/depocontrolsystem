<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\VehicleProcessService;

final class VehicleProcessController extends Controller
{
    public function __construct(private readonly VehicleProcessService $service = new VehicleProcessService())
    {
    }

    public function detail(): void
    {
        $record = $this->service->detail((int) $this->input('entry_id'));
        $this->json($record === null ? ['ok' => false] : ['ok' => true, 'record' => $record]);
    }

    public function list(): void
    {
        $group = (string) $this->input('group', '');
        $this->json([
            'ok' => true,
            'group' => $group,
            'records' => $this->service->listByGroup($group),
            'status_labels' => VehicleProcessService::STATUS_LABELS,
        ]);
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}

