<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\OutboundProcessHistory;
use App\Services\PlateReaderService;
use PDO;

final class OutboundLoadingController extends Controller
{
    public function __construct(private readonly PlateReaderService $plateReader = new PlateReaderService())
    {
    }

    public function index(): void
    {
        $this->ensureSchema();

        $this->view('outbound_loadings/index', [
            'title' => 'Silo Boşaltımı / Ürün Çıkışı',
            'message' => (string) $this->input('message', ''),
            'companies' => $this->companies(),
            'personSenders' => $this->personSenders(),
            'products' => $this->products(),
            'silos' => $this->silos(),
            'records' => $this->records(),
            'selectedRecord' => ((int) $this->input('id')) > 0 ? $this->findRecord((int) $this->input('id')) : null,
            'validation' => $this->consumeValidation(),
        ]);
    }

    public function store(): void
    {
        $this->ensureSchema();
        $senderType = in_array((string) $this->input('sender_type', 'company'), ['company', 'person'], true)
            ? (string) $this->input('sender_type', 'company')
            : 'company';
        $plate = $this->plateReader->normalize((string) $this->input('plate_number', ''));
        $productId = (int) $this->input('product_id');
        $siloId = (int) $this->input('source_silo_id');
        $planned = $this->decimalInputOrNull('planned_quantity_kg');
        $operationType = (string) $this->input('operation_type', '');
        $errors = [];

        if ($operationType !== 'PRODUCT_OUT') $errors['operation_type'] = 'Çıkış işlem tipi zorunludur.';
        if ($plate === '') $errors['plate_number'] = 'Plaka zorunludur.';
        if ($productId <= 0) $errors['product_id'] = 'Ürün seçiniz.';
        if ($siloId <= 0) $errors['source_silo_id'] = 'Silo seçiniz.';
        if ($planned === null || (float) $planned <= 0) $errors['planned_quantity_kg'] = 'Planlanan miktar sıfırdan büyük olmalıdır.';
        if (
            $senderType === 'company'
            && (int) $this->input('company_id') <= 0
            && trim((string) $this->input('company_name', '')) === ''
        ) {
            $errors['company_name'] = 'Firma bilgisi giriniz.';
        }
        if ($senderType === 'person' && trim((string) $this->input('sender_name', '')) === '') $errors['sender_name'] = 'Şahıs adı zorunludur.';

        $silo = $siloId > 0 ? $this->findSilo($siloId) : null;
        if ($silo !== null && (int) ($silo['product_id'] ?? 0) > 0 && (int) $silo['product_id'] !== $productId) {
            $errors['source_silo_id'] = 'Seçilen silodaki ürün tipi çıkış ürünüyle uyumlu değil.';
        }

        if ($errors !== []) {
            $this->redirectWithValidation($this->returnTo('invalid'), $errors);
        }

        $companyId = $senderType === 'company' ? $this->senderCompanyId() : null;

        $initialStatus = (string) $this->input('outbound_status', 'OUTBOUND_ARRIVED') === 'OUTBOUND_PRE_NOTIFIED'
            ? 'OUTBOUND_PRE_NOTIFIED'
            : 'OUTBOUND_ARRIVED';

        Database::connection()->prepare(
            'INSERT INTO outbound_loadings
                (operation_number, sender_type, company_id, sender_name, plate_number, normalized_plate, driver_name,
                 product_id, source_silo_id, planned_quantity_kg, dispatch_number, sender_tax_number, sender_phone, sender_address,
                 operation_type, status, note, created_at, updated_at)
             VALUES
                (:operation_number, :sender_type, :company_id, :sender_name, :plate_number, :normalized_plate, :driver_name,
                 :product_id, :source_silo_id, :planned_quantity_kg, :dispatch_number, :sender_tax_number, :sender_phone, :sender_address,
                 "PRODUCT_OUT", :status, :note, NOW(), NOW())'
        )->execute([
            'operation_number' => 'CIK-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2))),
            'sender_type' => $senderType,
            'company_id' => $senderType === 'company' ? $companyId : null,
            'sender_name' => $senderType === 'person' ? trim((string) $this->input('sender_name')) : null,
            'plate_number' => $plate,
            'normalized_plate' => $plate,
            'driver_name' => trim((string) $this->input('driver_name', '')) ?: null,
            'product_id' => $productId,
            'source_silo_id' => $siloId,
            'planned_quantity_kg' => $planned,
            'dispatch_number' => $this->dispatchNumber(),
            'sender_tax_number' => $senderType === 'company' ? $this->nullableInput('sender_tax_number') : null,
            'sender_phone' => $this->nullableInput('sender_phone'),
            'sender_address' => $this->nullableInput('sender_address'),
            'status' => $initialStatus,
            'note' => trim((string) $this->input('note', '')) ?: null,
        ]);

        $outboundId = (int) Database::connection()->lastInsertId();
        OutboundProcessHistory::record(
            $outboundId,
            null,
            $initialStatus,
            $initialStatus === 'OUTBOUND_PRE_NOTIFIED' ? 'Çıkış ön bildirimi oluşturuldu' : 'Ürün çıkışı kaydı oluşturuldu',
            trim((string) $this->input('note', '')) ?: null
        );

        $this->redirect($this->returnTo('created', $outboundId));
    }

    public function startArrived(): void
    {
        $this->ensureSchema();
        $record = $this->findRecord((int) $this->input('id'));

        if ($record === null || (string) ($record['status'] ?? '') !== 'OUTBOUND_PRE_NOTIFIED') {
            $this->redirect($this->returnTo('invalid'));
        }

        OutboundProcessHistory::changeStatus(
            (int) $record['id'],
            'OUTBOUND_ARRIVED',
            'Çıkış akışı başlatıldı',
            trim((string) $this->input('note', '')) ?: 'Ön bildirimden çıkış sürecine aktarıldı'
        );

        $this->redirect($this->returnTo('started', (int) $record['id']));
    }

    public function firstWeight(): void
    {
        $this->ensureSchema();
        $record = $this->findRecord((int) $this->input('id'));
        $weight = $this->decimalInputOrNull('first_weight_kg');

        if ($record === null || $weight === null || (float) $weight <= 0) {
            $this->redirect($this->returnTo('invalid'));
        }

        Database::connection()->prepare(
            'UPDATE outbound_loadings
             SET first_weight_kg = :first_weight_kg, first_weighed_at = NOW(), status = "OUTBOUND_FIRST_WEIGHED", updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => (int) $record['id'], 'first_weight_kg' => $weight]);

        OutboundProcessHistory::record(
            (int) $record['id'],
            (string) $record['status'],
            'OUTBOUND_FIRST_WEIGHED',
            '1. tartım kaydedildi',
            number_format((float) $weight, 0, ',', '.') . ' kg'
        );

        $this->redirect($this->returnTo('first_saved', (int) $record['id']));
    }

    public function assignSilo(): void
    {
        $this->ensureSchema();
        $record = $this->findRecord((int) $this->input('id'));

        if ($record === null || $record['first_weight_kg'] === null) {
            $this->redirect($this->returnTo('first_required'));
        }

        $silo = $this->findSilo((int) $record['source_silo_id']);
        if ($silo === null || (int) ($silo['product_id'] ?? 0) !== (int) $record['product_id']) {
            $this->redirect($this->returnTo('silo_mismatch', (int) $record['id']));
        }

        Database::connection()->prepare(
            'UPDATE outbound_loadings
             SET status = "OUTBOUND_LOADING_ASSIGNED_TO_SILO", assigned_at = NOW(), updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => (int) $record['id']]);

        OutboundProcessHistory::record(
            (int) $record['id'],
            (string) $record['status'],
            'OUTBOUND_LOADING_ASSIGNED_TO_SILO',
            'Yükleme alanına yönlendirildi',
            (string) ($silo['code'] ?? '') . ' - ' . (string) ($silo['name'] ?? '')
        );

        $this->redirect($this->returnTo('loading_assigned', (int) $record['id']));
    }

    public function sendToSecondWeighing(): void
    {
        $this->ensureSchema();
        $record = $this->findRecord((int) $this->input('id'));

        if ($record === null || (string) ($record['status'] ?? '') !== 'OUTBOUND_LOADING_ASSIGNED_TO_SILO') {
            $this->redirect($this->returnTo('invalid', $record !== null ? (int) $record['id'] : null));
        }

        OutboundProcessHistory::changeStatus(
            (int) $record['id'],
            'OUTBOUND_SECOND_WEIGHING_WAITING',
            '2. tartıma yönlendirildi',
            'Yükleme tamamlandı, dolu araç 2. tartıma alınacak'
        );

        $this->redirect('/second-weighing?outbound_id=' . (int) $record['id']);
    }

    public function cancel(): void
    {
        $this->ensureSchema();
        $record = $this->findRecord((int) $this->input('id'));

        if ($record === null) {
            $this->redirect($this->returnTo('invalid'));
        }

        $note = trim((string) $this->input('cancel_note', '')) ?: null;
        Database::connection()->prepare(
            'UPDATE outbound_loadings SET status = "OUTBOUND_REJECTED", note = COALESCE(:note, note), updated_at = NOW() WHERE id = :id'
        )->execute(['id' => (int) $record['id'], 'note' => $note]);

        OutboundProcessHistory::record(
            (int) $record['id'],
            (string) $record['status'],
            'OUTBOUND_REJECTED',
            'Çıkış kaydı iptal edildi',
            $note
        );

        $this->redirect($this->returnTo('cancelled', (int) $record['id']));
    }

    private function returnTo(string $message, ?int $id = null): string
    {
        if ((string) $this->input('return_to', '') === 'product_operations_entry') {
            return '/product-operations/entry?mode=outbound&message=' . urlencode($message) . ($id !== null ? '&outbound_id=' . $id : '');
        }

        return '/outbound-loadings?message=' . urlencode($message) . ($id !== null ? '&id=' . $id : '');
    }

    private function records(): array
    {
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

    private function findRecord(int $id): ?array
    {
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

    private function findSilo(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM silos WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $silo = $statement->fetch(PDO::FETCH_ASSOC);

        return $silo === false ? null : $silo;
    }

    private function companies(): array
    {
        return Database::connection()->query('SELECT id, name, tax_number, address, phone FROM companies WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
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

    private function senderCompanyId(): int
    {
        $companyId = (int) $this->input('company_id');

        if ($companyId > 0) {
            return $companyId;
        }

        return $this->createOrFindCompanyByName((string) $this->input('company_name', ''));
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
        AuditLogger::log('company.quick_created_from_outbound_loading', 'companies', $companyId, [
            'name' => $name,
        ]);

        return $companyId;
    }

    private function dispatchNumber(): string
    {
        $number = trim((string) $this->input('dispatch_number', ''));

        return $number !== '' ? $number : 'IRS-CIKIS-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    }

    private function products(): array
    {
        return Database::connection()->query('SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
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

    private function ensureSchema(): void
    {
        OutboundProcessHistory::ensureTable();
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
