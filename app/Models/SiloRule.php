<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class SiloRule extends Model
{
    protected string $table = 'silo_rules';

    protected array $fillable = [
        'name',
        'product_id',
        'silo_id',
        'min_moisture',
        'max_moisture',
        'min_protein',
        'max_protein',
        'min_hectoliter',
        'max_hectoliter',
        'max_foreign_material',
        'max_sunn_pest_rate',
        'priority',
        'is_active',
    ];

    protected array $belongsTo = [
        'product' => [Product::class, 'product_id'],
        'silo' => [Silo::class, 'silo_id'],
    ];
}
