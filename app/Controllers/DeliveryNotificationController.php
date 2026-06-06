<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\DriverVehicleRegistry;
use App\Services\PlateReaderService;
use App\Services\VehicleProcessHistory;
use PDO;

final class DeliveryNotificationController extends Controller
{
    public function __construct(private readonly PlateReaderService $plateReader = new PlateReaderService())
    {
    }

    private const STATUS_OPTIONS = [
        'pending' => 'Beklemede',
        'ürün_bildirimi' => 'Ürün bildirimi',
        'kantara_geldi' => 'Kantara Geldi',
        'kantara_yonlendirildi' => 'Kantara Yönlendirildi',
        'giriş_bariyeri_bekliyor' => 'Giriş Bariyeri Bekliyor',
        'giriş_bariyeri_açıldı' => 'Giriş Bariyeri Açıldı',
        'kantarda' => 'Kantarda',
        'analiz_bekliyor' => 'Analiz Bekliyor',
        'analizde' => 'Analizde',
        'analiz_tamamlandı' => 'Analiz Tamamlandı',
        'siloya_yönlendirildi' => 'Siloya Yönlendirildi',
        'boşaltımda' => 'Boşaltımda',
        'boşaltıldı' => 'Boşaltıldı',
        'ikinci_tartım_bekliyor' => 'İkinci Tartım Bekliyor',
        'tamamlandı' => 'Tamamlandı',
        'iptal' => 'İptal',
        'ret' => 'Ret',
        'alıma_girmedi' => 'Alıma Girmedi',
        'at_weighbridge' => 'Kantara Geldi',
        'in_analysis' => 'Analizde',
        'directed_to_silo' => 'Siloya Yönlendirildi',
        'unloaded' => 'Boşaltıldı',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal',
    ];

    private const ACTIVE_STATUSES = [
        'pending',
        'ürün_bildirimi',
        'kantara_geldi',
        'giriş_bariyeri_açıldı',
        'kantarda',
        'analiz_bekliyor',
        'analizde',
        'siloya_yönlendirildi',
        'boşaltıldı',
        'at_weighbridge',
        'in_analysis',
        'directed_to_silo',
        'unloaded',
    ];

    public function index(): void
    {
        $filters = [
            'plate' => trim((string) $this->input('plate', '')),
            'company_id' => trim((string) $this->input('company_id', '')),
            'product_id' => trim((string) $this->input('product_id', '')),
            'date' => trim((string) $this->input('date', '')),
            'status' => trim((string) $this->input('status', '')),
        ];

        $this->view('delivery_notifications/index', [
            'title' => 'Ürün Ön Bildirimleri',
            'notifications' => $this->filteredNotifications($filters),
            'companies' => $this->companies(),
            'products' => $this->products(),
            'filters' => $filters,
            'statusOptions' => self::STATUS_OPTIONS,
            'message' => (string) $this->input('message', ''),
        ]);
    }

    public function create(): void
    {
        $validation = $this->consumeValidation();
        $notification = $validation['old'] === [] ? $this->emptyNotification() : array_merge($this->emptyNotification(), $validation['old']);
        $vehicle = $validation['old'] === [] ? $this->emptyVehicle() : [
            'plate_number' => $validation['old']['plate_number'] ?? '',
            'brand' => $validation['old']['vehicle_brand'] ?? '',
            'driver_name' => $validation['old']['driver_name'] ?? '',
            'driver_phone' => $validation['old']['driver_phone'] ?? '',
        ];

        $this->view('delivery_notifications/form', [
            'title' => 'Ön Bildirim Ekle',
            'notification' => $notification,
            'vehicle' => $vehicle,
            'companies' => $this->companies(),
            'personSenders' => $this->personSenders(),
            'products' => $this->products(),
            'statusOptions' => self::STATUS_OPTIONS,
            'action' => '/delivery-notifications/store',
            'message' => (string) $this->input('message', ''),
            'validation' => $validation,
        ]);
    }

    public function store(): void
    {
        $errors = $this->validationErrors();

        if ($errors !== []) {
            $this->redirectWithValidation($this->returnTo('/delivery-notifications/create?message=invalid', 'invalid'), $errors);
        }

        $senderCompanyId = $this->senderCompanyId();
        $vehicleId = $this->upsertVehicle($senderCompanyId);
        $payload = $this->payload($vehicleId, $senderCompanyId);
        $payload['notification_number'] = $this->notificationNumber();

        if ($this->statusIsActive($payload['status']) && $this->activeNotificationExistsForVehicle($vehicleId)) {
            $this->redirectWithValidation($this->returnTo('/delivery-notifications/create?message=active_plate_exists', 'active_plate_exists'), [
                'plate_number' => 'Bu plaka için aktif bir süreç zaten var.',
            ]);
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO delivery_notifications
                (notification_number, company_id, product_id, vehicle_id, expected_quantity_kg,
                 loading_date, expected_arrival_date, entry_type, sender_type, dispatch_number,
                 identity_number, sender_name, sender_tax_number, sender_phone, sender_address, status, notes, operation_type)
             VALUES
                (:notification_number, :company_id, :product_id, :vehicle_id, :expected_quantity_kg,
                 :loading_date, :expected_arrival_date, :entry_type, :sender_type, :dispatch_number,
                 :identity_number, :sender_name, :sender_tax_number, :sender_phone, :sender_address, :status, :notes, :operation_type)'
        );

        $statement->execute($payload);
        $entryId = (int) Database::connection()->lastInsertId();
        AuditLogger::log('delivery_notification.created', 'delivery_notifications', $entryId, $payload);
        VehicleProcessHistory::record($entryId, null, 'pending', 'Ürün bildirimi oluşturuldu', $payload['notes']);
        DriverVehicleRegistry::recordUsageForEntry($entryId);

        $this->redirect($this->returnTo('/delivery-notifications?message=saved', 'saved'));
    }

    public function edit(): void
    {
        $notification = $this->findNotification((int) $this->input('id'));

        if ($notification === null) {
            http_response_code(404);
            echo 'Ön bildirim bulunamadı.';
            return;
        }

        $vehicle = $notification['vehicle_id'] === null
            ? $this->emptyVehicle()
            : $this->findVehicle((int) $notification['vehicle_id']);

        $validation = $this->consumeValidation();
        if ($validation['old'] !== []) {
            $notification = array_merge($notification, $validation['old']);
            $vehicle = [
                'plate_number' => $validation['old']['plate_number'] ?? ($vehicle['plate_number'] ?? ''),
                'brand' => $validation['old']['vehicle_brand'] ?? ($vehicle['brand'] ?? ''),
                'model' => $validation['old']['vehicle_model'] ?? ($vehicle['model'] ?? ''),
                'driver_name' => $validation['old']['driver_name'] ?? ($vehicle['driver_name'] ?? ''),
                'driver_phone' => $validation['old']['driver_phone'] ?? ($vehicle['driver_phone'] ?? ''),
            ];
        }

        $this->view('delivery_notifications/form', [
            'title' => 'Ön Bildirim Düzenle',
            'notification' => $notification,
            'vehicle' => $vehicle ?? $this->emptyVehicle(),
            'companies' => $this->companies(),
            'personSenders' => $this->personSenders(),
            'products' => $this->products(),
            'statusOptions' => self::STATUS_OPTIONS,
            'action' => '/delivery-notifications/update',
            'message' => (string) $this->input('message', ''),
            'validation' => $validation,
        ]);
    }

    public function update(): void
    {
        $id = (int) $this->input('id');

        $errors = $this->validationErrors();

        if ($errors !== []) {
            $this->redirectWithValidation($this->returnTo('/delivery-notifications/edit?id=' . $id . '&message=invalid', 'invalid'), $errors);
        }

        $senderCompanyId = $this->senderCompanyId();
        $vehicleId = $this->upsertVehicle($senderCompanyId);
        $payload = $this->payload($vehicleId, $senderCompanyId);
        $payload['id'] = $id;

        if ($this->statusIsActive($payload['status']) && $this->activeNotificationExistsForVehicle($vehicleId, $id)) {
            $this->redirectWithValidation($this->returnTo('/delivery-notifications/edit?id=' . $id . '&message=active_plate_exists', 'active_plate_exists'), [
                'plate_number' => 'Bu plaka için aktif bir süreç zaten var.',
            ]);
        }

        $statement = Database::connection()->prepare(
            'UPDATE delivery_notifications
             SET company_id = :company_id,
                 product_id = :product_id,
                 vehicle_id = :vehicle_id,
                 expected_quantity_kg = :expected_quantity_kg,
                 loading_date = :loading_date,
                 expected_arrival_date = :expected_arrival_date,
                 entry_type = :entry_type,
                 sender_type = :sender_type,
                 dispatch_number = :dispatch_number,
                 identity_number = :identity_number,
                 sender_name = :sender_name,
                 sender_tax_number = :sender_tax_number,
                 sender_phone = :sender_phone,
                 sender_address = :sender_address,
                 status = :status,
                 notes = :notes,
                 operation_type = :operation_type
             WHERE id = :id'
        );

        $statement->execute($payload);
        AuditLogger::log('delivery_notification.updated', 'delivery_notifications', $id, $payload);
        DriverVehicleRegistry::recordUsageForEntry($id);

        $this->redirect($this->returnTo('/delivery-notifications?message=updated', 'updated'));
    }

    public function cancel(): void
    {
        $this->ensureNotificationMetaColumns();
        $entryId = (int) $this->input('id');
        $reason = $this->nullableInput('cancel_reason');
        $note = $this->nullableInput('cancel_note');

        if ($reason === null) {
            $this->redirect($this->returnTo('/delivery-notifications', 'cancel_reason_required'));
        }

        $statement = Database::connection()->prepare(
            'UPDATE delivery_notifications
             SET status = "iptal",
                 cancel_reason = :cancel_reason,
                 cancel_note = :cancel_note,
                 cancelled_at = NOW(),
                 cancelled_by = :cancelled_by,
                 operation_status = "closed",
                 operation_closed_at = COALESCE(operation_closed_at, NOW()),
                 updated_at = NOW()
             WHERE id = :id AND status IN ("pending", "ürün_bildirimi", "kantara_geldi", "at_weighbridge")'
        );
        $statement->execute([
            'id' => $entryId,
            'cancel_reason' => $reason,
            'cancel_note' => $note,
            'cancelled_by' => Auth::user()['id'] ?? null,
        ]);
        AuditLogger::log('delivery_notification.cancelled', 'delivery_notifications', $entryId, [
            'cancel_reason' => $reason,
            'cancel_note' => $note,
        ]);
        VehicleProcessHistory::record($entryId, null, 'iptal', 'Ürün bildirimi iptal edildi', $reason . ($note ? ' - ' . $note : ''));

        $this->redirect($this->returnTo('/delivery-notifications', 'cancelled'));
    }

    public function notifyCompany(): void
    {
        $this->saveNotificationNote('company_notified', 'Firma haberdar edildi');
    }

    public function addNote(): void
    {
        $this->saveNotificationNote('note_added', 'Ön bildirim notu eklendi');
    }

    private function saveNotificationNote(string $message, string $actionName): void
    {
        $this->ensureNotificationMetaColumns();
        $entryId = (int) $this->input('id');
        $note = $this->nullableInput('note');

        if ($note === null) {
            $this->redirect($this->returnTo('/delivery-notifications', 'note_required'));
        }

        $isCompanyNotification = $message === 'company_notified';
        $sql = $isCompanyNotification
            ? 'UPDATE delivery_notifications SET company_notified_at = NOW(), company_notified_by = :user_id, company_notified_note = :note, updated_at = NOW() WHERE id = :id'
            : 'UPDATE delivery_notifications SET notification_note_at = NOW(), notification_note_by = :user_id, notification_note = :note, updated_at = NOW() WHERE id = :id';

        Database::connection()->prepare($sql)->execute([
            'id' => $entryId,
            'note' => $note,
            'user_id' => Auth::user()['id'] ?? null,
        ]);
        AuditLogger::log('delivery_notification.' . $message, 'delivery_notifications', $entryId, ['note' => $note]);
        VehicleProcessHistory::record($entryId, null, 'ürün_bildirimi', $actionName, $note);

        $this->redirect($this->returnTo('/delivery-notifications', $message));
    }

    private function filteredNotifications(array $filters): array
    {
        $sql = 'SELECT
                    dn.*,
                    c.name AS company_name,
                    p.name AS product_name,
                    v.plate_number,
                    v.brand AS vehicle_brand,
                    v.model AS vehicle_model,
                    v.driver_name,
                    v.driver_phone
                FROM delivery_notifications dn
                INNER JOIN companies c ON c.id = dn.company_id
                INNER JOIN products p ON p.id = dn.product_id
                LEFT JOIN vehicles v ON v.id = dn.vehicle_id';

        $where = [];
        $params = [];

        if ($filters['plate'] !== '') {
            $where[] = 'v.plate_number LIKE :plate';
            $params['plate'] = '%' . $filters['plate'] . '%';
        }

        if ($filters['company_id'] !== '') {
            $where[] = 'dn.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        if ($filters['product_id'] !== '') {
            $where[] = 'dn.product_id = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }

        if ($filters['date'] !== '') {
            $where[] = '(dn.loading_date = :filter_date OR dn.expected_arrival_date = :filter_date)';
            $params['filter_date'] = $filters['date'];
        }

        if ($filters['status'] !== '' && isset(self::STATUS_OPTIONS[$filters['status']])) {
            $where[] = 'dn.status = :status';
            $params['status'] = $filters['status'];
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY dn.expected_arrival_date IS NULL, dn.expected_arrival_date DESC, dn.id DESC LIMIT 100';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function upsertVehicle(int $senderCompanyId): int
    {
        return DriverVehicleRegistry::upsertFromRequest($senderCompanyId, $_POST);
    }

    private function payload(int $vehicleId, int $senderCompanyId): array
    {
        $expectedQuantityKg = $this->decimalInputOrNull('expected_quantity_ton') !== null
            ? (string) ((float) $this->decimalInputOrNull('expected_quantity_ton') * 1000)
            : $this->decimalInputOrNull('expected_quantity_kg');

        return [
            'company_id' => $senderCompanyId,
            'product_id' => (int) $this->input('product_id'),
            'vehicle_id' => $vehicleId,
            'expected_quantity_kg' => $expectedQuantityKg,
            'loading_date' => $this->nullableInput('loading_date'),
            'expected_arrival_date' => $this->nullableInput('expected_arrival_date'),
            'entry_type' => 'pre_notified',
            'sender_type' => $this->senderType(),
            'dispatch_number' => $this->senderType() === 'company' ? $this->nullableInput('dispatch_number') : null,
            'identity_number' => $this->senderType() === 'person' ? preg_replace('/\D+/', '', (string) $this->input('identity_number', '')) : null,
            'sender_name' => $this->senderType() === 'person' ? $this->nullableInput('sender_name') : null,
            'sender_tax_number' => $this->senderType() === 'company'
                ? $this->nullableInput('sender_tax_number')
                : preg_replace('/\D+/', '', (string) $this->input('identity_number', '')),
            'sender_phone' => $this->nullableInput('sender_phone'),
            'sender_address' => $this->nullableInput('sender_address'),
            'status' => $this->status() === 'pending' ? 'ürün_bildirimi' : $this->status(),
            'notes' => $this->nullableInput('notes'),
            'operation_type' => (string) $this->input('operation_type', '') === 'PRODUCT_OUT' ? 'PRODUCT_OUT' : 'PRODUCT_IN',
        ];
    }

    private function validationErrors(): array
    {
        $expectedQuantity = $this->decimalInputOrNull('expected_quantity_ton') ?? $this->decimalInputOrNull('expected_quantity_kg');
        $plate = $this->plateReader->normalize((string) $this->input('plate_number'));
        $senderType = $this->senderType();
        $identityNumber = preg_replace('/\D+/', '', (string) $this->input('identity_number', ''));
        $operationType = (string) $this->input('operation_type', '');
        $errors = [];

        if (! in_array($operationType, ['PRODUCT_IN', 'PRODUCT_OUT'], true)) {
            $errors['operation_type'] = 'İşlem tipi zorunludur.';
        }

        if ((int) $this->input('product_id') <= 0) {
            $errors['product_id'] = 'Bu alan zorunludur.';
        }

        if ($expectedQuantity !== null && (float) $expectedQuantity < 0) {
            $errors['expected_quantity_ton'] = 'Miktar sıfırdan küçük olamaz.';
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

    private function companySenderIsValid(): bool
    {
        if ((int) $this->input('company_id') > 0) {
            return true;
        }

        return $this->nullableInput('company_name') !== null;
    }

    private function senderCompanyId(): int
    {
        if ($this->senderType() === 'person') {
            return $this->ensurePersonCompany();
        }

        $companyId = (int) $this->input('company_id');

        if ($companyId > 0) {
            return $companyId;
        }

        return $this->createOrFindCompanyByName((string) $this->input('company_name', ''));
    }

    private function senderType(): string
    {
        return (string) $this->input('sender_type', 'company') === 'person' ? 'person' : 'company';
    }

    private function status(): string
    {
        $status = (string) $this->input('status', 'pending');

        return isset(self::STATUS_OPTIONS[$status]) ? $status : 'pending';
    }

    private function notificationNumber(): string
    {
        return 'ONB-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
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

    private function products(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
    }

    private function findNotification(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM delivery_notifications WHERE id = :id');
        $statement->execute(['id' => $id]);
        $notification = $statement->fetch(PDO::FETCH_ASSOC);

        return $notification === false ? null : $notification;
    }

    private function findVehicle(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM vehicles WHERE id = :id');
        $statement->execute(['id' => $id]);
        $vehicle = $statement->fetch(PDO::FETCH_ASSOC);

        return $vehicle === false ? null : $vehicle;
    }

    private function findVehicleByPlate(string $plateNumber): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM vehicles WHERE REPLACE(plate_number, " ", "") = :plate_number LIMIT 1'
        );
        $statement->execute(['plate_number' => $plateNumber]);
        $vehicle = $statement->fetch(PDO::FETCH_ASSOC);

        return $vehicle === false ? null : $vehicle;
    }

    private function activeNotificationExistsForVehicle(int $vehicleId, ?int $exceptNotificationId = null): bool
    {
        $placeholders = implode(', ', array_fill(0, count(self::ACTIVE_STATUSES), '?'));
        $params = [$vehicleId, ...self::ACTIVE_STATUSES];
        $sql = 'SELECT COUNT(*) FROM delivery_notifications
                WHERE vehicle_id = ? AND status IN (' . $placeholders . ')';

        if ($exceptNotificationId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptNotificationId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private function statusIsActive(string $status): bool
    {
        return in_array($status, self::ACTIVE_STATUSES, true);
    }

    private function plateIsValid(string $plate): bool
    {
        return (bool) preg_match('/^[0-9]{2}\s?[A-Z]{1,3}\s?[0-9]{2,5}$/', $plate);
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

    private function createOrFindCompanyByName(string $name): int
    {
        $name = trim($name);

        if ($name === '') {
            return 0;
        }

        $taxNumber = $this->nullableInput('sender_tax_number');
        if ($taxNumber !== null) {
            $statement = Database::connection()->prepare(
                'SELECT id FROM companies WHERE tax_number = :tax_number LIMIT 1'
            );
            $statement->execute(['tax_number' => $taxNumber]);
            $id = $statement->fetchColumn();

            if ($id !== false) {
                return (int) $id;
            }
        }

        $statement = Database::connection()->prepare(
            'SELECT id FROM companies WHERE LOWER(name) = LOWER(:name) LIMIT 1'
        );
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
        AuditLogger::log('company.quick_created_from_notification', 'companies', $companyId, [
            'name' => $name,
        ]);

        return $companyId;
    }

    private function emptyNotification(): array
    {
        return [
            'id' => null,
            'company_id' => '',
            'product_id' => '',
            'expected_quantity_kg' => '',
            'entry_type' => 'pre_notified',
            'sender_type' => 'company',
            'dispatch_number' => '',
            'identity_number' => '',
            'sender_name' => '',
            'sender_tax_number' => '',
            'sender_phone' => '',
            'sender_address' => '',
            'loading_date' => '',
            'expected_arrival_date' => '',
            'status' => 'ürün_bildirimi',
            'notes' => '',
        ];
    }

    private function emptyVehicle(): array
    {
        return [
            'plate_number' => '',
            'brand' => '',
            'model' => '',
            'driver_name' => '',
            'driver_phone' => '',
        ];
    }

    private function returnTo(string $default, string $message): string
    {
        $returnTo = (string) $this->input('return_to', '');

        if ($returnTo === 'product_operations_pre_notifications') {
            return '/product-operations/pre-notifications?message=' . urlencode($message);
        }

        if ($returnTo === 'product_operations_entry') {
            return '/product-operations/entry?message=' . urlencode($message);
        }

        if ($returnTo === 'product_operations') {
            return '/product-operations?message=' . urlencode($message);
        }

        return $default;
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
