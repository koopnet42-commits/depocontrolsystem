<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE silo_rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(140) NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            silo_id BIGINT UNSIGNED NOT NULL,
            min_moisture DECIMAL(6,2) NULL,
            max_moisture DECIMAL(6,2) NULL,
            min_protein DECIMAL(6,2) NULL,
            max_protein DECIMAL(6,2) NULL,
            min_hectoliter DECIMAL(6,2) NULL,
            max_hectoliter DECIMAL(6,2) NULL,
            max_foreign_material DECIMAL(6,2) NULL,
            max_sunn_pest_rate DECIMAL(6,2) NULL,
            priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY silo_rules_product_id_index (product_id),
            KEY silo_rules_silo_id_index (silo_id),
            KEY silo_rules_priority_index (priority),
            CONSTRAINT silo_rules_product_id_foreign
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT silo_rules_silo_id_foreign
                FOREIGN KEY (silo_id) REFERENCES silos(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS silo_rules',
    ],
];
