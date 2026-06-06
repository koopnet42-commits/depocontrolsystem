<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\DriverVehicleRegistry;
use App\Services\PlateReaderService;
use App\Services\OutboundProcessHistory;
use App\Services\OutboundProcessService;
use App\Services\VehicleProcessHistory;
use PDO;

final class IncomingProductController extends Controller
{
    public function __construct(private readonly PlateReaderService $plateReader = new PlateReaderService())
    {
    }

    public function index(): void
    {
        $query = trim((string) $this->input('q', ''));
        $selectedId = (int) $this->input('notification_id');

        $this->view('incoming_products/index', [
            'title' => 'Gelen Ürün Girişi',
            'message' => (string) $this->input('message', ''),
            'query' => $query,
            'matches' => $query === '' ? [] : $this->searchPendingNotifications($query),
            'selectedNotification' => $selectedId > 0 ? $this->findPendingNotification($selectedId) : null,
            'companies' => $this->companies(),
            'personSenders' => $this->personSenders(),
            'products' => $this->products(),
            'canDirectEntry' => Auth::can('delivery.direct-entry'),
            'validation' => $this->consumeValidation(),
        ]);
    }

    public function productOperations(): void
    {
        $this->productPreNotifications();
    }

    public function productPreNotifications(): void
    {
        $this->renderProductOperations('pre_notifications');
    }

    public function productEntry(): void
    {
        $this->renderProductOperations('entry');
    }

    private function renderProductOperations(string $screen): void
    {
        $this->ensureNotificationMetaColumns();
        $query = trim((string) $this->input('q', ''));
        $selectedId = (int) $this->input('notification_id');
        [$preNotificationFilters, $preNotificationFilterError] = $this->preNotificationFilters();

        $this->view('product_operations/index', [
            'title' => 'Ürün İşlemleri',
            'screen' => $screen,
            'operationMode' => (string) $this->input('mode', $screen === 'entry' ? 'inbound' : 'inbound_pre'),
            'message' => (string) $this->input('message', ''),
            'query' => $query,
            'matches' => $query === '' ? [] : $this->searchPendingNotifications($query),
            'selectedNotification' => $selectedId > 0 ? $this->findPendingNotification($selectedId) : null,
            'selectedOutboundRecord' => ((int) $this->input('outbound_id')) > 0 ? $this->findOutboundRecord((int) $this->input('outbound_id')) : null,
            'waitingNotifications' => $this->waitingPreNotifications($preNotificationFilters),
            'preNotificationFilters' => $preNotificationFilters,
            'preNotificationFilterError' => $preNotificationFilterError,
            'notifications' => $this->preNotifications(),
            'incomingEntries' => $this->incomingEntries(),
            'outboundRecords' => $this->outboundRecords(),
            'outboundProcessCounts' => (new OutboundProcessService())->counts(),
            'outboundHistories' => $this->outboundHistories(),
            'secondWeighingWaiting' => $this->secondWeighingWaitingOperations(),
            'histories' => $this->histories(),
            'companies' => $this->companies(),
            'personSenders' => $this->personSenders(),
            'products' => $this->products(),
            'silos' => $this->silos(),
            'canDirectEntry' => Auth::can('delivery.direct-entry'),
            'validation' => $this->consumeValidation(),
        ]);
    }

    public function startPreNotified(): void
    {
        $notification = $this->findPendingNotification((int) $this->input('notification_id'));

        if ($notification === null) {
            $this->redirect($this->incomingReturnTo('/incoming-products?message=not_found', 'not_found'));
        }

        if (! in_array($notification['status'], ['pending', 'ürün_bildirimi'], true)) {
            $this->redirect($this->incomingReturnTo('/incoming-products?message=not_found', 'already_transferred'));
        }

        Database::connection()
            ->prepare('UPDATE delivery_notifications SET expected_arrival_date = COALESCE(expected_arrival_date, :today) WHERE id = :id')
            ->execute(['id' => (int) $notification['id'], 'today' => $this->nullableInput('arrival_date') ?? date('Y-m-d')]);
        VehicleProcessHistory::changeStatus((int) $notification['id'], 'kantara_geldi', 'Gelen ürün girişine aktarıldı', $this->nullableInput('entry_notes'));

        AuditLogger::log('incoming.pre_notified_started', 'delivery_notifications', (int) $notification['id'], [
            'plate' => $notification['plate_number'],
            'notification_number' => $notification['notification_number'],
            'arrival_date' => $this->nullableInput('arrival_date') ?? date('Y-m-d'),
            'entry_notes' => $this->nullableInput('entry_notes'),
        ]);

        $this->redirect('/weighbridge-entry?plate=' . urlencode((string) $notification['plate_number']));
    }

    public function storeDirect(): void
    {
        if (! Auth::can('delivery.direct-entry')) {
            http_response_code(403);
            echo 'Bu işlem için yetkiniz yok.';
            return;
        }

        $errors = $this->directEntryValidationErrors();

        if ($errors !== []) {
            $this->redirectWithValidation($this->incomingReturnTo('/incoming-products?message=invalid', 'invalid'), $errors);
        }

        $senderType = $this->senderType();
        $quantityKg = ((float) $this->decimalInputOrZero('quantity_ton')) * 1000;
        $companyId = $this->senderCompanyId($senderType);
        $vehicleId = $this->upsertVehicle($companyId);

        $payload = [
            'notification_number' => 'DGR-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2))),
            'company_id' => $companyId,
            'product_id' => (int) $this->input('product_id'),
            'vehicle_id' => $vehicleId,
            'expected_quantity_kg' => number_format($quantityKg, 3, '.', ''),
            'loading_date' => $this->nullableInput('entry_date') ?? date('Y-m-d'),
            'expected_arrival_date' => $this->nullableInput('entry_date') ?? date('Y-m-d'),
            'entry_type' => 'direct_entry',
            'sender_type' => $senderType,
            'dispatch_number' => $this->nullableInput('dispatch_number'),
            'identity_number' => $senderType === 'person' ? preg_replace('/\D+/', '', (string) $this->input('identity_number', '')) : null,
            'sender_name' => $senderType === 'person' ? $this->nullableInput('sender_name') : null,
            'sender_tax_number' => $senderType === 'company'
                ? $this->nullableInput('sender_tax_number')
                : preg_replace('/\D+/', '', (string) $this->input('identity_number', '')),
            'sender_phone' => $this->nullableInput('sender_phone'),
            'sender_address' => $this->nullableInput('sender_address'),
            'status' => 'kantara_geldi',
            'notes' => $this->nullableInput('notes'),
            'operation_type' => 'PRODUCT_IN',
        ];

        Database::connection()->prepare(
            'INSERT INTO delivery_notifications
                (notification_number, company_id, product_id, vehicle_id, expected_quantity_kg,
                 loading_date, expected_arrival_date, entry_type, sender_type, dispatch_number,
                 identity_number, sender_name, sender_tax_number, sender_phone, sender_address, status, notes, operation_type)
             VALUES
                (:notification_number, :company_id, :product_id, :vehicle_id, :expected_quantity_kg,
                 :loading_date, :expected_arrival_date, :entry_type, :sender_type, :dispatch_number,
                 :identity_number, :sender_name, :sender_tax_number, :sender_phone, :sender_address, :status, :notes, :operation_type)'
        )->execute($payload);

        $notificationId = (int) Database::connection()->lastInsertId();
        AuditLogger::log('incoming.direct_entry_created', 'delivery_notifications', $notificationId, $payload);
        VehicleProcessHistory::record($notificationId, null, 'kantara_geldi', 'Ön bildirimsiz gelen ürün girişi oluşturuldu', $payload['notes']);
        DriverVehicleRegistry::recordUsageForEntry($notificationId);

        $plate = $this->plateReader->normalize((string) $this->input('plate_number'));
        if ((string) $this->input('return_to', '') === 'product_operations') {
            $this->redirect('/weighbridge-entry?plate=' . urlencode($plate));
        }

        $this->redirect($this->incomingReturnTo('/incoming-products?message=saved&notification_id=' . $notificationId . '&q=' . urlencode($plate), 'saved'));
    }

    private function preNotifications(): array
    {
        return $this->fetchNotificationList(
            'WHERE dn.entry_type = "pre_notified"
             ORDER BY dn.created_at DESC, dn.id DESC
             LIMIT 120'
        );
    }

    private function waitingPreNotifications(array $filters): array
    {
        $where = [
            'dn.entry_type = "pre_notified"',
            'dn.status IN ("pending", "ürün_bildirimi")',
        ];
        $params = [];

        if ($filters['status'] !== 'delayed' && $filters['date_from'] !== '') {
            $where[] = 'DATE(dn.expected_arrival_date) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if ($filters['status'] !== 'delayed' && $filters['date_to'] !== '') {
            $where[] = 'DATE(dn.expected_arrival_date) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if ($filters['plate'] !== '') {
            $where[] = 'REPLACE(v.plate_number, " ", "") LIKE :plate';
            $params['plate'] = '%' . $this->plateReader->normalize($filters['plate']) . '%';
        }

        if ($filters['sender'] !== '') {
            $where[] = '(c.name LIKE :sender OR dn.sender_name LIKE :sender)';
            $params['sender'] = '%' . $filters['sender'] . '%';
        }

        if ($filters['product_id'] !== '') {
            $where[] = 'dn.product_id = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }

        if ($filters['status'] === 'delayed') {
            $where[] = 'dn.expected_arrival_date IS NOT NULL AND DATE(dn.expected_arrival_date) < DATE("now")';
        } elseif ($filters['status'] !== '') {
            $where[] = 'dn.status = :status';
            $params['status'] = $filters['status'];
        }

        return $this->fetchNotificationList(
            'WHERE ' . implode(' AND ', $where) . '
             ORDER BY dn.expected_arrival_date IS NULL, dn.expected_arrival_date ASC, dn.id DESC
             LIMIT 120',
            $params
        );
    }

    private function incomingEntries(): array
    {
        return $this->fetchNotificationList(
            'WHERE dn.entry_type = "direct_entry"
                OR dn.status IN (
                    "kantara_geldi", "kantara_yonlendirildi", "giriş_bariyeri_bekliyor",
                    "giriş_bariyeri_açıldı", "kantarda", "analiz_bekliyor", "analizde",
                    "analiz_tamamlandı", "siloya_yönlendirildi", "boşaltımda", "boşaltıldı",
                    "ikinci_tartım_bekliyor", "tamamlandı", "at_weighbridge", "in_analysis",
                    "directed_to_silo", "unloaded", "completed"
                )
             ORDER BY dn.updated_at DESC, dn.id DESC
             LIMIT 120'
        );
    }

    private function outboundHistories(): array
    {
        $this->ensureOutboundSchema();
        OutboundProcessHistory::ensureTable();

        $rows = Database::connection()->query(
            'SELECT oph.*, u.name AS user_name
             FROM outbound_process_history oph
             LEFT JOIN users u ON u.id = oph.user_id
             ORDER BY oph.created_at ASC, oph.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['outbound_id']][] = $row;
        }

        return $grouped;
    }

    private function outboundRecords(): array
    {
        $this->ensureOutboundSchema();

        return Database::connection()->query(
            'SELECT ol.*, p.name AS product_name, s.code AS silo_code, s.name AS silo_name, s.current_stock_kg,
                    CASE WHEN ol.sender_type = "person" THEN COALESCE(ol.sender_name, "-") ELSE COALESCE(c.name, "-") END AS sender_display
             FROM outbound_loadings ol
             LEFT JOIN companies c ON c.id = ol.company_id
             INNER JOIN products p ON p.id = ol.product_id
             INNER JOIN silos s ON s.id = ol.source_silo_id
             ORDER BY ol.updated_at DESC, ol.id DESC
             LIMIT 120'
        )->fetchAll();
    }

    private function secondWeighingWaitingOperations(): array
    {
        $this->ensureOutboundSchema();
        $database = Database::connection();
        $inbound = $database->query(
            'SELECT "PRODUCT_IN" AS operation_type, wr.id AS record_id, NULL AS outbound_id,
                    dn.id AS entry_id, dn.notification_number AS operation_number,
                    COALESCE(v.plate_number, "-") AS plate_number,
                    c.name AS sender_display, p.name AS product_name,
                    s.code AS silo_code, s.name AS silo_name,
                    wr.first_weight_kg, wr.second_weight_kg, wr.net_weight_kg,
                    dn.status, COALESCE(bt.issued_at, wr.updated_at) AS queue_time
             FROM weighbridge_records wr
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             INNER JOIN silos s ON s.id = wr.assigned_silo_id
             LEFT JOIN barcode_tickets bt ON bt.weighbridge_record_id = wr.id
             WHERE dn.status = "ikinci_tartım_bekliyor"
                AND wr.assigned_silo_id IS NOT NULL
                AND wr.first_weight_kg IS NOT NULL
                AND wr.second_weight_kg IS NULL
             ORDER BY queue_time ASC
             LIMIT 80'
        )->fetchAll(PDO::FETCH_ASSOC);

        $outbound = $database->query(
            'SELECT "PRODUCT_OUT" AS operation_type, NULL AS record_id, ol.id AS outbound_id,
                    NULL AS entry_id, ol.operation_number,
                    ol.plate_number,
                    CASE WHEN ol.sender_type = "person" THEN COALESCE(ol.sender_name, "-") ELSE COALESCE(c.name, "-") END AS sender_display,
                    p.name AS product_name,
                    s.code AS silo_code, s.name AS silo_name,
                    ol.first_weight_kg, ol.second_weight_kg, ol.net_quantity_kg AS net_weight_kg,
                    ol.status, COALESCE(ol.assigned_at, ol.updated_at) AS queue_time
             FROM outbound_loadings ol
             LEFT JOIN companies c ON c.id = ol.company_id
             INNER JOIN products p ON p.id = ol.product_id
             INNER JOIN silos s ON s.id = ol.source_silo_id
             WHERE ol.status = "OUTBOUND_SECOND_WEIGHING_WAITING"
                AND ol.first_weight_kg IS NOT NULL
                AND ol.second_weight_kg IS NULL
             ORDER BY queue_time ASC
             LIMIT 80'
        )->fetchAll(PDO::FETCH_ASSOC);

        $records = array_merge($inbound, $outbound);
        usort($records, static fn (array $a, array $b): int => strcmp((string) ($a['queue_time'] ?? ''), (string) ($b['queue_time'] ?? '')));

        return $records;
    }

    private function findOutboundRecord(int $id): ?array
    {
        $this->ensureOutboundSchema();
        $statement = Database::connection()->prepare(
            'SELECT ol.*, p.name AS product_name, s.code AS silo_code, s.name AS silo_name, s.current_stock_kg,
                    CASE WHEN ol.sender_type = "person" THEN COALESCE(ol.sender_name, "-") ELSE COALESCE(c.name, "-") END AS sender_display
             FROM outbound_loadings ol
             LEFT JOIN companies c ON c.id = ol.company_id
             INNER JOIN products p ON p.id = ol.product_id
             INNER JOIN silos s ON s.id = ol.source_silo_id
             WHERE ol.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function silos(): array
    {
        return Database::connection()->query(
            'SELECT s.id, s.code, s.name, s.product_id, s.current_stock_kg, p.name AS product_name
             FROM silos s
             LEFT JOIN products p ON p.id = s.product_id
             WHERE s.is_active = 1
             ORDER BY s.code ASC'
        )->fetchAll();
    }

    private function fetchNotificationList(string $whereAndOrder, array $params = []): array
    {
        $statement = Database::connection()
            ->prepare(
                'SELECT dn.*, c.name AS company_name, p.name AS product_name, v.plate_number, v.brand AS vehicle_brand, v.model AS vehicle_model,
                        v.driver_name, v.driver_phone,
                        cancelled_user.name AS cancelled_by_name,
                        notified_user.name AS company_notified_by_name
                 FROM delivery_notifications dn
                 INNER JOIN companies c ON c.id = dn.company_id
                 INNER JOIN products p ON p.id = dn.product_id
                 LEFT JOIN vehicles v ON v.id = dn.vehicle_id
                 LEFT JOIN users cancelled_user ON cancelled_user.id = dn.cancelled_by
                 LEFT JOIN users notified_user ON notified_user.id = dn.company_notified_by ' . $whereAndOrder
            );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function preNotificationFilters(): array
    {
        $hasRequestFilters = array_intersect(array_keys($_GET), ['date_from', 'date_to', 'plate', 'sender', 'product_id', 'status']) !== [];
        $filters = [
            'date_from' => trim((string) $this->input('date_from', $hasRequestFilters ? '' : date('Y-m-d'))),
            'date_to' => trim((string) $this->input('date_to', $hasRequestFilters ? '' : date('Y-m-d', strtotime('+2 days')))),
            'plate' => trim((string) $this->input('plate', '')),
            'sender' => trim((string) $this->input('sender', '')),
            'product_id' => trim((string) $this->input('product_id', '')),
            'status' => trim((string) $this->input('status', '')),
        ];

        $error = null;
        if ($filters['date_from'] !== '' && $filters['date_to'] !== '') {
            $from = strtotime($filters['date_from']);
            $to = strtotime($filters['date_to']);
            if ($from !== false && $to !== false && $to >= $from && (($to - $from) / 86400) > 2) {
                $filters['date_to'] = date('Y-m-d', strtotime('+2 days', $from));
                $error = 'En fazla 3 günlük tarih aralığı seçebilirsiniz.';
            }
        }

        return [$filters, $error];
    }

    private function histories(): array
    {
        VehicleProcessHistory::forEntry(-1);
        $rows = Database::connection()
            ->query('SELECT * FROM vehicle_process_history ORDER BY created_at ASC, id ASC')
            ->fetchAll();
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['entry_id']][] = $row;
        }

        return $grouped;
    }

    private function searchPendingNotifications(string $query): array
    {
        $normalized = $this->plateReader->normalize($query);
        $statement = Database::connection()->prepare(
            'SELECT dn.*, c.name AS company_name, p.name AS product_name, v.plate_number, v.brand AS vehicle_brand, v.model AS vehicle_model,
                    v.driver_name, v.driver_phone
             FROM delivery_notifications dn
             INNER JOIN companies c ON c.id = dn.company_id
             INNER JOIN products p ON p.id = dn.product_id
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             WHERE dn.status IN ("pending", "ürün_bildirimi")
                AND (
                    dn.notification_number LIKE :query
                    OR REPLACE(v.plate_number, " ", "") LIKE :plate
                    OR c.name LIKE :query
                    OR p.name LIKE :query
                    OR dn.sender_name LIKE :query
                    OR dn.dispatch_number LIKE :query
                )
             ORDER BY dn.expected_arrival_date IS NULL, dn.expected_arrival_date ASC, dn.id DESC
             LIMIT 20'
        );
        $statement->execute([
            'query' => '%' . $query . '%',
            'plate' => '%' . $normalized . '%',
        ]);

        return $statement->fetchAll();
    }

    private function findPendingNotification(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT dn.*, c.name AS company_name, p.name AS product_name, v.plate_number, v.brand AS vehicle_brand, v.model AS vehicle_model,
                    v.driver_name, v.driver_phone
             FROM delivery_notifications dn
             INNER JOIN companies c ON c.id = dn.company_id
             INNER JOIN products p ON p.id = dn.product_id
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             WHERE dn.id = :id AND dn.status IN ("pending", "ürün_bildirimi")
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $notification = $statement->fetch(PDO::FETCH_ASSOC);

        return $notification === false ? null : $notification;
    }

    private function directEntryValidationErrors(): array
    {
        $senderType = $this->senderType();
        $identityNumber = preg_replace('/\D+/', '', (string) $this->input('identity_number', ''));
        $quantityTon = $this->decimalInputOrNull('quantity_ton');
        $plate = $this->plateReader->normalize((string) $this->input('plate_number'));
        $operationType = (string) $this->input('operation_type', '');
        $errors = [];

        if ($operationType !== 'PRODUCT_IN') {
            $errors['operation_type'] = 'Giriş işlem tipi zorunludur.';
        }

        if ((int) $this->input('product_id') <= 0) {
            $errors['product_id'] = 'Bu alan zorunludur.';
        }

        if ($this->nullableInput('notes') === null) {
            $errors['notes'] = 'Ön bildirimsiz girişlerde açıklama zorunludur.';
        }

        if ($quantityTon === null || (float) $quantityTon <= 0) {
            $errors['quantity_ton'] = 'Miktar sayısal ve sıfırdan büyük olmalıdır.';
        }

        if ($plate === '') {
            $errors['plate_number'] = 'Plaka alanı zorunludur.';
        } elseif (! $this->plateIsValid($plate)) {
            $errors['plate_number'] = 'Plaka formatı geçersiz. Örnek: 34 ABC 123.';
        }

        if ($senderType === 'company') {
            if (! $this->companySenderIsValid()) {
                $errors['company_name'] = 'Bu alan zorunludur.';
            }

            if ($this->nullableInput('dispatch_number') === null) {
                $errors['dispatch_number'] = 'Firma ürünü için irsaliye numarası zorunludur.';
            }

            return $errors;
        }

        if ($this->nullableInput('sender_name') === null) {
            $errors['sender_name'] = 'Bu alan zorunludur.';
        }

        if ($identityNumber === '') {
            $errors['identity_number'] = 'Şahıs ürünü için TC kimlik no zorunludur.';
        } elseif (strlen($identityNumber) !== 11) {
            $errors['identity_number'] = 'TC kimlik no 11 haneli olmalıdır.';
        }

        return $errors;
    }

    private function senderType(): string
    {
        return (string) $this->input('sender_type', 'company') === 'person' ? 'person' : 'company';
    }

    private function companySenderIsValid(): bool
    {
        if ((int) $this->input('company_id') > 0) {
            return true;
        }

        return $this->nullableInput('company_name') !== null;
    }

    private function senderCompanyId(string $senderType): int
    {
        if ($senderType === 'person') {
            return $this->ensurePersonCompany();
        }

        $companyId = (int) $this->input('company_id');

        if ($companyId > 0) {
            return $companyId;
        }

        return $this->createOrFindCompanyByName((string) $this->input('company_name', ''));
    }

    private function upsertVehicle(int $companyId): int
    {
        return DriverVehicleRegistry::upsertFromRequest($companyId, $_POST);
    }

    private function ensurePersonCompany(): int
    {
        $name = 'Şahıs Ürünleri';
        $statement = Database::connection()->prepare('SELECT id FROM companies WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $name]);
        $id = $statement->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        Database::connection()->prepare('INSERT INTO companies (name, is_active) VALUES (:name, 1)')->execute(['name' => $name]);

        return (int) Database::connection()->lastInsertId();
    }

    private function companies(): array
    {
        return Database::connection()
            ->query('SELECT id, name, tax_number, address, phone FROM companies WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
    }

    private function personSenders(): array
    {
        return Database::connection()
            ->query(
                'SELECT sender_name, identity_number, sender_phone, sender_address
                 FROM delivery_notifications
                 WHERE sender_type = "person" AND sender_name IS NOT NULL AND sender_name <> ""
                 GROUP BY sender_name, identity_number, sender_phone, sender_address
                 ORDER BY sender_name ASC
                 LIMIT 200'
            )
            ->fetchAll();
    }

    private function createOrFindCompanyByName(string $name): int
    {
        $name = trim($name);

        if ($name === '') {
            return 0;
        }

        $taxNumber = $this->nullableInput('sender_tax_number');
        if ($taxNumber !== null) {
            $statement = Database::connection()->prepare('SELECT id FROM companies WHERE tax_number = :tax_number LIMIT 1');
            $statement->execute(['tax_number' => $taxNumber]);
            $id = $statement->fetchColumn();

            if ($id !== false) {
                return (int) $id;
            }
        }

        $statement = Database::connection()->prepare('SELECT id FROM companies WHERE LOWER(name) = LOWER(:name) LIMIT 1');
        $statement->execute(['name' => $name]);
        $id = $statement->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO companies (name, tax_number, phone, address, is_active)
             VALUES (:name, :tax_number, :phone, :address, 1)'
        );
        $statement->execute([
            'name' => $name,
            'tax_number' => $taxNumber,
            'phone' => $this->nullableInput('sender_phone'),
            'address' => $this->nullableInput('sender_address'),
        ]);

        $companyId = (int) Database::connection()->lastInsertId();
        AuditLogger::log('company.quick_created_from_incoming_product', 'companies', $companyId, [
            'name' => $name,
        ]);

        return $companyId;
    }

    private function products(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
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
                dispatch_number TEXT NULL,
                sender_tax_number TEXT NULL,
                sender_phone TEXT NULL,
                sender_address TEXT NULL,
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
        foreach ([
            'dispatch_number' => 'TEXT NULL',
            'sender_tax_number' => 'TEXT NULL',
            'sender_phone' => 'TEXT NULL',
            'sender_address' => 'TEXT NULL',
        ] as $column => $definition) {
            if (! in_array($column, $columns, true)) {
                $database->exec('ALTER TABLE outbound_loadings ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }

    private function incomingReturnTo(string $default, string $message): string
    {
        if ((string) $this->input('return_to', '') === 'product_operations_entry') {
            return '/product-operations/entry?message=' . urlencode($message);
        }

        if ((string) $this->input('return_to', '') === 'product_operations_pre_notifications') {
            return '/product-operations/pre-notifications?message=' . urlencode($message);
        }

        if ((string) $this->input('return_to', '') === 'product_operations') {
            return '/product-operations?message=' . urlencode($message);
        }

        return $default;
    }

    private function plateIsValid(string $plate): bool
    {
        return (bool) preg_match('/^[0-9]{2}\s?[A-Z]{1,3}\s?[0-9]{2,5}$/', $plate);
    }

    private function ensureNotificationMetaColumns(): void
    {
        $database = Database::connection();
        $driver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = $driver === 'sqlite'
            ? array_column($database->query('PRAGMA table_info(delivery_notifications)')->fetchAll(PDO::FETCH_ASSOC), 'name')
            : array_column($database->query('SHOW COLUMNS FROM delivery_notifications')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $definitions = $driver === 'sqlite'
            ? [
                'company_notified_at' => 'TEXT NULL',
                'company_notified_by' => 'INTEGER NULL',
                'company_notified_note' => 'TEXT NULL',
                'notification_note_at' => 'TEXT NULL',
                'notification_note_by' => 'INTEGER NULL',
                'notification_note' => 'TEXT NULL',
                'cancel_reason' => 'TEXT NULL',
                'cancel_note' => 'TEXT NULL',
                'cancelled_at' => 'TEXT NULL',
                'cancelled_by' => 'INTEGER NULL',
                'operation_type' => 'TEXT NOT NULL DEFAULT "PRODUCT_IN"',
            ]
            : [
                'company_notified_at' => 'TIMESTAMP NULL',
                'company_notified_by' => 'BIGINT UNSIGNED NULL',
                'company_notified_note' => 'TEXT NULL',
                'notification_note_at' => 'TIMESTAMP NULL',
                'notification_note_by' => 'BIGINT UNSIGNED NULL',
                'notification_note' => 'TEXT NULL',
                'cancel_reason' => 'VARCHAR(160) NULL',
                'cancel_note' => 'TEXT NULL',
                'cancelled_at' => 'TIMESTAMP NULL',
                'cancelled_by' => 'BIGINT UNSIGNED NULL',
                'operation_type' => 'VARCHAR(20) NOT NULL DEFAULT "PRODUCT_IN"',
            ];

        foreach ($definitions as $column => $definition) {
            if (! in_array($column, $columns, true)) {
                $database->exec('ALTER TABLE delivery_notifications ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }
}
