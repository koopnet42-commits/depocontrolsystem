<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE barcode_tickets ADD COLUMN entry_id BIGINT UNSIGNED NULL AFTER barcode',
        'ALTER TABLE barcode_tickets MODIFY status ENUM("active", "in_progress", "completed", "used", "cancelled") NOT NULL DEFAULT "active"',
        'ALTER TABLE barcode_tickets ADD INDEX barcode_tickets_entry_id_index (entry_id)',
        'ALTER TABLE barcode_tickets ADD CONSTRAINT barcode_tickets_entry_id_foreign
            FOREIGN KEY (entry_id) REFERENCES delivery_notifications(id)
            ON DELETE SET NULL ON UPDATE CASCADE',
    ],
    'down' => [
        'ALTER TABLE barcode_tickets DROP FOREIGN KEY barcode_tickets_entry_id_foreign',
        'ALTER TABLE barcode_tickets DROP INDEX barcode_tickets_entry_id_index',
        'ALTER TABLE barcode_tickets DROP COLUMN entry_id',
        'ALTER TABLE barcode_tickets MODIFY status ENUM("active", "used", "cancelled") NOT NULL DEFAULT "active"',
    ],
];
