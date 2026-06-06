<?php

declare(strict_types=1);

return [
    'up' => [
        'SET @column_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "companies"
                AND COLUMN_NAME = "contact_person"
        )',
        'SET @sql := IF(
            @column_exists = 0,
            "ALTER TABLE companies ADD COLUMN contact_person VARCHAR(140) NULL AFTER phone",
            "SELECT 1"
        )',
        'PREPARE contact_person_statement FROM @sql',
        'EXECUTE contact_person_statement',
        'DEALLOCATE PREPARE contact_person_statement',
    ],
    'down' => [
        'SET @column_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "companies"
                AND COLUMN_NAME = "contact_person"
        )',
        'SET @sql := IF(
            @column_exists = 1,
            "ALTER TABLE companies DROP COLUMN contact_person",
            "SELECT 1"
        )',
        'PREPARE contact_person_statement FROM @sql',
        'EXECUTE contact_person_statement',
        'DEALLOCATE PREPARE contact_person_statement',
    ],
];
