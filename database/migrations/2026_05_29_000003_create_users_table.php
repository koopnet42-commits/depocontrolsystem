<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(140) NOT NULL,
            email VARCHAR(160) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM("admin", "weighbridge", "lab", "silo", "manager") NOT NULL DEFAULT "weighbridge",
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY users_email_unique (email),
            KEY users_role_index (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS users',
    ],
];
