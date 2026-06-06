<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use Throwable;

final class AuditLogger
{
    public static function log(string $action, ?string $tableName = null, ?int $recordId = null, array $payload = []): void
    {
        try {
            self::ensureTable();

            $statement = Database::connection()->prepare(
                'INSERT INTO audit_logs
                    (user_id, user_name, user_role, target_user_id, action, table_name, record_id, ip_address, user_agent, description, old_values, new_values, payload, created_at)
                 VALUES
                    (:user_id, :user_name, :user_role, :target_user_id, :action, :table_name, :record_id, :ip_address, :user_agent, :description, :old_values, :new_values, :payload, NOW())'
            );
            $user = Auth::user();
            $statement->execute([
                'user_id' => $user === null ? null : (int) $user['id'],
                'user_name' => $user['name'] ?? null,
                'user_role' => $user['role'] ?? null,
                'target_user_id' => $payload['target_user_id'] ?? null,
                'action' => $action,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                'description' => $payload['service_note'] ?? null,
                'old_values' => isset($payload['old']) ? json_encode($payload['old'], JSON_UNESCAPED_UNICODE) : null,
                'new_values' => isset($payload['new']) ? json_encode($payload['new'], JSON_UNESCAPED_UNICODE) : null,
                'payload' => json_encode(self::sanitize($payload), JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable) {
            // Audit logging must never break an operational flow.
        }
    }

    private static function sanitize(array $payload): array
    {
        foreach (['password', 'password_confirmation'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[filtered]';
            }
        }

        return $payload;
    }

    private static function ensureTable(): void
    {
        $driver = Database::connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            Database::connection()->exec(
                'CREATE TABLE IF NOT EXISTS audit_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NULL,
                    user_name TEXT NULL,
                    user_role TEXT NULL,
                    target_user_id INTEGER NULL,
                    action TEXT NOT NULL,
                    table_name TEXT NULL,
                    record_id INTEGER NULL,
                    ip_address TEXT NULL,
                    user_agent TEXT NULL,
                    description TEXT NULL,
                    old_values TEXT NULL,
                    new_values TEXT NULL,
                    payload TEXT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )'
            );
            self::ensureSqliteColumns();
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS audit_logs (
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
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function ensureSqliteColumns(): void
    {
        $columns = array_column(Database::connection()->query('PRAGMA table_info(audit_logs)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
        $definitions = [
            'target_user_id' => 'INTEGER NULL',
            'description' => 'TEXT NULL',
            'old_values' => 'TEXT NULL',
            'new_values' => 'TEXT NULL',
        ];

        foreach ($definitions as $column => $definition) {
            if (! in_array($column, $columns, true)) {
                Database::connection()->exec('ALTER TABLE audit_logs ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }
}
