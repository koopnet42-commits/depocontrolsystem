<?php

declare(strict_types=1);

$columns = [
    'min_moisture' => 'DECIMAL(6,2) NULL',
    'max_moisture' => 'DECIMAL(6,2) NULL',
    'min_protein' => 'DECIMAL(6,2) NULL',
    'max_protein' => 'DECIMAL(6,2) NULL',
    'min_hectoliter' => 'DECIMAL(6,2) NULL',
    'max_hectoliter' => 'DECIMAL(6,2) NULL',
    'min_gluten' => 'DECIMAL(6,2) NULL',
    'max_gluten' => 'DECIMAL(6,2) NULL',
    'min_sunn_pest_rate' => 'DECIMAL(6,2) NULL',
    'max_sunn_pest_rate' => 'DECIMAL(6,2) NULL',
    'min_foreign_material' => 'DECIMAL(6,2) NULL',
    'max_foreign_material' => 'DECIMAL(6,2) NULL',
    'min_broken_grain' => 'DECIMAL(6,2) NULL',
    'max_broken_grain' => 'DECIMAL(6,2) NULL',
    'description' => 'TEXT NULL',
];

$up = [];
$down = [];

foreach ($columns as $name => $definition) {
    $up[] = "SET @{$name}_exists := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = \"silos\"
            AND COLUMN_NAME = \"{$name}\"
    )";
    $up[] = "SET @sql := IF(
        @{$name}_exists = 0,
        \"ALTER TABLE silos ADD COLUMN {$name} {$definition}\",
        \"SELECT 1\"
    )";
    $up[] = "PREPARE {$name}_statement FROM @sql";
    $up[] = "EXECUTE {$name}_statement";
    $up[] = "DEALLOCATE PREPARE {$name}_statement";

    $down[] = "SET @{$name}_exists := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = \"silos\"
            AND COLUMN_NAME = \"{$name}\"
    )";
    $down[] = "SET @sql := IF(
        @{$name}_exists = 1,
        \"ALTER TABLE silos DROP COLUMN {$name}\",
        \"SELECT 1\"
    )";
    $down[] = "PREPARE {$name}_statement FROM @sql";
    $down[] = "EXECUTE {$name}_statement";
    $down[] = "DEALLOCATE PREPARE {$name}_statement";
}

return [
    'up' => $up,
    'down' => $down,
];
