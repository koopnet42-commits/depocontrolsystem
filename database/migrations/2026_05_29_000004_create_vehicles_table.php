<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE vehicles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plate_number VARCHAR(20) NOT NULL,
            brand VARCHAR(80) NULL,
            model VARCHAR(80) NULL,
            driver_name VARCHAR(140) NULL,
            driver_phone VARCHAR(40) NULL,
            company_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY vehicles_plate_number_unique (plate_number),
            KEY vehicles_company_id_index (company_id),
            CONSTRAINT vehicles_company_id_foreign
                FOREIGN KEY (company_id) REFERENCES companies(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS vehicles',
    ],
];
