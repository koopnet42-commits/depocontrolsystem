<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE delivery_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            notification_number VARCHAR(60) NOT NULL,
            company_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            vehicle_id BIGINT UNSIGNED NULL,
            expected_quantity_kg DECIMAL(12,3) NULL,
            loading_date DATE NULL,
            expected_arrival_date DATE NULL,
            status ENUM("pending", "at_weighbridge", "in_analysis", "directed_to_silo", "unloaded", "completed", "cancelled") NOT NULL DEFAULT "pending",
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY delivery_notifications_number_unique (notification_number),
            KEY delivery_notifications_company_id_index (company_id),
            KEY delivery_notifications_product_id_index (product_id),
            KEY delivery_notifications_vehicle_id_index (vehicle_id),
            KEY delivery_notifications_created_by_index (created_by),
            CONSTRAINT delivery_notifications_company_id_foreign
                FOREIGN KEY (company_id) REFERENCES companies(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT delivery_notifications_product_id_foreign
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT delivery_notifications_vehicle_id_foreign
                FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT delivery_notifications_created_by_foreign
                FOREIGN KEY (created_by) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS delivery_notifications',
    ],
];
