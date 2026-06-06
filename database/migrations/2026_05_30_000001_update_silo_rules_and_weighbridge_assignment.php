<?php

declare(strict_types=1);

return [
    'up' => [
        'SET @assigned_silo_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "weighbridge_records"
                AND COLUMN_NAME = "assigned_silo_id"
        )',
        'SET @sql := IF(
            @assigned_silo_exists = 0,
            "ALTER TABLE weighbridge_records ADD COLUMN assigned_silo_id BIGINT UNSIGNED NULL AFTER vehicle_id",
            "SELECT 1"
        )',
        'PREPARE assigned_silo_statement FROM @sql',
        'EXECUTE assigned_silo_statement',
        'DEALLOCATE PREPARE assigned_silo_statement',
        'SET @assigned_silo_index_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "weighbridge_records"
                AND INDEX_NAME = "weighbridge_records_assigned_silo_id_index"
        )',
        'SET @sql := IF(
            @assigned_silo_index_exists = 0,
            "ALTER TABLE weighbridge_records ADD INDEX weighbridge_records_assigned_silo_id_index (assigned_silo_id)",
            "SELECT 1"
        )',
        'PREPARE assigned_silo_index_statement FROM @sql',
        'EXECUTE assigned_silo_index_statement',
        'DEALLOCATE PREPARE assigned_silo_index_statement',
        'SET @assigned_silo_fk_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "weighbridge_records"
                AND CONSTRAINT_NAME = "weighbridge_records_assigned_silo_id_foreign"
        )',
        'SET @sql := IF(
            @assigned_silo_fk_exists = 0,
            "ALTER TABLE weighbridge_records ADD CONSTRAINT weighbridge_records_assigned_silo_id_foreign FOREIGN KEY (assigned_silo_id) REFERENCES silos(id) ON DELETE SET NULL ON UPDATE CASCADE",
            "SELECT 1"
        )',
        'PREPARE assigned_silo_fk_statement FROM @sql',
        'EXECUTE assigned_silo_fk_statement',
        'DEALLOCATE PREPARE assigned_silo_fk_statement',
        'SET @max_foreign_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "silo_rules"
                AND COLUMN_NAME = "max_foreign_material"
        )',
        'SET @sql := IF(
            @max_foreign_exists = 0,
            "ALTER TABLE silo_rules ADD COLUMN max_foreign_material DECIMAL(6,2) NULL AFTER max_hectoliter",
            "SELECT 1"
        )',
        'PREPARE max_foreign_statement FROM @sql',
        'EXECUTE max_foreign_statement',
        'DEALLOCATE PREPARE max_foreign_statement',
        'SET @max_sunn_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "silo_rules"
                AND COLUMN_NAME = "max_sunn_pest_rate"
        )',
        'SET @sql := IF(
            @max_sunn_exists = 0,
            "ALTER TABLE silo_rules ADD COLUMN max_sunn_pest_rate DECIMAL(6,2) NULL AFTER max_foreign_material",
            "SELECT 1"
        )',
        'PREPARE max_sunn_statement FROM @sql',
        'EXECUTE max_sunn_statement',
        'DEALLOCATE PREPARE max_sunn_statement',
    ],
    'down' => [
        'SET @max_sunn_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "silo_rules"
                AND COLUMN_NAME = "max_sunn_pest_rate"
        )',
        'SET @sql := IF(@max_sunn_exists = 1, "ALTER TABLE silo_rules DROP COLUMN max_sunn_pest_rate", "SELECT 1")',
        'PREPARE max_sunn_statement FROM @sql',
        'EXECUTE max_sunn_statement',
        'DEALLOCATE PREPARE max_sunn_statement',
        'SET @max_foreign_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "silo_rules"
                AND COLUMN_NAME = "max_foreign_material"
        )',
        'SET @sql := IF(@max_foreign_exists = 1, "ALTER TABLE silo_rules DROP COLUMN max_foreign_material", "SELECT 1")',
        'PREPARE max_foreign_statement FROM @sql',
        'EXECUTE max_foreign_statement',
        'DEALLOCATE PREPARE max_foreign_statement',
        'SET @assigned_silo_fk_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "weighbridge_records"
                AND CONSTRAINT_NAME = "weighbridge_records_assigned_silo_id_foreign"
        )',
        'SET @sql := IF(@assigned_silo_fk_exists = 1, "ALTER TABLE weighbridge_records DROP FOREIGN KEY weighbridge_records_assigned_silo_id_foreign", "SELECT 1")',
        'PREPARE assigned_silo_fk_statement FROM @sql',
        'EXECUTE assigned_silo_fk_statement',
        'DEALLOCATE PREPARE assigned_silo_fk_statement',
        'SET @assigned_silo_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "weighbridge_records"
                AND COLUMN_NAME = "assigned_silo_id"
        )',
        'SET @sql := IF(@assigned_silo_exists = 1, "ALTER TABLE weighbridge_records DROP COLUMN assigned_silo_id", "SELECT 1")',
        'PREPARE assigned_silo_statement FROM @sql',
        'EXECUTE assigned_silo_statement',
        'DEALLOCATE PREPARE assigned_silo_statement',
    ],
];
