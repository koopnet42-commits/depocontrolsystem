<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE weighbridge_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(60) NOT NULL,
            delivery_notification_id BIGINT UNSIGNED NULL,
            company_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            vehicle_id BIGINT UNSIGNED NOT NULL,
            assigned_silo_id BIGINT UNSIGNED NULL,
            first_weight_kg DECIMAL(12,3) NULL,
            first_weighed_at TIMESTAMP NULL,
            second_weight_kg DECIMAL(12,3) NULL,
            second_weighed_at TIMESTAMP NULL,
            net_weight_kg DECIMAL(12,3) NULL,
            status ENUM("entry", "sampled", "directed", "unloading", "completed", "cancelled") NOT NULL DEFAULT "entry",
            first_weighed_by BIGINT UNSIGNED NULL,
            second_weighed_by BIGINT UNSIGNED NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY weighbridge_records_ticket_number_unique (ticket_number),
            KEY weighbridge_records_delivery_notification_id_index (delivery_notification_id),
            KEY weighbridge_records_company_id_index (company_id),
            KEY weighbridge_records_product_id_index (product_id),
            KEY weighbridge_records_vehicle_id_index (vehicle_id),
            KEY weighbridge_records_assigned_silo_id_index (assigned_silo_id),
            KEY weighbridge_records_first_weighed_by_index (first_weighed_by),
            KEY weighbridge_records_second_weighed_by_index (second_weighed_by),
            CONSTRAINT weighbridge_records_delivery_notification_id_foreign
                FOREIGN KEY (delivery_notification_id) REFERENCES delivery_notifications(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT weighbridge_records_company_id_foreign
                FOREIGN KEY (company_id) REFERENCES companies(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT weighbridge_records_product_id_foreign
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT weighbridge_records_vehicle_id_foreign
                FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT weighbridge_records_assigned_silo_id_foreign
                FOREIGN KEY (assigned_silo_id) REFERENCES silos(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT weighbridge_records_first_weighed_by_foreign
                FOREIGN KEY (first_weighed_by) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT weighbridge_records_second_weighed_by_foreign
                FOREIGN KEY (second_weighed_by) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS weighbridge_records',
    ],
];
