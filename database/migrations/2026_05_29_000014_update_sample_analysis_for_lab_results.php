<?php

declare(strict_types=1);

return [
    'up' => [
        'SET @sunn_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "sample_analysis"
                AND COLUMN_NAME = "sunn_pest_rate"
        )',
        'SET @sql := IF(
            @sunn_exists = 0,
            "ALTER TABLE sample_analysis ADD COLUMN sunn_pest_rate DECIMAL(6,2) NULL AFTER gluten",
            "SELECT 1"
        )',
        'PREPARE sunn_statement FROM @sql',
        'EXECUTE sunn_statement',
        'DEALLOCATE PREPARE sunn_statement',
        'SET @result_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "sample_analysis"
                AND COLUMN_NAME = "result"
        )',
        'SET @sql := IF(
            @result_exists = 0,
            "ALTER TABLE sample_analysis ADD COLUMN result ENUM(\"accepted\", \"conditional\", \"rejected\") NOT NULL DEFAULT \"accepted\" AFTER broken_grain",
            "SELECT 1"
        )',
        'PREPARE result_statement FROM @sql',
        'EXECUTE result_statement',
        'DEALLOCATE PREPARE result_statement',
        'ALTER TABLE sample_analysis MODIFY status ENUM("pending", "approved", "rejected", "completed") NOT NULL DEFAULT "pending"',
        'UPDATE sample_analysis SET status = "completed" WHERE status IN ("approved", "rejected")',
        'ALTER TABLE sample_analysis MODIFY status ENUM("pending", "completed") NOT NULL DEFAULT "completed"',
    ],
    'down' => [
        'ALTER TABLE sample_analysis MODIFY status ENUM("pending", "approved", "rejected", "completed") NOT NULL DEFAULT "pending"',
        'UPDATE sample_analysis SET status = "approved" WHERE status = "completed"',
        'ALTER TABLE sample_analysis MODIFY status ENUM("pending", "approved", "rejected") NOT NULL DEFAULT "pending"',
        'SET @result_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "sample_analysis"
                AND COLUMN_NAME = "result"
        )',
        'SET @sql := IF(
            @result_exists = 1,
            "ALTER TABLE sample_analysis DROP COLUMN result",
            "SELECT 1"
        )',
        'PREPARE result_statement FROM @sql',
        'EXECUTE result_statement',
        'DEALLOCATE PREPARE result_statement',
        'SET @sunn_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "sample_analysis"
                AND COLUMN_NAME = "sunn_pest_rate"
        )',
        'SET @sql := IF(
            @sunn_exists = 1,
            "ALTER TABLE sample_analysis DROP COLUMN sunn_pest_rate",
            "SELECT 1"
        )',
        'PREPARE sunn_statement FROM @sql',
        'EXECUTE sunn_statement',
        'DEALLOCATE PREPARE sunn_statement',
    ],
];
