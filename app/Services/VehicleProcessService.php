<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

final class VehicleProcessService
{
    public const STATUS_GROUPS = [
        'waiting' => ['pending', 'ürün_bildirimi'],
        'arrived' => ['kantara_geldi', 'giriş_bariyeri_açıldı', 'kantarda'],
        'first_weighed' => ['ilk_tartım_alındı'],
        'analysis_waiting' => ['analiz_bekliyor', 'analizde'],
        'analyzed' => ['analiz_yapıldı', 'silo_belirlendi', 'barkod_bekliyor'],
        'directed' => ['siloya_yönlendirildi'],
        'unloading' => ['boşaltımda'],
        'second_waiting' => ['ikinci_tartım_bekliyor'],
        'completed' => ['tamamlandı'],
        'rejected' => ['ret', 'alıma_girmedi'],
    ];

    public const STATUS_LABELS = [
        'ürün_bildirimi' => 'Ürün bildirimi',
        'pending' => 'Beklemede',
        'kantara_geldi' => 'Kantara geldi',
        'giriş_bariyeri_açıldı' => 'Giriş bariyeri açıldı',
        'kantarda' => 'Kantarda',
        'ilk_tartım_alındı' => 'İlk tartım alındı',
        'analiz_bekliyor' => 'Analiz bekliyor',
        'analizde' => 'Analizde',
        'analiz_yapıldı' => 'Analiz yapıldı',
        'silo_belirlendi' => 'Silo belirlendi',
        'barkod_bekliyor' => 'Barkod bekliyor',
        'barkod_basıldı' => 'Barkod basıldı',
        'siloya_yönlendirildi' => 'Siloya yönlendirildi',
        'boşaltımda' => 'Boşaltımda',
        'ikinci_tartım_bekliyor' => 'İkinci tartım bekliyor',
        'ikinci_tartım_alındı' => 'İkinci tartım alındı',
        'tamamlandı' => 'Tamamlandı',
        'iptal' => 'İptal',
        'ret' => 'Ret',
    ];

    public function counts(): array
    {
        $counts = [];
        foreach (self::STATUS_GROUPS as $group => $statuses) {
            $counts[$group] = $this->countByStatuses($statuses);
        }
        $counts['delayed_notifications'] = $this->countDelayedNotifications();

        return $counts;
    }

    public function listByGroup(string $group): array
    {
        if ($group === 'delayed_notifications') {
            return $this->delayedNotifications();
        }

        return $this->listByStatuses(self::STATUS_GROUPS[$group] ?? []);
    }

    public function listByStatuses(array $statuses, int $limit = 100): array
    {
        $this->ensureRejectionSchema();

        if ($statuses === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
        $statement = Database::connection()->prepare(
            $this->baseSelect() . '
             WHERE dn.status IN (' . $placeholders . ')
             ORDER BY dn.updated_at DESC, dn.id DESC
             LIMIT ' . $limit
        );
        $statement->execute(array_values($statuses));

        return $statement->fetchAll();
    }

    public function recent(int $limit = 10): array
    {
        $this->ensureRejectionSchema();

        return Database::connection()
            ->query($this->baseSelect() . ' ORDER BY dn.updated_at DESC, dn.id DESC LIMIT ' . $limit)
            ->fetchAll();
    }

    public function detail(int $entryId): ?array
    {
        $this->ensureRejectionSchema();

        $statement = Database::connection()->prepare($this->baseSelect() . ' WHERE dn.id = :id LIMIT 1');
        $statement->execute(['id' => $entryId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            return null;
        }

        $record['history'] = VehicleProcessHistory::forEntry($entryId);
        $record['status_label'] = self::STATUS_LABELS[$record['status']] ?? $record['status'];

        return $record;
    }

    public function countByStatuses(array $statuses): int
    {
        $this->ensureRejectionSchema();

        if ($statuses === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM delivery_notifications WHERE status IN (' . $placeholders . ')'
        );
        $statement->execute(array_values($statuses));

        return (int) $statement->fetchColumn();
    }

    private function baseSelect(): string
    {
        return 'SELECT
                dn.id AS entry_id,
                dn.notification_number,
                dn.status,
                dn.updated_at,
                dn.created_at,
                dn.operation_status,
                dn.operation_closed_at,
                dn.expected_arrival_date,
                dn.expected_quantity_kg,
                dn.company_notified_at,
                dn.company_notified_note,
                dn.notification_note,
                dn.cancel_reason,
                dn.cancel_note,
                dn.cancelled_at,
                cancelled_user.name AS cancelled_by_name,
                notified_user.name AS company_notified_by_name,
                dn.sender_type,
                dn.sender_name,
                dn.dispatch_number,
                dn.identity_number,
                CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE COALESCE(c.name, "-") END AS sender_display,
                c.name AS company_name,
                p.name AS product_name,
                v.plate_number,
                v.driver_name,
                v.driver_phone,
                wr.id AS weighbridge_record_id,
                wr.ticket_number AS weighbridge_ticket,
                wr.first_weight_kg,
                wr.first_weighed_at,
                wr.second_weight_kg,
                wr.second_weighed_at,
                wr.net_weight_kg,
                wr.assigned_silo_id,
                sa.id AS analysis_id,
                sa.moisture,
                sa.protein,
                sa.hectoliter,
                sa.gluten,
                sa.sunn_pest_rate,
                sa.foreign_material,
                sa.broken_grain,
                sa.result AS analysis_result,
                sa.result_status,
                sa.conditional_reason,
                sa.conditional_note,
                sa.rejection_reason,
                sa.rejection_note,
                sa.rejected_at,
                sa.analyzed_at,
                rd.id AS rejection_document_id,
                rd.document_no AS rejection_document_no,
                bt.id AS barcode_ticket_id,
                bt.barcode,
                bt.status AS barcode_status,
                bt.issued_at,
                s.code AS silo_code,
                s.name AS silo_name,
                uo.id AS unloading_operation_id,
                uo.status AS unloading_status,
                uo.started_at AS unloading_started_at,
                uo.completed_at AS unloading_completed_at
             FROM delivery_notifications dn
             LEFT JOIN companies c ON c.id = dn.company_id
             LEFT JOIN products p ON p.id = dn.product_id
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             LEFT JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
             LEFT JOIN rejection_documents rd ON rd.analysis_id = sa.id
             LEFT JOIN barcode_tickets bt ON bt.weighbridge_record_id = wr.id
             LEFT JOIN silos s ON s.id = wr.assigned_silo_id
             LEFT JOIN unloading_operations uo ON uo.weighbridge_record_id = wr.id
             LEFT JOIN users cancelled_user ON cancelled_user.id = dn.cancelled_by
             LEFT JOIN users notified_user ON notified_user.id = dn.company_notified_by';
    }

    private function countDelayedNotifications(): int
    {
        $statement = Database::connection()->query(
            'SELECT COUNT(*)
             FROM delivery_notifications
             WHERE entry_type = "pre_notified"
                AND status IN ("pending", "ürün_bildirimi")
                AND expected_arrival_date IS NOT NULL
                AND DATE(expected_arrival_date) < DATE("now")'
        );

        return (int) $statement->fetchColumn();
    }

    private function delayedNotifications(): array
    {
        return Database::connection()
            ->query($this->baseSelect() . '
             WHERE dn.entry_type = "pre_notified"
                AND dn.status IN ("pending", "ürün_bildirimi")
                AND dn.expected_arrival_date IS NOT NULL
                AND DATE(dn.expected_arrival_date) < DATE("now")
             ORDER BY dn.expected_arrival_date ASC, dn.id ASC
             LIMIT 100')
            ->fetchAll();
    }

    private function ensureRejectionSchema(): void
    {
        try {
            $database = Database::connection();
            $driver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);
            $columns = $driver === 'sqlite'
                ? array_column($database->query('PRAGMA table_info(sample_analysis)')->fetchAll(PDO::FETCH_ASSOC), 'name')
                : array_column($database->query('SHOW COLUMNS FROM sample_analysis')->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $notificationColumns = $driver === 'sqlite'
                ? array_column($database->query('PRAGMA table_info(delivery_notifications)')->fetchAll(PDO::FETCH_ASSOC), 'name')
                : array_column($database->query('SHOW COLUMNS FROM delivery_notifications')->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $definitions = $driver === 'sqlite'
                ? [
                    'result_status' => 'TEXT NOT NULL DEFAULT "kabul"',
                    'conditional_reason' => 'TEXT NULL',
                    'conditional_note' => 'TEXT NULL',
                    'rejection_reason' => 'TEXT NULL',
                    'rejection_note' => 'TEXT NULL',
                    'rejected_at' => 'TEXT NULL',
                    'rejected_by' => 'INTEGER NULL',
                ]
                : [
                    'result_status' => 'ENUM("kabul", "sartli_kabul", "ret") NOT NULL DEFAULT "kabul"',
                    'conditional_reason' => 'VARCHAR(120) NULL',
                    'conditional_note' => 'TEXT NULL',
                    'rejection_reason' => 'VARCHAR(120) NULL',
                    'rejection_note' => 'TEXT NULL',
                    'rejected_at' => 'TIMESTAMP NULL',
                    'rejected_by' => 'BIGINT UNSIGNED NULL',
                ];

            foreach ($definitions as $column => $definition) {
                if (! in_array($column, $columns, true)) {
                    $database->exec('ALTER TABLE sample_analysis ADD COLUMN ' . $column . ' ' . $definition);
                }
            }

            $notificationDefinitions = $driver === 'sqlite'
                ? [
                    'operation_status' => 'TEXT NOT NULL DEFAULT "open"',
                    'operation_closed_at' => 'TEXT NULL',
                ]
                : [
                    'operation_status' => 'VARCHAR(40) NOT NULL DEFAULT "open"',
                    'operation_closed_at' => 'TIMESTAMP NULL',
                ];

            foreach ($notificationDefinitions as $column => $definition) {
                if (! in_array($column, $notificationColumns, true)) {
                    $database->exec('ALTER TABLE delivery_notifications ADD COLUMN ' . $column . ' ' . $definition);
                }
            }

            $database->exec(
                'UPDATE sample_analysis
                 SET result_status = "ret",
                     rejection_reason = COALESCE(NULLIF(rejection_reason, ""), "not_suitable"),
                     rejected_at = COALESCE(rejected_at, analyzed_at, NOW())
                 WHERE result = "rejected" OR result_status = "ret"'
            );
            $database->exec(
                'UPDATE weighbridge_records
                 SET assigned_silo_id = NULL, status = "rejected"
                 WHERE id IN (
                    SELECT weighbridge_record_id
                    FROM sample_analysis
                    WHERE result = "rejected" OR result_status = "ret"
                 )'
            );
            $database->exec(
                'UPDATE delivery_notifications
                 SET status = "ret",
                     operation_status = "closed",
                     operation_closed_at = COALESCE(operation_closed_at, NOW()),
                     updated_at = NOW()
                 WHERE id IN (
                    SELECT wr.delivery_notification_id
                    FROM weighbridge_records wr
                    INNER JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
                    WHERE sa.result = "rejected" OR sa.result_status = "ret"
                 )'
            );
            $database->exec(
                'UPDATE barcode_tickets
                 SET status = "cancelled", updated_at = NOW()
                 WHERE sample_analysis_id IN (
                    SELECT id
                    FROM sample_analysis
                    WHERE result = "rejected" OR result_status = "ret"
                 )'
            );
            $database->exec(
                $driver === 'sqlite'
                    ? 'CREATE TABLE IF NOT EXISTS vehicle_process_history (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        entry_id INTEGER NOT NULL,
                        old_status TEXT NULL,
                        new_status TEXT NOT NULL,
                        action_name TEXT NOT NULL,
                        description TEXT NULL,
                        user_id INTEGER NULL,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP
                    )'
                    : 'CREATE TABLE IF NOT EXISTS vehicle_process_history (
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
            $database->exec(
                'INSERT INTO vehicle_process_history (entry_id, old_status, new_status, action_name, description, user_id, created_at)
                 SELECT dn.id, NULL, "ret", "Araç analiz sonucu reddedildi, operasyon sonlandırıldı.", COALESCE(sa.rejection_reason, "not_suitable"), NULL, NOW()
                 FROM delivery_notifications dn
                 INNER JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
                 INNER JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
                 WHERE dn.status = "ret"
                    AND (sa.result = "rejected" OR sa.result_status = "ret")
                    AND NOT EXISTS (
                        SELECT 1
                        FROM vehicle_process_history vph
                        WHERE vph.entry_id = dn.id
                           AND vph.new_status = "ret"
                           AND vph.action_name = "Araç analiz sonucu reddedildi, operasyon sonlandırıldı."
                    )'
            );

            $database->exec(
                $driver === 'sqlite'
                    ? 'CREATE TABLE IF NOT EXISTS rejection_documents (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        entry_id INTEGER NOT NULL,
                        analysis_id INTEGER NOT NULL,
                        document_no TEXT NOT NULL UNIQUE,
                        rejection_reason TEXT NOT NULL,
                        rejection_note TEXT NULL,
                        printed_at TEXT NULL,
                        printed_by INTEGER NULL,
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                    )'
                    : 'CREATE TABLE IF NOT EXISTS rejection_documents (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        entry_id BIGINT UNSIGNED NOT NULL,
                        analysis_id BIGINT UNSIGNED NOT NULL,
                        document_no VARCHAR(80) NOT NULL UNIQUE,
                        rejection_reason VARCHAR(120) NOT NULL,
                        rejection_note TEXT NULL,
                        printed_at TIMESTAMP NULL,
                        printed_by BIGINT UNSIGNED NULL,
                        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable) {
        }
    }
}
