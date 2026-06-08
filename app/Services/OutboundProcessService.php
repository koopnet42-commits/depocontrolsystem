<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class OutboundProcessService
{
    public const STATUS_GROUPS = [
        'waiting' => ['OUTBOUND_PRE_NOTIFIED'],
        'first_weigh_waiting' => ['OUTBOUND_ARRIVED'],
        'loading_waiting' => ['OUTBOUND_FIRST_WEIGHED'],
        'loading' => ['OUTBOUND_LOADING_ASSIGNED_TO_SILO'],
        'analysis_waiting' => ['OUTBOUND_ANALYSIS_PENDING'],
        'analysis_done' => ['OUTBOUND_ANALYSIS_DONE'],
        'second_waiting' => ['OUTBOUND_SECOND_WEIGHING_WAITING'],
        'completed' => ['OUTBOUND_COMPLETED'],
        'rejected' => ['OUTBOUND_REJECTED'],
    ];

    public const STATUS_LABELS = [
        'OUTBOUND_PRE_NOTIFIED' => 'Çıkış ön bildirimi',
        'OUTBOUND_ARRIVED' => '1. tartım bekliyor',
        'OUTBOUND_FIRST_WEIGHED' => 'Barkod basıldı, doluma gönderme bekliyor',
        'OUTBOUND_LOADING_ASSIGNED_TO_SILO' => 'Doluma gönderildi',
        'OUTBOUND_ANALYSIS_PENDING' => 'Dolum tamamlandı, analiz bekliyor',
        'OUTBOUND_ANALYSIS_DONE' => 'Analiz tamamlandı, 2. tartım bekliyor',
        'OUTBOUND_SECOND_WEIGHING_WAITING' => '2. tartım bekliyor',
        'OUTBOUND_SECOND_WEIGHED' => '2. tartım alındı',
        'OUTBOUND_COMPLETED' => 'Tamamlandı',
        'OUTBOUND_REJECTED' => 'İptal / ret',
    ];

    public function counts(): array
    {
        $this->ensureSchema();
        $counts = [];
        foreach (self::STATUS_GROUPS as $group => $statuses) {
            $counts[$group] = $this->countByStatuses($statuses);
        }

        return $counts;
    }

    public function listByGroup(string $group, int $limit = 100): array
    {
        return $this->listByStatuses(self::STATUS_GROUPS[$group] ?? [], $limit);
    }

    public function listByStatuses(array $statuses, int $limit = 100): array
    {
        $this->ensureSchema();
        OutboundProcessHistory::ensureTable();

        if ($statuses === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
        $statement = Database::connection()->prepare(
            $this->baseSelect() . '
             WHERE ol.status IN (' . $placeholders . ')
             ORDER BY ol.updated_at DESC, ol.id DESC
             LIMIT ' . $limit
        );
        $statement->execute(array_values($statuses));

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recent(int $limit = 10): array
    {
        $this->ensureSchema();
        OutboundProcessHistory::ensureTable();

        return Database::connection()
            ->query($this->baseSelect() . ' ORDER BY ol.updated_at DESC, ol.id DESC LIMIT ' . $limit)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function detail(int $outboundId): ?array
    {
        $this->ensureSchema();
        OutboundProcessHistory::ensureTable();

        $statement = Database::connection()->prepare($this->baseSelect() . ' WHERE ol.id = :id LIMIT 1');
        $statement->execute(['id' => $outboundId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            return null;
        }

        $record['history'] = OutboundProcessHistory::forOutbound($outboundId);
        $record['status_label'] = self::STATUS_LABELS[$record['status']] ?? $record['status'];

        return $record;
    }

    public function alerts(int $limit = 12): array
    {
        return $this->listByStatuses([
            'OUTBOUND_ARRIVED',
            'OUTBOUND_FIRST_WEIGHED',
            'OUTBOUND_LOADING_ASSIGNED_TO_SILO',
            'OUTBOUND_ANALYSIS_PENDING',
            'OUTBOUND_ANALYSIS_DONE',
            'OUTBOUND_SECOND_WEIGHING_WAITING',
        ], $limit);
    }

    private function countByStatuses(array $statuses): int
    {
        if ($statuses === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM outbound_loadings WHERE status IN (' . $placeholders . ')'
        );
        $statement->execute(array_values($statuses));

        return (int) $statement->fetchColumn();
    }

    private function baseSelect(): string
    {
        return 'SELECT
                ol.id AS outbound_id,
                ol.operation_number,
                ol.status,
                ol.updated_at,
                ol.created_at,
                ol.plate_number,
                ol.driver_name,
                ol.planned_quantity_kg,
                ol.dispatch_number,
                ol.first_weight_kg,
                ol.first_weighed_at,
                ol.second_weight_kg,
                ol.second_weighed_at,
                ol.net_quantity_kg,
                ol.outbound_barcode,
                ol.outbound_barcode_issued_at,
                ol.filling_completed_at,
                ol.analysis_result,
                ol.analysis_note,
                ol.analyzed_at,
                ol.assigned_at,
                ol.sender_type,
                ol.sender_name,
                CASE WHEN ol.sender_type = "person" THEN COALESCE(ol.sender_name, "-") ELSE COALESCE(c.name, "-") END AS sender_display,
                p.name AS product_name,
                s.code AS silo_code,
                s.name AS silo_name,
                s.current_stock_kg
             FROM outbound_loadings ol
             LEFT JOIN companies c ON c.id = ol.company_id
             INNER JOIN products p ON p.id = ol.product_id
             INNER JOIN silos s ON s.id = ol.source_silo_id';
    }

    private function ensureSchema(): void
    {
        $database = Database::connection();
        $columns = array_column($database->query('PRAGMA table_info(outbound_loadings)')->fetchAll(PDO::FETCH_ASSOC), 'name');

        foreach ([
            'outbound_barcode' => 'TEXT NULL',
            'outbound_barcode_issued_at' => 'TEXT NULL',
            'filling_completed_at' => 'TEXT NULL',
            'analysis_result' => 'TEXT NULL',
            'analysis_note' => 'TEXT NULL',
            'analyzed_at' => 'TEXT NULL',
        ] as $column => $definition) {
            if (! in_array($column, $columns, true)) {
                $database->exec('ALTER TABLE outbound_loadings ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }
}
