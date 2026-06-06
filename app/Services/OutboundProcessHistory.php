<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use Throwable;

final class OutboundProcessHistory
{
    public static function record(int $outboundId, ?string $oldStatus, string $newStatus, string $actionName, ?string $description = null): void
    {
        try {
            self::ensureTable();

            Database::connection()
                ->prepare(
                    'INSERT INTO outbound_process_history
                        (outbound_id, old_status, new_status, action_name, description, user_id, created_at)
                     VALUES
                        (:outbound_id, :old_status, :new_status, :action_name, :description, :user_id, NOW())'
                )
                ->execute([
                    'outbound_id' => $outboundId,
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

    public static function changeStatus(int $outboundId, string $newStatus, string $actionName, ?string $description = null): void
    {
        $statement = Database::connection()->prepare('SELECT status FROM outbound_loadings WHERE id = :id');
        $statement->execute(['id' => $outboundId]);
        $oldStatus = $statement->fetchColumn();

        Database::connection()
            ->prepare('UPDATE outbound_loadings SET status = :status, updated_at = NOW() WHERE id = :id')
            ->execute(['id' => $outboundId, 'status' => $newStatus]);

        self::record($outboundId, $oldStatus === false ? null : (string) $oldStatus, $newStatus, $actionName, $description);
    }

    public static function forOutbound(int $outboundId): array
    {
        self::ensureTable();

        $statement = Database::connection()->prepare(
            'SELECT oph.*, u.name AS user_name
             FROM outbound_process_history oph
             LEFT JOIN users u ON u.id = oph.user_id
             WHERE oph.outbound_id = :outbound_id
             ORDER BY oph.created_at ASC, oph.id ASC'
        );
        $statement->execute(['outbound_id' => $outboundId]);

        return $statement->fetchAll();
    }

    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS outbound_process_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                outbound_id INTEGER NOT NULL,
                old_status TEXT NULL,
                new_status TEXT NOT NULL,
                action_name TEXT NOT NULL,
                description TEXT NULL,
                user_id INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }
}
