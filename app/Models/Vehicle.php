<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Vehicle extends Model
{
    protected string $table = 'vehicles';

    protected array $fillable = [
        'plate_number',
        'normalized_plate',
        'brand',
        'model',
        'driver_name',
        'driver_phone',
        'company_id',
        'is_active',
    ];

    protected array $belongsTo = [
        'company' => [Company::class, 'company_id'],
    ];

    protected array $hasMany = [
        'deliveryNotifications' => [DeliveryNotification::class, 'vehicle_id'],
        'weighbridgeRecords' => [WeighbridgeRecord::class, 'vehicle_id'],
    ];
}
