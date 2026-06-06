<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE companies (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(180) NOT NULL,
            tax_number VARCHAR(40) NULL,
            tax_office VARCHAR(120) NULL,
            phone VARCHAR(40) NULL,
            email VARCHAR(160) NULL,
            address TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY companies_tax_number_unique (tax_number),
            KEY companies_name_index (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS companies',
    ],
];
