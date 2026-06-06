<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            user_name VARCHAR(120) NULL,
            user_role VARCHAR(60) NULL,
            target_user_id BIGINT UNSIGNED NULL,
            action VARCHAR(120) NOT NULL,
            table_name VARCHAR(120) NULL,
            record_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(60) NULL,
            user_agent VARCHAR(250) NULL,
            description TEXT NULL,
            old_values JSON NULL,
            new_values JSON NULL,
            payload JSON NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY audit_logs_user_id_index (user_id),
            KEY audit_logs_action_index (action),
            KEY audit_logs_table_record_index (table_name, record_id),
            CONSTRAINT audit_logs_user_id_foreign
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS audit_logs',
    ],
];
