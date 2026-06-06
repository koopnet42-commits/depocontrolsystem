<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class DeliveryNotification extends Model
{
    protected string $table = 'delivery_notifications';

    protected array $fillable = [
        'notification_number',
        'company_id',
        'product_id',
        'vehicle_id',
        'expected_quantity_kg',
        'loading_date',
        'expected_arrival_date',
        'status',
        'notes',
        'created_by',
    ];

    protected array $belongsTo = [
        'company' => [Company::class, 'company_id'],
        'product' => [Product::class, 'product_id'],
        'vehicle' => [Vehicle::class, 'vehicle_id'],
        'creator' => [User::class, 'created_by'],
    ];

    protected array $hasMany = [
        'weighbridgeRecords' => [WeighbridgeRecord::class, 'delivery_notification_id'],
    ];
}
