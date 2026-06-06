<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE delivery_notifications ADD COLUMN company_notified_at TIMESTAMP NULL AFTER operation_closed_at',
        'ALTER TABLE delivery_notifications ADD COLUMN company_notified_by BIGINT UNSIGNED NULL AFTER company_notified_at',
        'ALTER TABLE delivery_notifications ADD COLUMN company_notified_note TEXT NULL AFTER company_notified_by',
        'ALTER TABLE delivery_notifications ADD COLUMN notification_note_at TIMESTAMP NULL AFTER company_notified_note',
        'ALTER TABLE delivery_notifications ADD COLUMN notification_note_by BIGINT UNSIGNED NULL AFTER notification_note_at',
        'ALTER TABLE delivery_notifications ADD COLUMN notification_note TEXT NULL AFTER notification_note_by',
        'ALTER TABLE delivery_notifications ADD COLUMN cancel_reason VARCHAR(160) NULL AFTER notification_note',
        'ALTER TABLE delivery_notifications ADD COLUMN cancel_note TEXT NULL AFTER cancel_reason',
        'ALTER TABLE delivery_notifications ADD COLUMN cancelled_at TIMESTAMP NULL AFTER cancel_note',
        'ALTER TABLE delivery_notifications ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at',
    ],
    'down' => [
        'ALTER TABLE delivery_notifications DROP COLUMN cancelled_by',
        'ALTER TABLE delivery_notifications DROP COLUMN cancelled_at',
        'ALTER TABLE delivery_notifications DROP COLUMN cancel_note',
        'ALTER TABLE delivery_notifications DROP COLUMN cancel_reason',
        'ALTER TABLE delivery_notifications DROP COLUMN notification_note',
        'ALTER TABLE delivery_notifications DROP COLUMN notification_note_by',
        'ALTER TABLE delivery_notifications DROP COLUMN notification_note_at',
        'ALTER TABLE delivery_notifications DROP COLUMN company_notified_note',
        'ALTER TABLE delivery_notifications DROP COLUMN company_notified_by',
        'ALTER TABLE delivery_notifications DROP COLUMN company_notified_at',
    ],
];
