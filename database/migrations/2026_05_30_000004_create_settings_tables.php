<?php

declare(strict_types=1);

return [
    'up' => [
        'CREATE TABLE company_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(180) NOT NULL,
            logo_path VARCHAR(255) NULL,
            address TEXT NULL,
            tax_office VARCHAR(120) NULL,
            tax_number VARCHAR(80) NULL,
            phone VARCHAR(80) NULL,
            email VARCHAR(160) NULL,
            website VARCHAR(180) NULL,
            contact_person VARCHAR(160) NULL,
            license_number VARCHAR(120) NULL,
            letterhead_text TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE camera_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            usage_location VARCHAR(80) NOT NULL,
            camera_type VARCHAR(80) NOT NULL,
            connection_url VARCHAR(255) NULL,
            port INT NULL,
            username VARCHAR(120) NULL,
            password_encrypted TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE scale_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            usage_location VARCHAR(80) NOT NULL,
            communication_type VARCHAR(80) NOT NULL,
            ip_address VARCHAR(120) NULL,
            port INT NULL,
            com_port VARCHAR(40) NULL,
            baud_rate INT NULL,
            data_bits INT NULL,
            stop_bits VARCHAR(20) NULL,
            parity VARCHAR(40) NULL,
            read_format VARCHAR(180) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE barrier_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            usage_location VARCHAR(80) NOT NULL,
            control_type VARCHAR(80) NOT NULL,
            ip_address VARCHAR(120) NULL,
            port INT NULL,
            relay_number VARCHAR(80) NULL,
            open_command VARCHAR(160) NULL,
            close_command VARCHAR(160) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE system_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            operation_mode VARCHAR(40) NOT NULL DEFAULT "simulation",
            default_printer VARCHAR(160) NULL,
            barcode_type VARCHAR(40) NOT NULL DEFAULT "QR",
            auto_plate_recognition TINYINT(1) NOT NULL DEFAULT 0,
            manual_weight_allowed TINYINT(1) NOT NULL DEFAULT 1,
            manual_weight_reason_required TINYINT(1) NOT NULL DEFAULT 1,
            critical_confirmation_enabled TINYINT(1) NOT NULL DEFAULT 1,
            auto_backup_enabled TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ],
    'down' => [
        'DROP TABLE IF EXISTS system_settings',
        'DROP TABLE IF EXISTS barrier_settings',
        'DROP TABLE IF EXISTS scale_settings',
        'DROP TABLE IF EXISTS camera_settings',
        'DROP TABLE IF EXISTS company_settings',
    ],
];
