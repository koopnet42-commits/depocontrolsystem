<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE sample_analysis (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            analysis_number VARCHAR(60) NOT NULL,
            weighbridge_record_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            moisture DECIMAL(6,2) NULL,
            protein DECIMAL(6,2) NULL,
            hectoliter DECIMAL(6,2) NULL,
            gluten DECIMAL(6,2) NULL,
            sunn_pest_rate DECIMAL(6,2) NULL,
            foreign_material DECIMAL(6,2) NULL,
            broken_grain DECIMAL(6,2) NULL,
            result ENUM("accepted", "conditional", "rejected") NOT NULL DEFAULT "accepted",
            status ENUM("pending", "completed") NOT NULL DEFAULT "completed",
            analyzed_by BIGINT UNSIGNED NULL,
            analyzed_at TIMESTAMP NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY sample_analysis_analysis_number_unique (analysis_number),
            UNIQUE KEY sample_analysis_weighbridge_record_id_unique (weighbridge_record_id),
            KEY sample_analysis_product_id_index (product_id),
            KEY sample_analysis_analyzed_by_index (analyzed_by),
            CONSTRAINT sample_analysis_weighbridge_record_id_foreign
                FOREIGN KEY (weighbridge_record_id) REFERENCES weighbridge_records(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT sample_analysis_product_id_foreign
                FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT sample_analysis_analyzed_by_foreign
                FOREIGN KEY (analyzed_by) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS sample_analysis',
    ],
];
