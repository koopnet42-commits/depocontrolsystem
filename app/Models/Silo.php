<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Silo extends Model
{
    protected string $table = 'silos';

    protected array $fillable = [
        'name',
        'code',
        'product_id',
        'capacity_kg',
        'current_stock_kg',
        'min_moisture',
        'max_moisture',
        'min_protein',
        'max_protein',
        'min_hectoliter',
        'max_hectoliter',
        'min_gluten',
        'max_gluten',
        'min_sunn_pest_rate',
        'max_sunn_pest_rate',
        'min_foreign_material',
        'max_foreign_material',
        'min_broken_grain',
        'max_broken_grain',
        'location',
        'description',
        'is_active',
    ];

    protected array $belongsTo = [
        'product' => [Product::class, 'product_id'],
    ];

    protected array $hasMany = [
        'rules' => [SiloRule::class, 'silo_id'],
        'barcodeTickets' => [BarcodeTicket::class, 'silo_id'],
        'unloadingOperations' => [UnloadingOperation::class, 'silo_id'],
    ];
}
