<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class SampleAnalysis extends Model
{
    protected string $table = 'sample_analysis';

    protected array $fillable = [
        'analysis_number',
        'weighbridge_record_id',
        'product_id',
        'moisture',
        'protein',
        'hectoliter',
        'gluten',
        'sunn_pest_rate',
        'foreign_material',
        'broken_grain',
        'result',
        'status',
        'analyzed_by',
        'analyzed_at',
        'notes',
    ];

    protected array $belongsTo = [
        'weighbridgeRecord' => [WeighbridgeRecord::class, 'weighbridge_record_id'],
        'product' => [Product::class, 'product_id'],
        'analyst' => [User::class, 'analyzed_by'],
    ];

    protected array $hasMany = [
        'barcodeTickets' => [BarcodeTicket::class, 'sample_analysis_id'],
    ];
}
