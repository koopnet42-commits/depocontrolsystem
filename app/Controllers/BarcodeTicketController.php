<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\BarcodeService;
use App\Services\SettingsService;
use App\Services\VehicleProcessHistory;
use PDO;

final class BarcodeTicketController extends Controller
{
    public function __construct(private readonly BarcodeService $barcodeService = new BarcodeService())
    {
    }

    public function index(): void
    {
        $this->repairBarcodeSecondWeighingQueue();
        $this->view('barcode_tickets/index', [
            'title' => 'Barkodlu Sevk Fişleri',
            'records' => $this->routableRecords(),
            'silos' => $this->silos(),
            'message' => (string) $this->input('message', ''),
            'selectedRecordId' => (int) $this->input('record_id'),
        ]);
    }

    public function generate(): void
    {
        $record = $this->findRoutableRecord((int) $this->input('record_id'));

        if ($record === null) {
            $this->redirect('/barcode-tickets?message=not_found');
        }

        if ((int) ($record['assigned_silo_id'] ?? 0) <= 0) {
            $this->redirect('/barcode-tickets?message=missing_silo&record_id=' . (int) $this->input('record_id') . '&entry_id=' . (int) $record['delivery_notification_id'] . '&vehicle_step=4');
        }

        if (! $this->siloMatchesProduct((int) $record['assigned_silo_id'], (int) $record['product_id'])) {
            $this->redirect('/barcode-tickets?message=silo_product_mismatch&record_id=' . (int) $this->input('record_id') . '&entry_id=' . (int) $record['delivery_notification_id'] . '&vehicle_step=4');
        }

        $ticket = $this->findTicketByRecord((int) $record['weighbridge_record_id']);

        if ($ticket === null) {
            $statement = Database::connection()->prepare(
                'INSERT INTO barcode_tickets
                    (barcode, entry_id, weighbridge_record_id, sample_analysis_id, silo_id, issued_at, status)
                 VALUES
                    (:barcode, :entry_id, :weighbridge_record_id, :sample_analysis_id, :silo_id, NOW(), "active")'
            );
            $statement->execute([
                'barcode' => $this->uniqueTicketCode($record),
                'entry_id' => (int) $record['delivery_notification_id'],
                'weighbridge_record_id' => (int) $record['weighbridge_record_id'],
                'sample_analysis_id' => (int) $record['analysis_id'],
                'silo_id' => (int) $record['assigned_silo_id'],
            ]);
        }

        Database::connection()
            ->prepare('UPDATE weighbridge_records SET status = "directed" WHERE id = :id')
            ->execute(['id' => (int) $record['weighbridge_record_id']]);
        if (($record['delivery_status'] ?? '') !== 'siloya_yönlendirildi') {
            VehicleProcessHistory::record((int) $record['delivery_notification_id'], 'silo_belirlendi', 'barkod_basıldı', 'Barkod / yönlendirme fişi basıldı');
        }
        VehicleProcessHistory::changeStatus((int) $record['delivery_notification_id'], 'siloya_yönlendirildi', 'Siloya yönlendirildi', 'Barkod basıldı, araç hedef siloya yönlendirildi.');
        VehicleProcessHistory::changeStatus((int) $record['delivery_notification_id'], 'ikinci_tartım_bekliyor', 'İkinci tartım bekliyor', 'Silo boşaltımı harici operasyon olarak yönetilecek; araç ikinci tartım kuyruğuna alındı.');

        $this->redirect('/barcode-tickets/print?record_id=' . (int) $record['weighbridge_record_id'] . '&entry_id=' . (int) $record['delivery_notification_id']);
    }

    public function assignSilo(): void
    {
        $record = $this->findBarcodeCandidate((int) $this->input('record_id'));
        $siloId = (int) $this->input('silo_id');

        if ($record === null || $siloId <= 0) {
            $this->redirect('/barcode-tickets?message=missing_silo');
        }

        if (! $this->siloMatchesProduct($siloId, (int) $record['product_id'])) {
            $this->redirect('/barcode-tickets?message=silo_product_mismatch&record_id=' . (int) $record['weighbridge_record_id'] . '&entry_id=' . (int) $record['delivery_notification_id'] . '&vehicle_step=4');
        }

        Database::connection()
            ->prepare('UPDATE weighbridge_records SET assigned_silo_id = :silo_id, status = "silo_assigned" WHERE id = :id')
            ->execute(['id' => (int) $record['weighbridge_record_id'], 'silo_id' => $siloId]);
        VehicleProcessHistory::record((int) $record['delivery_notification_id'], (string) $record['delivery_status'], 'silo_belirlendi', 'Manuel silo seçildi');
        VehicleProcessHistory::changeStatus((int) $record['delivery_notification_id'], 'barkod_bekliyor', 'Barkod bekliyor');

        $this->redirect('/barcode-tickets?message=manual_assigned&record_id=' . (int) $record['weighbridge_record_id'] . '&entry_id=' . (int) $record['delivery_notification_id'] . '&vehicle_step=5&process_focus=1');
    }

    public function print(): void
    {
        $record = $this->findPrintableRecord((int) $this->input('record_id'));

        if ($record === null) {
            http_response_code(404);
            echo 'Barkod fişi bulunamadı.';
            return;
        }

        $this->view('barcode_tickets/print', [
            'title' => 'Barkodlu Yönlendirme Fişi',
            'record' => $record,
            'barcodeSvg' => $this->barcodeService->svg($record['barcode']),
            'qrPayload' => $this->qrPayload($record),
        ]);
    }

    public function lookup(): void
    {
        $barcode = trim((string) $this->input('code'));

        if ($barcode === '') {
            $this->redirect('/barcode-tickets');
        }

        $statement = Database::connection()->prepare(
            'SELECT weighbridge_record_id FROM barcode_tickets WHERE barcode = :barcode LIMIT 1'
        );
        $statement->execute(['barcode' => strtoupper($barcode)]);
        $recordId = $statement->fetchColumn();

        if ($recordId === false) {
            $this->redirect('/barcode-tickets?message=not_found');
        }

        $this->redirect('/barcode-tickets/print?record_id=' . (int) $recordId);
    }

    private function routableRecords(): array
    {
        $statement = Database::connection()->query(
            'SELECT
                wr.id AS weighbridge_record_id,
                wr.ticket_number,
                wr.delivery_notification_id,
                wr.first_weight_kg,
                wr.first_weighed_at,
                wr.product_id,
                wr.assigned_silo_id,
                c.name AS company_name,
                p.name AS product_name,
                v.plate_number,
                v.driver_name,
                s.code AS silo_code,
                s.name AS silo_name,
                s.product_id AS silo_product_id,
                sa.id AS analysis_id,
                sa.analyzed_at,
                dn.status AS delivery_status,
                bt.barcode,
                bt.issued_at
             FROM weighbridge_records wr
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             LEFT JOIN silos s ON s.id = wr.assigned_silo_id
             INNER JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             LEFT JOIN barcode_tickets bt ON bt.weighbridge_record_id = wr.id
             WHERE sa.status = "completed"
                AND sa.result <> "rejected"
                AND COALESCE(sa.result_status, "") <> "ret"
                AND dn.status NOT IN ("ret", "alıma_girmedi")
                AND (dn.status IN ("analiz_yapıldı", "silo_belirlendi", "barkod_bekliyor") OR bt.id IS NOT NULL)
             ORDER BY bt.issued_at IS NULL DESC, wr.first_weighed_at DESC, wr.id DESC
             LIMIT 100'
        );

        return $statement->fetchAll();
    }

    private function findRoutableRecord(int $recordId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                wr.id AS weighbridge_record_id,
                wr.delivery_notification_id,
                wr.product_id,
                wr.assigned_silo_id,
                c.name AS company_name,
                s.code AS silo_code,
                sa.id AS analysis_id,
                dn.status AS delivery_status
             FROM weighbridge_records wr
             INNER JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             LEFT JOIN silos s ON s.id = wr.assigned_silo_id
             WHERE wr.id = :id
                AND wr.assigned_silo_id IS NOT NULL
                AND sa.status = "completed"
                AND sa.result <> "rejected"
                AND COALESCE(sa.result_status, "") <> "ret"
                AND dn.status NOT IN ("ret", "alıma_girmedi")
                AND dn.status IN ("analiz_yapıldı", "silo_belirlendi", "barkod_bekliyor")
             LIMIT 1'
        );
        $statement->execute(['id' => $recordId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findPrintableRecord(int $recordId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                wr.id AS weighbridge_record_id,
                wr.ticket_number,
                wr.first_weight_kg,
                wr.first_weighed_at,
                dn.entry_type,
                dn.sender_type,
                dn.dispatch_number,
                dn.identity_number,
                dn.sender_name,
                dn.sender_tax_number,
                dn.status AS delivery_status,
                c.name AS company_name,
                c.tax_number AS company_tax_number,
                p.name AS product_name,
                v.plate_number,
                v.driver_name,
                v.driver_phone,
                s.code AS silo_code,
                s.name AS silo_name,
                s.code AS final_silo_code,
                s.name AS final_silo_name,
                sa.moisture,
                sa.protein,
                sa.hectoliter,
                sa.gluten,
                sa.sunn_pest_rate,
                sa.foreign_material,
                sa.broken_grain,
                sa.notes,
                sa.analyzed_at,
                bt.barcode,
                bt.issued_at
             FROM barcode_tickets bt
             INNER JOIN weighbridge_records wr ON wr.id = bt.weighbridge_record_id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             INNER JOIN silos s ON s.id = bt.silo_id
             LEFT JOIN sample_analysis sa ON sa.id = bt.sample_analysis_id
             WHERE wr.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $recordId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findBarcodeCandidate(int $recordId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT wr.id AS weighbridge_record_id, wr.delivery_notification_id, wr.product_id, dn.status AS delivery_status
             FROM weighbridge_records wr
             INNER JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             WHERE wr.id = :id
                AND sa.status = "completed"
                AND sa.result <> "rejected"
                AND COALESCE(sa.result_status, "") <> "ret"
                AND dn.status NOT IN ("ret", "alıma_girmedi")
                AND dn.status IN ("analiz_yapıldı", "silo_belirlendi", "barkod_bekliyor")
             LIMIT 1'
        );
        $statement->execute(['id' => $recordId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function qrPayload(array $record): string
    {
        $fields = json_decode((string) (SettingsService::system()['qr_content_fields'] ?? '[]'), true);
        $fields = is_array($fields) && $fields !== [] ? $fields : SettingsService::defaultQrFields();
        $values = [
            'company_name' => $record['company_name'] ?? null,
            'company_tax_number' => $record['company_tax_number'] ?? null,
            'plate_number' => $record['plate_number'] ?? null,
            'product_name' => $record['product_name'] ?? null,
            'first_weight' => $record['first_weight_kg'] ?? null,
            'analysis_values' => [
                'moisture' => $record['moisture'] ?? null,
                'protein' => $record['protein'] ?? null,
                'hectoliter' => $record['hectoliter'] ?? null,
                'gluten' => $record['gluten'] ?? null,
            ],
            'silo_code' => $record['silo_code'] ?? null,
            'silo_name' => $record['silo_name'] ?? null,
            'ticket_code' => $record['barcode'] ?? null,
            'issued_at' => $record['issued_at'] ?? null,
            'driver_name' => $record['driver_name'] ?? null,
            'dispatch_number' => $record['dispatch_number'] ?? null,
            'identity_number' => $record['identity_number'] ?? null,
        ];
        $payload = [];
        foreach ($fields as $field) {
            $payload[$field] = $values[$field] ?? null;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function findTicketByRecord(int $recordId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM barcode_tickets WHERE weighbridge_record_id = :weighbridge_record_id LIMIT 1'
        );
        $statement->execute(['weighbridge_record_id' => $recordId]);
        $ticket = $statement->fetch(PDO::FETCH_ASSOC);

        return $ticket === false ? null : $ticket;
    }

    private function silos(): array
    {
        return Database::connection()
            ->query(
                'SELECT s.id, s.code, s.name, s.product_id, p.name AS product_name
                 FROM silos s
                 LEFT JOIN products p ON p.id = s.product_id
                 WHERE s.is_active = 1
                 ORDER BY p.name ASC, s.code ASC'
            )
            ->fetchAll();
    }

    private function siloMatchesProduct(int $siloId, int $productId): bool
    {
        if ($siloId <= 0 || $productId <= 0) {
            return false;
        }

        $statement = Database::connection()->prepare('SELECT product_id FROM silos WHERE id = :id AND is_active = 1 LIMIT 1');
        $statement->execute(['id' => $siloId]);
        $siloProductId = $statement->fetchColumn();

        return $siloProductId !== false && (int) $siloProductId === $productId;
    }

    private function uniqueTicketCode(array $record): string
    {
        $sequence = 1;
        do {
            $code = $this->barcodeService->generateTicketCode($record, $sequence);
            $statement = Database::connection()->prepare('SELECT COUNT(*) FROM barcode_tickets WHERE barcode = :barcode');
            $statement->execute(['barcode' => $code]);
            $sequence++;
        } while ((int) $statement->fetchColumn() > 0);

        return $code;
    }

    private function repairBarcodeSecondWeighingQueue(): void
    {
        $records = Database::connection()->query(
            'SELECT dn.id
             FROM delivery_notifications dn
             INNER JOIN barcode_tickets bt ON bt.entry_id = dn.id
             INNER JOIN weighbridge_records wr ON wr.id = bt.weighbridge_record_id
             WHERE dn.status IN ("siloya_yönlendirildi", "directed_to_silo")
                AND wr.second_weight_kg IS NULL
             LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($records as $record) {
            VehicleProcessHistory::changeStatus((int) $record['id'], 'ikinci_tartım_bekliyor', 'İkinci tartım bekliyor', 'Barkod basılmış kayıt 2. tartım kuyruğuna alındı.');
        }
    }
}
