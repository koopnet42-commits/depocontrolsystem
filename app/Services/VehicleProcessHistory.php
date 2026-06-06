<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use Throwable;

final class VehicleProcessHistory
{
    public static function record(int $entryId, ?string $oldStatus, string $newStatus, string $actionName, ?string $description = null): void
    {
        try {
            self::ensureTable();

            Database::connection()
                ->prepare(
                    'INSERT INTO vehicle_process_history
                        (entry_id, old_status, new_status, action_name, description, user_id, created_at)
                     VALUES
                        (:entry_id, :old_status, :new_status, :action_name, :description, :user_id, NOW())'
                )
                ->execute([
                    'entry_id' => $entryId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'action_name' => $actionName,
                    'description' => $description,
                    'user_id' => Auth::user()['id'] ?? null,
                ]);
        } catch (Throwable) {
            // History must never block operational flow.
        }
    }

    public static function changeStatus(int $entryId, string $newStatus, string $actionName, ?string $description = null): void
    {
        $statement = Database::connection()->prepare('SELECT status FROM delivery_notifications WHERE id = :id');
        $statement->execute(['id' => $entryId]);
        $oldStatus = $statement->fetchColumn();

        Database::connection()
            ->prepare('UPDATE delivery_notifications SET status = :status, updated_at = NOW() WHERE id = :id')
            ->execute(['id' => $entryId, 'status' => $newStatus]);

        self::record($entryId, $oldStatus === false ? null : (string) $oldStatus, $newStatus, $actionName, $description);
    }

    public static function forEntry(int $entryId): array
    {
        self::ensureTable();

        $statement = Database::connection()->prepare(
            'SELECT vph.*, u.name AS user_name
             FROM vehicle_process_history vph
             LEFT JOIN users u ON u.id = vph.user_id
             WHERE vph.entry_id = :entry_id
             ORDER BY vph.created_at ASC, vph.id ASC'
        );
        $statement->execute(['entry_id' => $entryId]);

        return $statement->fetchAll();
    }

    private static function ensureTable(): void
    {
        $driver = Database::connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            Database::connection()->exec(
                'CREATE TABLE IF NOT EXISTS vehicle_process_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entry_id INTEGER NOT NULL,
                    old_status TEXT NULL,
                    new_status TEXT NOT NULL,
                    action_name TEXT NOT NULL,
                    description TEXT NULL,
                    user_id INTEGER NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )'
            );
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS vehicle_process_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entry_id BIGINT UNSIGNED NOT NULL,
                old_status VARCHAR(80) NULL,
                new_status VARCHAR(80) NOT NULL,
                action_name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX vehicle_process_history_entry_id_index (entry_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
