<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Product extends Model
{
    protected string $table = 'products';

    protected array $fillable = [
        'name',
        'code',
        'unit',
        'description',
        'is_active',
    ];

    protected array $hasMany = [
        'deliveryNotifications' => [DeliveryNotification::class, 'product_id'],
        'weighbridgeRecords' => [WeighbridgeRecord::class, 'product_id'],
        'sampleAnalyses' => [SampleAnalysis::class, 'product_id'],
        'silos' => [Silo::class, 'product_id'],
        'siloRules' => [SiloRule::class, 'product_id'],
    ];
}
