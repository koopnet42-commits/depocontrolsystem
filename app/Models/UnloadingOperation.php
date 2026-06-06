<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class UnloadingOperation extends Model
{
    protected string $table = 'unloading_operations';

    protected array $fillable = [
        'operation_number',
        'barcode_ticket_id',
        'weighbridge_record_id',
        'silo_id',
        'started_at',
        'completed_at',
        'unloaded_weight_kg',
        'status',
        'operator_id',
        'notes',
    ];

    protected array $belongsTo = [
        'barcodeTicket' => [BarcodeTicket::class, 'barcode_ticket_id'],
        'weighbridgeRecord' => [WeighbridgeRecord::class, 'weighbridge_record_id'],
        'silo' => [Silo::class, 'silo_id'],
        'operator' => [User::class, 'operator_id'],
    ];
}
