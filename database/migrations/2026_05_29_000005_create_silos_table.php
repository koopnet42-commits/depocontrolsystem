<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE silos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            code VARCHAR(40) NOT NULL,
            product_id BIGINT UNSIGNED NULL,
            capacity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
            current_stock_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
            min_moisture DECIMAL(6,2) NULL,
            max_moisture DECIMAL(6,2) NULL,
            min_protein DECIMAL(6,2) NULL,
            max_protein DECIMAL(6,2) NULL,
            min_hectoliter DECIMAL(6,2) NULL,
            max_hectoliter DECIMAL(6,2) NULL,
            min_gluten DECIMAL(6,2) NULL,
            max_gluten DECIMAL(6,2) NULL,
            min_sunn_pest_rate DECIMAL(6,2) NULL,
            max_sunn_pest_rate DECIMAL(6,2) NULL,
            min_foreign_material DECIMAL(6,2) NULL,
            max_foreign_material DECIMAL(6,2) NULL,
            min_broken_grain DECIMAL(6,2) NULL,
            max_broken_grain DECIMAL(6,2) NULL,
            location VARCHAR(160) NULL,
            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY silos_code_unique (code),
            KEY silos_product_id_index (product_id),
            CONSTRAINT silos_product_id_foreign
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS silos',
    ],
];
