<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\OutboundProcessHistory;
use App\Services\PlateReaderService;
use App\Services\VehicleProcessHistory;
use PDO;

final class SecondWeighingController extends Controller
{
    public function __construct(private readonly PlateReaderService $plateReader = new PlateReaderService())
    {
    }

    public function index(): void
    {
        $this->ensureOutboundSchema();
        $query = trim((string) ($this->input('q', '') ?: $this->input('plate', '')));
        $plate = $this->plateReader->normalize($query);
        $recordId = (int) $this->input('record_id');
        $outboundId = (int) $this->input('outbound_id');
        $waitingRecords = $this->combinedSecondWeighingQueue($query);
        $record = $outboundId > 0
            ? $this->findOutboundSecondWeighingById($outboundId)
            : ($recordId > 0 ? $this->findSecondWeighingRecordById($recordId) : $this->findSecondWeighingRecordByQuery($query));

        $this->view('second_weighing/index', [
            'title' => 'İkinci Tartım',
            'plate' => $plate,
            'query' => $query,
            'record' => $record,
            'waitingRecords' => $waitingRecords,
            'message' => (string) $this->input('message', ''),
            'validation' => $this->consumeValidation(),
        ]);
    }

    public function complete(): void
    {
        $this->ensureOutboundSchema();
        $outboundId = (int) $this->input('outbound_id');
        if ($outboundId > 0) {
            $this->completeOutbound($outboundId);
            return;
        }

        $recordId = (int) $this->input('record_id');
        $record = $this->findSecondWeighingRecordById($recordId);

        if ($record === null) {
            $this->redirect('/second-weighing?message=not_found');
        }

        $secondWeight = $this->decimalInputOrNull('second_weight_kg');
        $reason = $this->nullableInput('second_weight_reason');

        $errors = [];

        if ($secondWeight === null || (float) $secondWeight < 1000 || (float) $secondWeight > 100000) {
            $errors['second_weight_kg'] = 'İkinci tartım 1.000 kg ile 100.000 kg arasında girilmelidir.';
        }

        if ($reason === null) {
            $errors['second_weight_reason'] = 'Manuel ikinci tartım değeri için açıklama zorunludur.';
        }

        $firstWeight = (float) $record['first_weight_kg'];
        $secondWeightFloat = (float) $secondWeight;

        if ($secondWeightFloat >= $firstWeight) {
            $errors['second_weight_kg'] = 'Ürün girişinde ikinci tartım ilk tartımdan küçük olmalıdır.';
        }

        if ($errors !== []) {
            $this->redirectWithValidation('/second-weighing?record_id=' . (int) $record['weighbridge_record_id'] . '&entry_id=' . (int) $record['delivery_notification_id'] . '&vehicle_step=7&message=invalid', $errors);
        }

        $netWeight = number_format($firstWeight - $secondWeightFloat, 3, '.', '');
        $database = Database::connection();

        $database->beginTransaction();

        try {
            $statement = $database->prepare(
                'UPDATE weighbridge_records
                 SET second_weight_kg = :second_weight_kg,
                     second_weighed_at = NOW(),
                     net_weight_kg = :net_weight_kg,
                     status = "completed",
                     notes = :notes
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $recordId,
                'second_weight_kg' => $secondWeight,
                'net_weight_kg' => $netWeight,
                'notes' => trim((string) ($record['notes'] ?? '') . "\n2. tartım manuel giriş nedeni: " . $reason),
            ]);

            $statement = $database->prepare('SELECT current_stock_kg FROM silos WHERE id = :id LIMIT 1');
            $statement->execute(['id' => (int) $record['assigned_silo_id']]);
            $beforeStock = (float) $statement->fetchColumn();
            $afterStock = $beforeStock + (float) $netWeight;

            $database->prepare('UPDATE silos SET current_stock_kg = :after_stock WHERE id = :id')->execute([
                'id' => (int) $record['assigned_silo_id'],
                'after_stock' => $afterStock,
            ]);
            $database->prepare(
                'INSERT INTO silo_stock_movements
                    (silo_id, visit_id, movement_type, product_id, quantity_kg, before_quantity_kg, after_quantity_kg, created_at, note)
                 VALUES
                    (:silo_id, :visit_id, "IN", :product_id, :quantity_kg, :before_quantity_kg, :after_quantity_kg, NOW(), :note)'
            )->execute([
                'silo_id' => (int) $record['assigned_silo_id'],
                'visit_id' => (int) $record['delivery_notification_id'],
                'product_id' => (int) $record['product_id'],
                'quantity_kg' => $netWeight,
                'before_quantity_kg' => $beforeStock,
                'after_quantity_kg' => $afterStock,
                'note' => 'Ürün girişi 2. tartım tamamlandı.',
            ]);

            VehicleProcessHistory::record((int) $record['delivery_notification_id'], (string) $record['delivery_status'], 'ikinci_tartım_alındı', 'İkinci tartım alındı');
            VehicleProcessHistory::changeStatus((int) $record['delivery_notification_id'], 'tamamlandı', 'İşlem tamamlandı');

            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        $this->redirect('/second-weighing?entry_id=' . (int) $record['delivery_notification_id'] . '&vehicle_step=8&process_focus=1&message=completed');
    }

    private function completeOutbound(int $outboundId): void
    {
        $record = $this->findOutboundSecondWeighingById($outboundId);
        if ($record === null) {
            $this->redirect('/second-weighing?message=not_found');
        }

        $secondWeight = $this->decimalInputOrNull('second_weight_kg');
        $reason = $this->nullableInput('second_weight_reason');
        $errors = [];

        if ($secondWeight === null || (float) $secondWeight < 1000 || (float) $secondWeight > 100000) {
            $errors['second_weight_kg'] = 'İkinci tartım 1.000 kg ile 100.000 kg arasında girilmelidir.';
        }
        if ($reason === null) {
            $errors['second_weight_reason'] = 'Manuel ikinci tartım değeri için açıklama zorunludur.';
        }

        $firstWeight = (float) $record['first_weight_kg'];
        $secondWeightFloat = (float) $secondWeight;
        $netWeight = $secondWeightFloat - $firstWeight;

        if ($netWeight <= 0) {
            $errors['second_weight_kg'] = 'Ürün çıkışında ikinci tartım birinci tartımdan büyük olmalıdır.';
        }
        if ((float) $record['current_stock_kg'] < $netWeight) {
            $errors['second_weight_kg'] = 'Seçilen siloda yeterli stok yok.';
        }

        if ($errors !== []) {
            $this->redirectWithValidation('/second-weighing?outbound_id=' . $outboundId . '&message=invalid', $errors);
        }

        $database = Database::connection();
        $database->beginTransaction();
        try {
            $statement = $database->prepare('SELECT current_stock_kg, product_id FROM silos WHERE id = :id LIMIT 1');
            $statement->execute(['id' => (int) $record['assigned_silo_id']]);
            $silo = $statement->fetch(PDO::FETCH_ASSOC);
            if ($silo === false || (int) $silo['product_id'] !== (int) $record['product_id'] || (float) $silo['current_stock_kg'] < $netWeight) {
                $database->rollBack();
                $this->redirect('/second-weighing?outbound_id=' . $outboundId . '&message=invalid');
            }

            $before = (float) $silo['current_stock_kg'];
            $after = $before - $netWeight;

            $database->prepare(
                'UPDATE outbound_loadings
                 SET second_weight_kg = :second_weight_kg, second_weighed_at = NOW(), net_quantity_kg = :net_quantity_kg,
                     status = "OUTBOUND_COMPLETED", completed_at = NOW(), updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'id' => $outboundId,
                'second_weight_kg' => $secondWeight,
                'net_quantity_kg' => number_format($netWeight, 3, '.', ''),
            ]);

            OutboundProcessHistory::record(
                $outboundId,
                (string) $record['status'],
                'OUTBOUND_COMPLETED',
                '2. tartım alındı, çıkış tamamlandı',
                number_format($netWeight, 0, ',', '.') . ' kg net çıkış'
            );

            $database->prepare('UPDATE silos SET current_stock_kg = :after WHERE id = :id')
                ->execute(['id' => (int) $record['assigned_silo_id'], 'after' => $after]);

            $database->prepare(
                'INSERT INTO silo_stock_movements
                    (silo_id, visit_id, movement_type, product_id, quantity_kg, before_quantity_kg, after_quantity_kg, created_at, note)
                 VALUES
                    (:silo_id, :visit_id, "OUT", :product_id, :quantity_kg, :before_quantity_kg, :after_quantity_kg, NOW(), :note)'
            )->execute([
                'silo_id' => (int) $record['assigned_silo_id'],
                'visit_id' => $outboundId,
                'product_id' => (int) $record['product_id'],
                'quantity_kg' => number_format($netWeight, 3, '.', ''),
                'before_quantity_kg' => $before,
                'after_quantity_kg' => $after,
                'note' => 'Ürün çıkışı 2. tartım tamamlandı. ' . $reason,
            ]);

            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        $this->redirect('/second-weighing?message=completed');
    }

    private function findSecondWeighingRecordByQuery(string $query): ?array
    {
        if ($query === '') {
            return null;
        }

        $statement = Database::connection()->prepare($this->secondWeighingSelect() . '
             WHERE ' . $this->secondWeighingWhere() . '
                AND ' . $this->exactSearchWhere() . '
             ORDER BY COALESCE(uo.completed_at, bt.issued_at, wr.updated_at) DESC, wr.id DESC
             LIMIT 2');
        $statement->execute($this->exactSearchParams($query));
        $records = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (count($records) === 1) {
            return $records[0];
        }

        if ($records !== []) {
            return null;
        }

        $filtered = $this->secondWeighingQueue($query, 2);

        return count($filtered) === 1 ? $filtered[0] : null;
    }

    private function findOutboundSecondWeighingById(int $id): ?array
    {
        $statement = Database::connection()->prepare($this->outboundSecondWeighingSelect() . '
             WHERE ol.id = :id
                AND ol.status = "OUTBOUND_SECOND_WEIGHING_WAITING"
             LIMIT 1');
        $statement->execute(['id' => $id]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findSecondWeighingRecordByPlate(string $plate): ?array
    {
        $statement = Database::connection()->prepare($this->secondWeighingSelect() . '
             WHERE REPLACE(v.plate_number, " ", "") = :plate
                AND ' . $this->secondWeighingWhere() . '
             ORDER BY COALESCE(uo.completed_at, bt.issued_at, wr.updated_at) DESC, wr.id DESC
             LIMIT 1');
        $statement->execute(['plate' => $plate]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findSecondWeighingRecordById(int $recordId): ?array
    {
        $statement = Database::connection()->prepare($this->secondWeighingSelect() . '
             WHERE wr.id = :id
                AND ' . $this->secondWeighingWhere() . '
             LIMIT 1');
        $statement->execute(['id' => $recordId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function secondWeighingQueue(string $query = '', int $limit = 80): array
    {
        $where = $this->secondWeighingWhere();
        $params = [];

        if (trim($query) !== '') {
            $where .= ' AND ' . $this->looseSearchWhere();
            $params = $this->looseSearchParams($query);
        }

        $statement = Database::connection()->prepare($this->secondWeighingSelect() . '
             WHERE ' . $where . '
             ORDER BY COALESCE(uo.completed_at, bt.issued_at, wr.updated_at) ASC, wr.id ASC
             LIMIT ' . max(1, min(100, $limit)));
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function combinedSecondWeighingQueue(string $query = ''): array
    {
        $inbound = $this->secondWeighingQueue($query, 80);
        $outbound = $this->outboundSecondWeighingQueue($query, 80);
        $records = array_merge($inbound, $outbound);
        usort($records, static fn (array $a, array $b): int => strcmp((string) ($a['queue_time'] ?? ''), (string) ($b['queue_time'] ?? '')));

        return $records;
    }

    private function outboundSecondWeighingQueue(string $query = '', int $limit = 80): array
    {
        $where = ['ol.status = "OUTBOUND_SECOND_WEIGHING_WAITING"'];
        $params = [];

        if (trim($query) !== '') {
            $where[] = '(REPLACE(ol.plate_number, " ", "") LIKE :plate_like
                OR UPPER(ol.operation_number) LIKE :text_like
                OR UPPER(COALESCE(c.name, "")) LIKE :text_like
                OR UPPER(COALESCE(ol.sender_name, "")) LIKE :text_like
                OR UPPER(COALESCE(p.name, "")) LIKE :text_like
                OR UPPER(COALESCE(ol.driver_name, "")) LIKE :text_like
                OR UPPER(COALESCE(s.code, "")) LIKE :text_like
                OR UPPER(COALESCE(s.name, "")) LIKE :text_like)';
            $params = [
                'plate_like' => '%' . $this->plateReader->normalize($query) . '%',
                'text_like' => '%' . $this->searchText($query) . '%',
            ];
        }

        $statement = Database::connection()->prepare($this->outboundSecondWeighingSelect() . '
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY COALESCE(ol.assigned_at, ol.updated_at) ASC, ol.id ASC
             LIMIT ' . max(1, min(100, $limit)));
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function secondWeighingSelect(): string
    {
        return 'SELECT
                wr.id AS weighbridge_record_id,
                wr.ticket_number,
                wr.delivery_notification_id,
                wr.assigned_silo_id,
                wr.first_weight_kg,
                wr.first_weighed_at,
                wr.second_weight_kg,
                wr.second_weighed_at,
                wr.net_weight_kg,
                wr.notes,
                wr.status AS weighbridge_status,
                dn.status AS delivery_status,
                "PRODUCT_IN" AS operation_type,
                NULL AS outbound_id,
                c.name AS company_name,
                c.name AS sender_display,
                p.name AS product_name,
                wr.product_id,
                v.plate_number,
                v.driver_name,
                s.code AS silo_code,
                s.name AS silo_name,
                bt.barcode,
                bt.issued_at AS directed_at,
                COALESCE(bt.issued_at, wr.updated_at) AS queue_time,
                uo.completed_at AS unloading_completed_at,
                uo.status AS unloading_status
             FROM weighbridge_records wr
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             INNER JOIN silos s ON s.id = wr.assigned_silo_id
             LEFT JOIN barcode_tickets bt ON bt.weighbridge_record_id = wr.id
             LEFT JOIN unloading_operations uo ON uo.weighbridge_record_id = wr.id';
    }

    private function outboundSecondWeighingSelect(): string
    {
        return 'SELECT
                NULL AS weighbridge_record_id,
                ol.operation_number AS ticket_number,
                NULL AS delivery_notification_id,
                ol.source_silo_id AS assigned_silo_id,
                ol.first_weight_kg,
                ol.first_weighed_at,
                ol.second_weight_kg,
                ol.second_weighed_at,
                ol.net_quantity_kg AS net_weight_kg,
                ol.note AS notes,
                ol.status AS weighbridge_status,
                ol.status AS delivery_status,
                "PRODUCT_OUT" AS operation_type,
                ol.id AS outbound_id,
                CASE WHEN ol.sender_type = "person" THEN COALESCE(ol.sender_name, "-") ELSE COALESCE(c.name, "-") END AS company_name,
                CASE WHEN ol.sender_type = "person" THEN COALESCE(ol.sender_name, "-") ELSE COALESCE(c.name, "-") END AS sender_display,
                p.name AS product_name,
                ol.product_id,
                ol.plate_number,
                ol.driver_name,
                s.code AS silo_code,
                s.name AS silo_name,
                ol.operation_number AS barcode,
                ol.assigned_at AS directed_at,
                COALESCE(ol.assigned_at, ol.updated_at) AS queue_time,
                NULL AS unloading_completed_at,
                NULL AS unloading_status,
                s.current_stock_kg
             FROM outbound_loadings ol
             LEFT JOIN companies c ON c.id = ol.company_id
             INNER JOIN products p ON p.id = ol.product_id
             INNER JOIN silos s ON s.id = ol.source_silo_id';
    }

    private function secondWeighingWhere(): string
    {
        return 'wr.assigned_silo_id IS NOT NULL
                AND wr.first_weight_kg IS NOT NULL
                AND wr.second_weight_kg IS NULL
                AND dn.status = "ikinci_tartım_bekliyor"
                AND wr.status IN ("directed", "unloading", "unloaded")';
    }

    private function exactSearchWhere(): string
    {
        return '(REPLACE(v.plate_number, " ", "") = :plate_exact
                OR UPPER(COALESCE(bt.barcode, "")) = :text_exact
                OR UPPER(wr.ticket_number) = :text_exact
                OR CAST(wr.id AS CHAR) = :numeric_exact
                OR CAST(dn.id AS CHAR) = :numeric_exact)';
    }

    private function looseSearchWhere(): string
    {
        return '(REPLACE(v.plate_number, " ", "") LIKE :plate_like
                OR UPPER(COALESCE(bt.barcode, "")) LIKE :text_like
                OR UPPER(wr.ticket_number) LIKE :text_like
                OR UPPER(COALESCE(c.name, "")) LIKE :text_like
                OR UPPER(COALESCE(p.name, "")) LIKE :text_like
                OR UPPER(COALESCE(v.driver_name, "")) LIKE :text_like
                OR UPPER(COALESCE(s.code, "")) LIKE :text_like
                OR UPPER(COALESCE(s.name, "")) LIKE :text_like
                OR CAST(wr.id AS CHAR) LIKE :number_like
                OR CAST(dn.id AS CHAR) LIKE :number_like)';
    }

    private function exactSearchParams(string $query): array
    {
        $text = $this->searchText($query);

        return [
            'plate_exact' => $this->plateReader->normalize($query),
            'text_exact' => $text,
            'numeric_exact' => preg_replace('/\D+/', '', $query) ?: $text,
        ];
    }

    private function looseSearchParams(string $query): array
    {
        $text = $this->searchText($query);

        return [
            'plate_like' => '%' . $this->plateReader->normalize($query) . '%',
            'text_like' => '%' . $text . '%',
            'number_like' => '%' . (preg_replace('/\D+/', '', $query) ?: $text) . '%',
        ];
    }

    private function searchText(string $query): string
    {
        $value = trim($query);

        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private function ensureOutboundSchema(): void
    {
        $database = Database::connection();
        $database->exec(
            'CREATE TABLE IF NOT EXISTS outbound_loadings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                operation_number TEXT NOT NULL UNIQUE,
                sender_type TEXT NOT NULL DEFAULT "company",
                company_id INTEGER NULL,
                sender_name TEXT NULL,
                plate_number TEXT NOT NULL,
                normalized_plate TEXT NOT NULL,
                driver_name TEXT NULL,
                product_id INTEGER NOT NULL,
                source_silo_id INTEGER NOT NULL,
                planned_quantity_kg REAL NULL,
                operation_type TEXT NOT NULL DEFAULT "PRODUCT_OUT",
                first_weight_kg REAL NULL,
                first_weighed_at TEXT NULL,
                second_weight_kg REAL NULL,
                second_weighed_at TEXT NULL,
                net_quantity_kg REAL NULL,
                status TEXT NOT NULL,
                assigned_at TEXT NULL,
                completed_at TEXT NULL,
                note TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $columns = array_column($database->query('PRAGMA table_info(outbound_loadings)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (! in_array('operation_type', $columns, true)) {
            $database->exec('ALTER TABLE outbound_loadings ADD COLUMN operation_type TEXT NOT NULL DEFAULT "PRODUCT_OUT"');
        }
        $database->exec(
            'CREATE TABLE IF NOT EXISTS silo_stock_movements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                silo_id INTEGER NOT NULL,
                visit_id INTEGER NULL,
                movement_type TEXT NOT NULL,
                product_id INTEGER NULL,
                quantity_kg REAL NOT NULL,
                before_quantity_kg REAL NOT NULL,
                after_quantity_kg REAL NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER NULL,
                note TEXT NULL
            )'
        );
    }

}
