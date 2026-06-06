<?php

declare(strict_types=1);

return [
    'up' => [
        'ALTER TABLE users MODIFY role ENUM("master", "admin", "kantar_gorevlisi", "laboratuvar_gorevlisi", "silo_gorevlisi", "yonetici", "weighbridge", "lab", "silo", "manager") NOT NULL DEFAULT "kantar_gorevlisi"',
        'ALTER TABLE users ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
        'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_locked',
        'ALTER TABLE users ADD COLUMN failed_login_count INT NOT NULL DEFAULT 0 AFTER last_login_at',
        'ALTER TABLE users ADD COLUMN locked_until TIMESTAMP NULL AFTER failed_login_count',
        'ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER locked_until',
        'ALTER TABLE users ADD COLUMN two_factor_secret_encrypted TEXT NULL AFTER two_factor_enabled',
    ],
    'down' => [
        'ALTER TABLE users DROP COLUMN two_factor_secret_encrypted',
        'ALTER TABLE users DROP COLUMN two_factor_enabled',
        'ALTER TABLE users DROP COLUMN locked_until',
        'ALTER TABLE users DROP COLUMN failed_login_count',
        'ALTER TABLE users DROP COLUMN must_change_password',
        'ALTER TABLE users DROP COLUMN is_locked',
        'ALTER TABLE users MODIFY role ENUM("admin", "weighbridge", "lab", "silo", "manager") NOT NULL DEFAULT "weighbridge"',
    ],
];
