<?php

declare(strict_types=1);

return [
    'up' => [
        'SET @loading_date_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "delivery_notifications"
                AND COLUMN_NAME = "loading_date"
        )',
        'SET @sql := IF(
            @loading_date_exists = 0,
            "ALTER TABLE delivery_notifications ADD COLUMN loading_date DATE NULL AFTER expected_quantity_kg",
            "SELECT 1"
        )',
        'PREPARE loading_date_statement FROM @sql',
        'EXECUTE loading_date_statement',
        'DEALLOCATE PREPARE loading_date_statement',
        'ALTER TABLE delivery_notifications
            MODIFY status ENUM(
                "pending",
                "arrived",
                "at_weighbridge",
                "in_analysis",
                "directed_to_silo",
                "unloaded",
                "completed",
                "cancelled"
            ) NOT NULL DEFAULT "pending"',
        'UPDATE delivery_notifications SET status = "at_weighbridge" WHERE status = "arrived"',
        'ALTER TABLE delivery_notifications
            MODIFY status ENUM(
                "pending",
                "at_weighbridge",
                "in_analysis",
                "directed_to_silo",
                "unloaded",
                "completed",
                "cancelled"
            ) NOT NULL DEFAULT "pending"',
    ],
    'down' => [
        'ALTER TABLE delivery_notifications
            MODIFY status ENUM(
                "pending",
                "arrived",
                "at_weighbridge",
                "in_analysis",
                "directed_to_silo",
                "unloaded",
                "completed",
                "cancelled"
            ) NOT NULL DEFAULT "pending"',
        'UPDATE delivery_notifications
            SET status = "arrived"
            WHERE status IN ("at_weighbridge", "in_analysis", "directed_to_silo", "unloaded")',
        'ALTER TABLE delivery_notifications
            MODIFY status ENUM("pending", "arrived", "cancelled", "completed") NOT NULL DEFAULT "pending"',
        'SET @loading_date_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "delivery_notifications"
                AND COLUMN_NAME = "loading_date"
        )',
        'SET @sql := IF(
            @loading_date_exists = 1,
            "ALTER TABLE delivery_notifications DROP COLUMN loading_date",
            "SELECT 1"
        )',
        'PREPARE loading_date_statement FROM @sql',
        'EXECUTE loading_date_statement',
        'DEALLOCATE PREPARE loading_date_statement',
    ],
];
