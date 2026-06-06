<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected string $table = 'users';

    public const ROLES = [
        'admin' => 'Admin',
        'weighbridge' => 'Kantar görevlisi',
        'lab' => 'Laboratuvar görevlisi',
        'silo' => 'Silo görevlisi',
        'manager' => 'Yönetici',
    ];

    protected array $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected array $hasMany = [
        'createdDeliveryNotifications' => [DeliveryNotification::class, 'created_by'],
        'firstWeighbridgeRecords' => [WeighbridgeRecord::class, 'first_weighed_by'],
        'secondWeighbridgeRecords' => [WeighbridgeRecord::class, 'second_weighed_by'],
        'sampleAnalyses' => [SampleAnalysis::class, 'analyzed_by'],
        'barcodeTickets' => [BarcodeTicket::class, 'issued_by'],
        'unloadingOperations' => [UnloadingOperation::class, 'operator_id'],
    ];
}
