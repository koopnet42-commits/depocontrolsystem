<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DriverVehicleRegistry;

final class DriverVehicleController extends Controller
{
    public function lookup(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(DriverVehicleRegistry::lookup($_GET), JSON_UNESCAPED_UNICODE);
    }
}
