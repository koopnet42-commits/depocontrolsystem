<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class UnloadingOperationController extends Controller
{
    public function index(): void
    {
        $code = strtoupper(trim((string) $this->input('code', '')));
        $ticket = $code === '' ? null : $this->findTicket($code);

        $this->view('unloading_operations/index', [
            'title' => 'Silo Boşaltım',
            'code' => $code,
            'ticket' => $ticket,
            'operation' => $ticket === null ? null : $this->findOperation((int) $ticket['barcode_ticket_id']),
            'routedTickets' => $this->routedTickets(),
            'message' => (string) $this->input('message', ''),
        ]);
    }

    public function start(): void
    {
        $code = strtoupper(trim((string) $this->input('code')));
        $this->redirect('/unloading-operations?code=' . urlencode($code) . '&message=disabled');
    }

    public function complete(): void
    {
        $code = strtoupper(trim((string) $this->input('code')));
        $this->redirect('/unloading-operations?code=' . urlencode($code) . '&message=disabled');
    }

    private function findTicket(string $code): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                bt.id AS barcode_ticket_id,
                bt.barcode,
                bt.entry_id,
                bt.status AS barcode_status,
                bt.silo_id,
                wr.id AS weighbridge_record_id,
                wr.status AS weighbridge_status,
                wr.ticket_number,
                wr.delivery_notification_id,
                wr.first_weight_kg,
                wr.first_weighed_at,
                dn.status AS delivery_status,
                c.name AS company_name,
                p.name AS product_name,
                v.plate_number,
                v.driver_name,
                v.driver_phone,
                s.code AS silo_code,
                s.name AS silo_name,
                sa.moisture,
                sa.protein,
                sa.hectoliter,
                sa.gluten,
                sa.sunn_pest_rate,
                sa.foreign_material,
                sa.broken_grain,
                sa.notes AS analysis_notes
             FROM barcode_tickets bt
             INNER JOIN weighbridge_records wr ON wr.id = bt.weighbridge_record_id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             INNER JOIN silos s ON s.id = bt.silo_id
             LEFT JOIN sample_analysis sa ON sa.id = bt.sample_analysis_id
             WHERE bt.barcode = :barcode
             LIMIT 1'
        );
        $statement->execute(['barcode' => $code]);
        $ticket = $statement->fetch(PDO::FETCH_ASSOC);

        return $ticket === false ? null : $ticket;
    }

    private function findOperation(int $ticketId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM unloading_operations WHERE barcode_ticket_id = :barcode_ticket_id LIMIT 1'
        );
        $statement->execute(['barcode_ticket_id' => $ticketId]);
        $operation = $statement->fetch(PDO::FETCH_ASSOC);

        return $operation === false ? null : $operation;
    }

    private function routedTickets(): array
    {
        $statement = Database::connection()->query(
            'SELECT
                bt.barcode,
                bt.status AS barcode_status,
                dn.id AS entry_id,
                dn.status AS delivery_status,
                CASE
                    WHEN dn.status = "ikinci_tartım_bekliyor" THEN "2. tartım bekliyor"
                    WHEN dn.status = "siloya_yönlendirildi" THEN "Siloya yönlendirildi"
                    ELSE dn.status
                END AS display_status,
                p.name AS product_name,
                CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE c.name END AS sender_name,
                v.plate_number,
                s.code AS silo_code,
                s.name AS silo_name,
                wr.first_weight_kg
             FROM barcode_tickets bt
             INNER JOIN weighbridge_records wr ON wr.id = bt.weighbridge_record_id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             INNER JOIN silos s ON s.id = bt.silo_id
             WHERE bt.status IN ("active", "in_progress", "completed")
                AND dn.status IN ("siloya_yönlendirildi", "ikinci_tartım_bekliyor")
             ORDER BY dn.updated_at ASC, dn.id ASC
             LIMIT 80'
        );

        return $statement->fetchAll();
    }

}
