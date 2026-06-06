<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Company extends Model
{
    protected string $table = 'companies';

    protected array $fillable = [
        'name',
        'tax_number',
        'tax_office',
        'phone',
        'contact_person',
        'email',
        'address',
        'is_active',
    ];

    protected array $hasMany = [
        'vehicles' => [Vehicle::class, 'company_id'],
        'deliveryNotifications' => [DeliveryNotification::class, 'company_id'],
        'weighbridgeRecords' => [WeighbridgeRecord::class, 'company_id'],
    ];
}
