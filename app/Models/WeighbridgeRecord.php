<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class WeighbridgeRecord extends Model
{
    protected string $table = 'weighbridge_records';

    protected array $fillable = [
        'ticket_number',
        'delivery_notification_id',
        'company_id',
        'product_id',
        'vehicle_id',
        'assigned_silo_id',
        'first_weight_kg',
        'first_weighed_at',
        'second_weight_kg',
        'second_weighed_at',
        'net_weight_kg',
        'status',
        'first_weighed_by',
        'second_weighed_by',
        'notes',
    ];

    protected array $belongsTo = [
        'deliveryNotification' => [DeliveryNotification::class, 'delivery_notification_id'],
        'company' => [Company::class, 'company_id'],
        'product' => [Product::class, 'product_id'],
        'vehicle' => [Vehicle::class, 'vehicle_id'],
        'assignedSilo' => [Silo::class, 'assigned_silo_id'],
        'firstWeigher' => [User::class, 'first_weighed_by'],
        'secondWeigher' => [User::class, 'second_weighed_by'],
    ];

    protected array $hasMany = [
        'sampleAnalyses' => [SampleAnalysis::class, 'weighbridge_record_id'],
        'barcodeTickets' => [BarcodeTicket::class, 'weighbridge_record_id'],
        'unloadingOperations' => [UnloadingOperation::class, 'weighbridge_record_id'],
    ];
}
