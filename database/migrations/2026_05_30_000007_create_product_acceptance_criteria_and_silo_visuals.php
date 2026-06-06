<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE product_acceptance_criteria (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            min_protein DECIMAL(6,2) NULL,
            max_moisture DECIMAL(6,2) NULL,
            min_hectoliter DECIMAL(6,2) NULL,
            max_sunn_pest_rate DECIMAL(6,2) NULL,
            max_foreign_matter DECIMAL(6,2) NULL,
            max_broken_grain DECIMAL(6,2) NULL,
            min_gluten DECIMAL(6,2) NULL,
            source_type ENUM("manual", "official_source") NOT NULL DEFAULT "manual",
            source_name VARCHAR(180) NULL,
            source_url VARCHAR(255) NULL,
            source_date DATE NULL,
            approved_by BIGINT UNSIGNED NULL,
            approved_at TIMESTAMP NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY product_acceptance_criteria_product_id_index (product_id),
            KEY product_acceptance_criteria_approved_by_index (approved_by),
            CONSTRAINT product_acceptance_criteria_product_id_foreign
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT product_acceptance_criteria_approved_by_foreign
                FOREIGN KEY (approved_by) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'ALTER TABLE silos ADD COLUMN visual_type ENUM("vertical", "horizontal") NOT NULL DEFAULT "vertical" AFTER product_id',
        'ALTER TABLE sample_analysis ADD COLUMN acceptance_status ENUM("accepted", "requires_approval", "rejected") NOT NULL DEFAULT "requires_approval" AFTER result',
        'ALTER TABLE sample_analysis ADD COLUMN acceptance_criteria_id BIGINT UNSIGNED NULL AFTER acceptance_status',
    ],
    'down' => [
        'ALTER TABLE sample_analysis DROP COLUMN acceptance_criteria_id',
        'ALTER TABLE sample_analysis DROP COLUMN acceptance_status',
        'ALTER TABLE silos DROP COLUMN visual_type',
        'DROP TABLE IF EXISTS product_acceptance_criteria',
    ],
];
