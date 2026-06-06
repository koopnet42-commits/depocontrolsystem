<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE vehicle_process_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(80) NULL,
            new_status VARCHAR(80) NOT NULL,
            action_name VARCHAR(160) NOT NULL,
            description TEXT NULL,
            user_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY vehicle_process_history_entry_id_index (entry_id),
            KEY vehicle_process_history_user_id_index (user_id),
            CONSTRAINT vehicle_process_history_entry_id_foreign
                FOREIGN KEY (entry_id) REFERENCES delivery_notifications(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT vehicle_process_history_user_id_foreign
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS vehicle_process_history',
    ],
];

