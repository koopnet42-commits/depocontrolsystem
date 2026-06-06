<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE barcode_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            barcode VARCHAR(120) NOT NULL,
            weighbridge_record_id BIGINT UNSIGNED NOT NULL,
            sample_analysis_id BIGINT UNSIGNED NULL,
            silo_id BIGINT UNSIGNED NOT NULL,
            issued_by BIGINT UNSIGNED NULL,
            issued_at TIMESTAMP NULL,
            status ENUM("active", "used", "cancelled") NOT NULL DEFAULT "active",
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY barcode_tickets_barcode_unique (barcode),
            UNIQUE KEY barcode_tickets_weighbridge_record_id_unique (weighbridge_record_id),
            KEY barcode_tickets_sample_analysis_id_index (sample_analysis_id),
            KEY barcode_tickets_silo_id_index (silo_id),
            KEY barcode_tickets_issued_by_index (issued_by),
            CONSTRAINT barcode_tickets_weighbridge_record_id_foreign
                FOREIGN KEY (weighbridge_record_id) REFERENCES weighbridge_records(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT barcode_tickets_sample_analysis_id_foreign
                FOREIGN KEY (sample_analysis_id) REFERENCES sample_analysis(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT barcode_tickets_silo_id_foreign
                FOREIGN KEY (silo_id) REFERENCES silos(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT barcode_tickets_issued_by_foreign
                FOREIGN KEY (issued_by) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS barcode_tickets',
    ],
];
