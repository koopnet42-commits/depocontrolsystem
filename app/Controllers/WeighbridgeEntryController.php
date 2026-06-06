<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\BarrierGateService;
use App\Services\VehicleProcessHistory;
use App\Services\PlateReaderService;
use App\Services\WeighbridgeScaleService;
use PDO;

final class WeighbridgeEntryController extends Controller
{
    private const ROLLBACK_STATUSES = ['kantara_geldi', 'giriş_bariyeri_açıldı', 'kantarda', 'at_weighbridge'];
    private const ROLLBACK_TARGET_STATUS = 'giriş_bariyeri_bekliyor';

    public function __construct(
        private readonly PlateReaderService $plateReader = new PlateReaderService(),
        private readonly BarrierGateService $barrierGate = new BarrierGateService(),
        private readonly WeighbridgeScaleService $scale = new WeighbridgeScaleService(),
    ) {
    }

    public function index(): void
    {
        $plate = $this->plateReader->normalize((string) $this->input('plate', ''));
        $notification = $plate === '' || ! $this->plateIsValid($plate) ? null : $this->findKantarNotificationByPlate($plate);

        $activeRecord = $this->activeKantarRecord();

        $this->view('weighbridge_entry/index', [
            'title' => 'Kantar Giriş',
            'plate' => $plate,
            'notification' => $notification,
            'record' => $notification === null ? null : $this->findRecordByNotification((int) $notification['id']),
            'arrivals' => $this->arrivals(),
            'activeRecord' => $activeRecord,
            'scaleStatus' => $this->scale->status($activeRecord['plate_number'] ?? null),
            'analysisWaiting' => $this->analysisWaiting(),
            'secondWeighingWaiting' => $this->secondWeighingWaiting(),
            'recentRecords' => $this->recentRecords(),
            'message' => (string) $this->input('message', ''),
            'validation' => $this->consumeValidation(),
            'canRollbackWeighbridge' => $this->canRollbackWeighbridge(),
            'canAdminRollbackWeighbridge' => $this->canAdminRollbackWeighbridge(),
            'barrierOpened' => $this->input('barrier', '') === 'opened'
                || ($notification !== null && in_array($notification['status'], ['giriş_bariyeri_açıldı', 'kantarda', 'at_weighbridge'], true)),
        ]);
    }

    public function openBarrier(): void
    {
        $notification = $this->kantarNotificationFromRequest();

        if ($notification === null) {
            $this->redirect('/weighbridge-entry?message=not_found');
        }

        $activeVehicle = $this->activeWeighbridgeVehicle((int) $notification['id']);

        if ($activeVehicle !== null) {
            AuditLogger::log('barrier_blocked_weighbridge_busy', 'delivery_notifications', (int) $notification['id'], $activeVehicle);
            $this->redirect('/weighbridge-entry?message=weighbridge_busy');
        }

        $result = $this->barrierGate->open();

        if ($result['success']) {
            VehicleProcessHistory::changeStatus((int) $notification['id'], 'giriş_bariyeri_açıldı', 'Kantar girişi açıldı', 'Giriş bariyeri simülasyon olarak açıldı.');
        }

        AuditLogger::log($result['success'] ? 'barrier_open' : 'barrier_open_failed', 'delivery_notifications', (int) $notification['id'], [
            'plate' => $notification['plate_number'],
            'simulation' => true,
        ]);

        $message = $result['success'] ? 'barrier_opened' : 'barrier_failed';

        $this->redirect('/weighbridge-entry?message=' . $message);
    }

    public function markOnScale(): void
    {
        $notification = $this->kantarNotificationFromRequest();

        if ($notification === null) {
            $this->redirect('/weighbridge-entry?message=not_found');
        }

        if (! in_array($notification['status'], ['giriş_bariyeri_açıldı', 'at_weighbridge'], true)) {
            $this->redirect('/weighbridge-entry?message=barrier_required');
        }

        if ($this->findRecordByNotification((int) $notification['id']) === null) {
            Database::connection()->prepare(
                'INSERT INTO weighbridge_records
                    (ticket_number, delivery_notification_id, company_id, product_id, vehicle_id, status, notes)
                 VALUES
                    (:ticket_number, :delivery_notification_id, :company_id, :product_id, :vehicle_id, "entry", :notes)'
            )->execute([
                'ticket_number' => $this->scale->ticketNumber(),
                'delivery_notification_id' => (int) $notification['id'],
                'company_id' => (int) $notification['company_id'],
                'product_id' => (int) $notification['product_id'],
                'vehicle_id' => (int) $notification['vehicle_id'],
                'notes' => 'Araç kantara giriş yaptı. Bariyer kapandı olarak işaretlendi.',
            ]);
        }

        VehicleProcessHistory::changeStatus((int) $notification['id'], 'kantarda', 'Araç kantara çıktı', 'Bariyer kapandı, araç kantarda.');

        AuditLogger::log('weighbridge.vehicle_on_scale', 'delivery_notifications', (int) $notification['id'], [
            'plate' => $notification['plate_number'],
        ]);

        $this->redirect('/weighbridge-entry?message=vehicle_on_scale');
    }

    public function saveFirstWeight(): void
    {
        $notificationId = (int) $this->input('notification_id');
        $notification = $this->findKantarNotificationById($notificationId);

        if ($notification === null) {
            $this->redirect('/weighbridge-entry?message=not_found');
        }

        if (! in_array($notification['status'], ['kantara_geldi', 'kantarda', 'at_weighbridge'], true)) {
            $this->redirect('/weighbridge-entry?plate=' . urlencode($notification['plate_number']) . '&message=barrier_required');
        }

        $firstWeight = $this->decimalInputOrNull('first_weight_kg');
        $reason = $this->nullableInput('first_weight_reason');

        $errors = [];

        if ($firstWeight === null || (float) $firstWeight < 1000 || (float) $firstWeight > 100000) {
            $errors['first_weight_kg'] = 'İlk tartım 1.000 kg ile 100.000 kg arasında girilmelidir.';
        }

        if ($reason === null) {
            $errors['first_weight_reason'] = 'Manuel tartım değeri girildiğinde açıklama zorunludur.';
        }

        if ($errors !== []) {
            $this->redirectWithValidation('/weighbridge-entry?plate=' . urlencode($notification['plate_number']) . '&barrier=opened&message=invalid', $errors);
        }

        $existingRecord = $this->findRecordByNotification($notificationId);

        if ($existingRecord === null) {
            $statement = Database::connection()->prepare(
                'INSERT INTO weighbridge_records
                    (ticket_number, delivery_notification_id, company_id, product_id, vehicle_id,
                     first_weight_kg, first_weighed_at, status, notes)
                 VALUES
                    (:ticket_number, :delivery_notification_id, :company_id, :product_id, :vehicle_id,
                     :first_weight_kg, NOW(), "sampled", :notes)'
            );
            $statement->execute([
                'ticket_number' => $this->scale->ticketNumber(),
                'delivery_notification_id' => $notificationId,
                'company_id' => (int) $notification['company_id'],
                'product_id' => (int) $notification['product_id'],
                'vehicle_id' => (int) $notification['vehicle_id'],
                'first_weight_kg' => $firstWeight,
                'notes' => '1. tartım manuel giriş nedeni: ' . $reason,
            ]);
        } else {
            $statement = Database::connection()->prepare(
                'UPDATE weighbridge_records
                 SET first_weight_kg = :first_weight_kg,
                     first_weighed_at = NOW(),
                     status = "sampled",
                     notes = :notes
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => (int) $existingRecord['id'],
                'first_weight_kg' => $firstWeight,
                'notes' => trim((string) ($existingRecord['notes'] ?? '') . "\n1. tartım manuel giriş nedeni: " . $reason),
            ]);
        }

        VehicleProcessHistory::record($notificationId, (string) $notification['status'], 'ilk_tartım_alındı', 'İlk tartım alındı');
        VehicleProcessHistory::changeStatus($notificationId, 'analiz_bekliyor', 'Araç analiz için bekliyor', 'İlk tartım kaydedildi ve araç numune/analiz kuyruğuna alındı.');

        $this->redirect('/weighbridge-entry?plate=' . urlencode($notification['plate_number']) . '&message=weight_saved');
    }

    public function rollback(): void
    {
        if (! $this->canRollbackWeighbridge()) {
            $this->redirect('/weighbridge-entry?message=rollback_forbidden');
        }

        $notificationId = (int) $this->input('notification_id');
        $notification = $this->findKantarNotificationById($notificationId);

        if ($notification === null || ! in_array((string) $notification['status'], self::ROLLBACK_STATUSES, true)) {
            $this->redirect('/weighbridge-entry?message=not_found');
        }

        $record = $this->findRecordByNotification($notificationId);
        $hasFirstWeight = $record !== null && $record['first_weight_kg'] !== null && $record['first_weight_kg'] !== '';

        if ($hasFirstWeight && ! $this->canAdminRollbackWeighbridge()) {
            $this->redirect('/weighbridge-entry?plate=' . urlencode((string) $notification['plate_number']) . '&message=rollback_weight_locked');
        }

        $reason = $this->nullableInput('rollback_reason');
        $note = $this->nullableInput('rollback_note');
        $allowedReasons = ['wrong_vehicle', 'plate_mixed', 'not_on_scale', 'operator_error', 'other'];
        $errors = [];

        if ($reason === null || ! in_array($reason, $allowedReasons, true)) {
            $errors['rollback_reason'] = 'Geri alma nedeni seçilmelidir.';
        }

        if ($reason === 'other' && $note === null) {
            $errors['rollback_note'] = 'Diğer nedeni için açıklama zorunludur.';
        }

        if ($hasFirstWeight && $note === null) {
            $errors['rollback_note'] = '1. tartım kaydı olan araç için yetkili açıklaması zorunludur.';
        }

        if ($errors !== []) {
            $this->redirectWithValidation('/weighbridge-entry?plate=' . urlencode((string) $notification['plate_number']) . '&message=invalid', $errors);
        }

        $oldStatus = (string) $notification['status'];
        $newStatus = self::ROLLBACK_TARGET_STATUS;
        $reasonLabel = $this->rollbackReasonLabel((string) $reason);
        $description = 'Yanlış araç seçimi nedeniyle kantar işlemi geri alındı. Neden: ' . $reasonLabel;
        if ($note !== null) {
            $description .= ' Açıklama: ' . $note;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $connection->prepare('UPDATE delivery_notifications SET status = :status, updated_at = NOW() WHERE id = :id')
                ->execute(['status' => $newStatus, 'id' => $notificationId]);

            if ($record !== null) {
                $recordNote = trim((string) ($record['notes'] ?? ''));
                $recordNote .= ($recordNote === '' ? '' : "\n") . 'Kantar geri alma: ' . $description;
                if ($hasFirstWeight) {
                    $recordNote .= "\n1. tartım kaydı fiziksel olarak silinmedi; yetkili geri alma nedeniyle geçersiz sayıldı.";
                }

                $connection->prepare(
                    'UPDATE weighbridge_records
                     SET status = :status,
                         notes = :notes,
                         updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'id' => (int) $record['id'],
                    'status' => 'cancelled',
                    'notes' => $recordNote,
                ]);
            }

            VehicleProcessHistory::record($notificationId, $oldStatus, $newStatus, 'Kantar işlemi geri alındı', $description);

            AuditLogger::log('weighbridge.rollback', 'delivery_notifications', $notificationId, [
                'entry_id' => $notificationId,
                'plate' => $notification['plate_number'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'reason_label' => $reasonLabel,
                'note' => $note,
                'had_first_weight' => $hasFirstWeight,
                'weighbridge_record_id' => $record['id'] ?? null,
                'service_note' => $description,
                'old' => ['status' => $oldStatus, 'weighbridge_status' => $record['status'] ?? null],
                'new' => ['status' => $newStatus, 'weighbridge_status' => $record === null ? null : 'cancelled'],
            ]);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            AuditLogger::log('weighbridge.rollback_failed', 'delivery_notifications', $notificationId, [
                'plate' => $notification['plate_number'],
                'message' => $exception->getMessage(),
            ]);
            $this->redirect('/weighbridge-entry?plate=' . urlencode((string) $notification['plate_number']) . '&message=rollback_failed');
        }

        $this->redirect('/weighbridge-entry?message=rollback_done');
    }

    private function canRollbackWeighbridge(): bool
    {
        return Auth::can('weighbridge.entry') || Auth::role() === 'master';
    }

    private function canAdminRollbackWeighbridge(): bool
    {
        return Auth::role() === 'admin' || Auth::role() === 'master';
    }

    private function rollbackReasonLabel(string $reason): string
    {
        return [
            'wrong_vehicle' => 'Yanlış araç seçildi',
            'plate_mixed' => 'Plaka karıştı',
            'not_on_scale' => 'Araç kantara çıkmadı',
            'operator_error' => 'Operatör hatası',
            'other' => 'Diğer',
        ][$reason] ?? $reason;
    }

    private function findKantarNotificationByPlate(string $plate): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                dn.*,
                c.name AS company_name,
                p.name AS product_name,
                v.plate_number,
                v.brand AS vehicle_brand,
                v.driver_name,
                v.driver_phone
             FROM delivery_notifications dn
             INNER JOIN companies c ON c.id = dn.company_id
             INNER JOIN products p ON p.id = dn.product_id
             INNER JOIN vehicles v ON v.id = dn.vehicle_id
             WHERE dn.status IN ("kantara_geldi", "kantara_yonlendirildi", "giriş_bariyeri_bekliyor", "giriş_bariyeri_açıldı", "kantarda", "at_weighbridge")
                AND REPLACE(v.plate_number, " ", "") = :plate
             ORDER BY dn.expected_arrival_date IS NULL, dn.expected_arrival_date ASC, dn.id ASC
             LIMIT 1'
        );
        $statement->execute(['plate' => $plate]);
        $notification = $statement->fetch(PDO::FETCH_ASSOC);

        return $notification === false ? null : $notification;
    }

    private function findKantarNotificationById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT dn.*, v.plate_number
             FROM delivery_notifications dn
             INNER JOIN vehicles v ON v.id = dn.vehicle_id
             WHERE dn.id = :id
                AND dn.status IN ("kantara_geldi", "kantara_yonlendirildi", "giriş_bariyeri_bekliyor", "giriş_bariyeri_açıldı", "kantarda", "at_weighbridge")'
        );
        $statement->execute(['id' => $id]);
        $notification = $statement->fetch(PDO::FETCH_ASSOC);

        return $notification === false ? null : $notification;
    }

    private function kantarNotificationFromRequest(): ?array
    {
        $id = (int) $this->input('notification_id');

        if ($id > 0) {
            return $this->findKantarNotificationById($id);
        }

        $plate = $this->plateReader->normalize((string) $this->input('plate', ''));

        return $plate !== '' && $this->plateIsValid($plate) ? $this->findKantarNotificationByPlate($plate) : null;
    }

    private function findRecordByNotification(int $notificationId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM weighbridge_records WHERE delivery_notification_id = :delivery_notification_id LIMIT 1'
        );
        $statement->execute(['delivery_notification_id' => $notificationId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function arrivals(): array
    {
        return $this->fetchKantarRows(
            'WHERE dn.status IN ("kantara_geldi", "kantara_yonlendirildi", "giriş_bariyeri_bekliyor", "at_weighbridge")
             ORDER BY dn.updated_at ASC, dn.id ASC
             LIMIT 60'
        );
    }

    private function activeKantarRecord(): ?array
    {
        $rows = $this->fetchKantarRows(
            'WHERE dn.status IN ("giriş_bariyeri_açıldı", "kantarda", "at_weighbridge")
             ORDER BY dn.updated_at ASC, dn.id ASC
             LIMIT 1'
        );

        return $rows[0] ?? null;
    }

    private function analysisWaiting(): array
    {
        return $this->fetchKantarRows(
            'WHERE dn.status IN ("analiz_bekliyor", "analizde", "in_analysis")
                AND wr.first_weight_kg IS NOT NULL
             ORDER BY wr.first_weighed_at ASC, dn.id ASC
             LIMIT 80'
        );
    }

    private function secondWeighingWaiting(): array
    {
        return $this->fetchKantarRows(
            'WHERE dn.status IN ("ikinci_tartım_bekliyor", "boşaltıldı", "unloaded")
             ORDER BY dn.updated_at ASC, dn.id ASC
             LIMIT 80'
        );
    }

    private function recentRecords(): array
    {
        return $this->fetchKantarRows(
            'WHERE wr.id IS NOT NULL
             ORDER BY wr.updated_at DESC, wr.id DESC
             LIMIT 20'
        );
    }

    private function fetchKantarRows(string $whereAndOrder): array
    {
        return Database::connection()
            ->query(
                'SELECT
                    dn.*,
                    c.name AS company_name,
                    p.name AS product_name,
                    v.plate_number,
                    v.brand AS vehicle_brand,
                    v.driver_name,
                    v.driver_phone,
                    wr.id AS weighbridge_record_id,
                    wr.ticket_number,
                    wr.first_weight_kg,
                    wr.first_weighed_at,
                    wr.status AS weighbridge_status
                 FROM delivery_notifications dn
                 INNER JOIN companies c ON c.id = dn.company_id
                 INNER JOIN products p ON p.id = dn.product_id
                 INNER JOIN vehicles v ON v.id = dn.vehicle_id
                 LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id ' . $whereAndOrder
            )
            ->fetchAll();
    }

    private function activeWeighbridgeVehicle(int $currentNotificationId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT dn.id, v.plate_number
             FROM delivery_notifications dn
             INNER JOIN vehicles v ON v.id = dn.vehicle_id
             WHERE dn.status IN ("giriş_bariyeri_açıldı", "kantarda", "at_weighbridge") AND dn.id <> :id
             ORDER BY dn.updated_at ASC, dn.id ASC
             LIMIT 1'
        );
        $statement->execute(['id' => $currentNotificationId]);
        $vehicle = $statement->fetch(PDO::FETCH_ASSOC);

        return $vehicle === false ? null : $vehicle;
    }

    private function plateIsValid(string $plate): bool
    {
        return (bool) preg_match('/^[0-9]{2}\s?[A-Z]{1,3}\s?[0-9]{2,5}$/', $plate);
    }
}
