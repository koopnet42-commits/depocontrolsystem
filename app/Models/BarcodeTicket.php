<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class BarcodeTicket extends Model
{
    protected string $table = 'barcode_tickets';

    protected array $fillable = [
        'barcode',
        'weighbridge_record_id',
        'sample_analysis_id',
        'silo_id',
        'issued_by',
        'issued_at',
        'status',
    ];

    protected array $belongsTo = [
        'weighbridgeRecord' => [WeighbridgeRecord::class, 'weighbridge_record_id'],
        'sampleAnalysis' => [SampleAnalysis::class, 'sample_analysis_id'],
        'silo' => [Silo::class, 'silo_id'],
        'issuer' => [User::class, 'issued_by'],
    ];

    protected array $hasMany = [
        'unloadingOperations' => [UnloadingOperation::class, 'barcode_ticket_id'],
    ];
}
