<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE delivery_notifications ADD COLUMN entry_type ENUM("pre_notified", "direct_entry") NOT NULL DEFAULT "pre_notified" AFTER expected_arrival_date',
        'ALTER TABLE delivery_notifications ADD COLUMN sender_type ENUM("company", "person") NOT NULL DEFAULT "company" AFTER entry_type',
        'ALTER TABLE delivery_notifications ADD COLUMN dispatch_number VARCHAR(120) NULL AFTER sender_type',
        'ALTER TABLE delivery_notifications ADD COLUMN identity_number VARCHAR(20) NULL AFTER dispatch_number',
        'ALTER TABLE delivery_notifications ADD COLUMN sender_name VARCHAR(180) NULL AFTER identity_number',
        'ALTER TABLE delivery_notifications ADD COLUMN sender_tax_number VARCHAR(80) NULL AFTER sender_name',
        'ALTER TABLE delivery_notifications ADD COLUMN sender_phone VARCHAR(80) NULL AFTER sender_tax_number',
        'ALTER TABLE delivery_notifications ADD COLUMN sender_address TEXT NULL AFTER sender_phone',
        'ALTER TABLE system_settings ADD COLUMN dashboard_silo_view ENUM("horizontal", "vertical") NOT NULL DEFAULT "vertical" AFTER auto_backup_enabled',
        'ALTER TABLE system_settings ADD COLUMN qr_content_fields TEXT NULL AFTER dashboard_silo_view',
    ],
    'down' => [
        'ALTER TABLE system_settings DROP COLUMN qr_content_fields',
        'ALTER TABLE system_settings DROP COLUMN dashboard_silo_view',
        'ALTER TABLE delivery_notifications DROP COLUMN sender_address',
        'ALTER TABLE delivery_notifications DROP COLUMN sender_phone',
        'ALTER TABLE delivery_notifications DROP COLUMN sender_tax_number',
        'ALTER TABLE delivery_notifications DROP COLUMN sender_name',
        'ALTER TABLE delivery_notifications DROP COLUMN identity_number',
        'ALTER TABLE delivery_notifications DROP COLUMN dispatch_number',
        'ALTER TABLE delivery_notifications DROP COLUMN sender_type',
        'ALTER TABLE delivery_notifications DROP COLUMN entry_type',
    ],
];
