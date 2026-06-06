<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\PlateReaderService;
use App\Services\VehicleProcessHistory;
use PDO;

final class ProcessRepairController extends Controller
{
    public function __construct(private readonly PlateReaderService $plateReader = new PlateReaderService())
    {
    }

    public function index(): void
    {
        $this->requireAdmin();
        $plate = $this->plateReader->normalize((string) $this->input('plate', ''));

        $this->view('process_repair/index', [
            'title' => 'Araç Süreç Onarım',
            'plate' => $plate,
            'records' => $plate === '' ? [] : $this->recordsByPlate($plate),
            'silos' => $this->silos(),
            'message' => (string) $this->input('message', ''),
        ]);
    }

    public function repair(): void
    {
        $this->requireAdmin();
        $entryId = (int) $this->input('entry_id');
        $action = (string) $this->input('repair_action');
        $plate = $this->plateReader->normalize((string) $this->input('plate', ''));
        $record = $this->recordByEntry($entryId);

        if ($record === null) {
            $this->redirect('/process-repair?plate=' . urlencode($plate) . '&message=not_found');
        }

        $message = 'repaired';

        if ($action === 'analysis_waiting') {
            VehicleProcessHistory::changeStatus($entryId, 'analiz_bekliyor', 'Admin süreç onarım: analiz bekliyor');
        } elseif ($action === 'assign_silo') {
            $siloId = (int) $this->input('silo_id');
            if ($siloId <= 0 || (int) ($record['weighbridge_record_id'] ?? 0) <= 0) {
                $message = 'invalid';
            } else {
                Database::connection()
                    ->prepare('UPDATE weighbridge_records SET assigned_silo_id = :silo_id, status = "silo_assigned" WHERE id = :id')
                    ->execute(['silo_id' => $siloId, 'id' => (int) $record['weighbridge_record_id']]);
                VehicleProcessHistory::changeStatus($entryId, 'silo_belirlendi', 'Admin süreç onarım: silo seçildi');
            }
        } elseif ($action === 'send_barcode') {
            VehicleProcessHistory::changeStatus($entryId, 'barkod_bekliyor', 'Admin süreç onarım: barkod ekranına gönderildi');
        } elseif ($action === 'direct_to_silo') {
            if ((int) ($record['barcode_ticket_id'] ?? 0) <= 0 || (int) ($record['assigned_silo_id'] ?? 0) <= 0) {
                $message = 'invalid';
            } else {
                Database::connection()
                    ->prepare('UPDATE weighbridge_records SET status = "directed" WHERE id = :id')
                    ->execute(['id' => (int) $record['weighbridge_record_id']]);
                VehicleProcessHistory::changeStatus($entryId, 'siloya_yönlendirildi', 'Admin süreç onarım: siloya yönlendirildi');
            }
        } elseif ($action === 'prepare_unloading') {
            if ((int) ($record['barcode_ticket_id'] ?? 0) <= 0) {
                $message = 'invalid';
            } else {
                Database::connection()
                    ->prepare('UPDATE barcode_tickets SET status = "active" WHERE id = :id')
                    ->execute(['id' => (int) $record['barcode_ticket_id']]);
                Database::connection()
                    ->prepare('UPDATE weighbridge_records SET status = "directed" WHERE id = :id')
                    ->execute(['id' => (int) $record['weighbridge_record_id']]);
                VehicleProcessHistory::changeStatus($entryId, 'siloya_yönlendirildi', 'Admin süreç onarım: boşaltıma hazırlandı');
            }
        } else {
            $message = 'invalid';
        }

        AuditLogger::log('process_repair.' . $action, 'delivery_notifications', $entryId, [
            'old' => $record,
            'new' => ['action' => $action, 'silo_id' => $this->input('silo_id')],
            'service_note' => 'Admin araç süreç onarım işlemi',
        ]);

        $this->redirect('/process-repair?plate=' . urlencode($plate) . '&message=' . $message);
    }

    private function recordsByPlate(string $plate): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                dn.id AS entry_id,
                dn.notification_number,
                dn.status AS entry_status,
                dn.product_id,
                dn.sender_type,
                dn.sender_name,
                c.name AS company_name,
                p.name AS product_name,
                v.plate_number,
                wr.id AS weighbridge_record_id,
                wr.first_weight_kg,
                wr.assigned_silo_id,
                sa.id AS analysis_id,
                bt.id AS barcode_ticket_id,
                bt.barcode,
                bt.status AS barcode_status,
                s.code AS silo_code,
                s.name AS silo_name
             FROM delivery_notifications dn
             LEFT JOIN companies c ON c.id = dn.company_id
             LEFT JOIN products p ON p.id = dn.product_id
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             LEFT JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
             LEFT JOIN barcode_tickets bt ON bt.weighbridge_record_id = wr.id
             LEFT JOIN silos s ON s.id = wr.assigned_silo_id
             WHERE REPLACE(v.plate_number, " ", "") = :plate
             ORDER BY dn.updated_at DESC, dn.id DESC
             LIMIT 20'
        );
        $statement->execute(['plate' => $plate]);

        return $statement->fetchAll();
    }

    private function recordByEntry(int $entryId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                dn.*,
                wr.id AS weighbridge_record_id,
                wr.assigned_silo_id,
                bt.id AS barcode_ticket_id
             FROM delivery_notifications dn
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             LEFT JOIN barcode_tickets bt ON bt.weighbridge_record_id = wr.id
             WHERE dn.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $entryId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function silos(): array
    {
        return Database::connection()
            ->query('SELECT id, code, name FROM silos WHERE is_active = 1 ORDER BY code ASC')
            ->fetchAll();
    }

    private function requireAdmin(): void
    {
        if (! Auth::can('*')) {
            http_response_code(403);
            echo 'Bu ekran sadece admin içindir.';
            exit;
        }
    }
}

