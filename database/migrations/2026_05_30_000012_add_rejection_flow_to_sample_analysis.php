<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE sample_analysis ADD COLUMN result_status ENUM("kabul", "sartli_kabul", "ret") NOT NULL DEFAULT "kabul" AFTER result',
        'ALTER TABLE sample_analysis ADD COLUMN rejection_reason VARCHAR(120) NULL AFTER result_status',
        'ALTER TABLE sample_analysis ADD COLUMN rejection_note TEXT NULL AFTER rejection_reason',
        'ALTER TABLE sample_analysis ADD COLUMN rejected_at TIMESTAMP NULL AFTER rejection_note',
        'ALTER TABLE sample_analysis ADD COLUMN rejected_by BIGINT UNSIGNED NULL AFTER rejected_at',
        'CREATE TABLE rejection_documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id BIGINT UNSIGNED NOT NULL,
            analysis_id BIGINT UNSIGNED NOT NULL,
            document_no VARCHAR(80) NOT NULL,
            rejection_reason VARCHAR(120) NOT NULL,
            rejection_note TEXT NULL,
            printed_at TIMESTAMP NULL,
            printed_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY rejection_documents_document_no_unique (document_no),
            KEY rejection_documents_entry_id_index (entry_id),
            KEY rejection_documents_analysis_id_index (analysis_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS rejection_documents',
        'ALTER TABLE sample_analysis DROP COLUMN rejected_by',
        'ALTER TABLE sample_analysis DROP COLUMN rejected_at',
        'ALTER TABLE sample_analysis DROP COLUMN rejection_note',
        'ALTER TABLE sample_analysis DROP COLUMN rejection_reason',
        'ALTER TABLE sample_analysis DROP COLUMN result_status',
    ],
];
