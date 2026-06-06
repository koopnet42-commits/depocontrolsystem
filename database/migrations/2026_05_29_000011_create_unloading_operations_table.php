<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE unloading_operations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            operation_number VARCHAR(60) NOT NULL,
            barcode_ticket_id BIGINT UNSIGNED NOT NULL,
            weighbridge_record_id BIGINT UNSIGNED NOT NULL,
            silo_id BIGINT UNSIGNED NOT NULL,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            unloaded_weight_kg DECIMAL(12,3) NULL,
            status ENUM("waiting", "in_progress", "completed", "cancelled") NOT NULL DEFAULT "waiting",
            operator_id BIGINT UNSIGNED NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unloading_operations_number_unique (operation_number),
            UNIQUE KEY unloading_operations_barcode_ticket_id_unique (barcode_ticket_id),
            KEY unloading_operations_weighbridge_record_id_index (weighbridge_record_id),
            KEY unloading_operations_silo_id_index (silo_id),
            KEY unloading_operations_operator_id_index (operator_id),
            CONSTRAINT unloading_operations_barcode_ticket_id_foreign
                FOREIGN KEY (barcode_ticket_id) REFERENCES barcode_tickets(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT unloading_operations_weighbridge_record_id_foreign
                FOREIGN KEY (weighbridge_record_id) REFERENCES weighbridge_records(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT unloading_operations_silo_id_foreign
                FOREIGN KEY (silo_id) REFERENCES silos(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT unloading_operations_operator_id_foreign
                FOREIGN KEY (operator_id) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS unloading_operations',
    ],
];
