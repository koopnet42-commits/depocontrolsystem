<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\CryptoService;
use App\Services\SettingsService;
use PDO;

final class SettingsController extends Controller
{
    private const TABS = ['company', 'cameras', 'scales', 'barriers', 'system'];

    public function index(): void
    {
        $tab = $this->tab();

        $this->view('settings/index', [
            'title' => 'Ayarlar',
            'tab' => $tab,
            'message' => (string) $this->input('message', ''),
            'company' => SettingsService::company(),
            'system' => SettingsService::system(),
            'cameras' => $this->rows('camera_settings'),
            'scales' => $this->rows('scale_settings'),
            'barriers' => $this->rows('barrier_settings'),
            'editCamera' => $this->find('camera_settings', (int) $this->input('edit_camera')),
            'editScale' => $this->find('scale_settings', (int) $this->input('edit_scale')),
            'editBarrier' => $this->find('barrier_settings', (int) $this->input('edit_barrier')),
        ]);
    }

    public function saveCompany(): void
    {
        $payload = [
            'company_name' => trim((string) $this->input('company_name')),
            'address' => $this->nullableInput('address'),
            'tax_office' => $this->nullableInput('tax_office'),
            'tax_number' => $this->nullableInput('tax_number'),
            'phone' => $this->nullableInput('phone'),
            'email' => $this->nullableInput('email'),
            'website' => $this->nullableInput('website'),
            'contact_person' => $this->nullableInput('contact_person'),
            'license_number' => $this->nullableInput('license_number'),
            'letterhead_text' => $this->nullableInput('letterhead_text'),
            'logo_path' => $this->uploadedLogoPath() ?? (SettingsService::company()['logo_path'] ?? null),
        ];

        if ($payload['company_name'] === '') {
            $this->redirect('/settings?tab=company&message=invalid');
        }

        $exists = $this->settingsExists('company_settings');
        $sql = $exists
            ? 'UPDATE company_settings SET company_name = :company_name, logo_path = :logo_path, address = :address,
                 tax_office = :tax_office, tax_number = :tax_number, phone = :phone, email = :email,
                 website = :website, contact_person = :contact_person, license_number = :license_number,
                 letterhead_text = :letterhead_text, updated_at = NOW() WHERE id = 1'
            : 'INSERT INTO company_settings
                 (id, company_name, logo_path, address, tax_office, tax_number, phone, email, website, contact_person, license_number, letterhead_text)
               VALUES
                 (1, :company_name, :logo_path, :address, :tax_office, :tax_number, :phone, :email, :website, :contact_person, :license_number, :letterhead_text)';

        Database::connection()->prepare($sql)->execute($payload);
        AuditLogger::log('settings.company_saved', 'company_settings', 1, $payload);
        $this->redirect('/settings?tab=company&message=saved');
    }

    public function saveSystem(): void
    {
        $mode = (string) $this->input('operation_mode', 'simulation');
        $payload = [
            'operation_mode' => in_array($mode, ['simulation', 'real'], true) ? $mode : 'simulation',
            'default_printer' => $this->nullableInput('default_printer'),
            'barcode_type' => in_array((string) $this->input('barcode_type'), ['QR', 'Barcode'], true) ? (string) $this->input('barcode_type') : 'QR',
            'auto_plate_recognition' => $this->boolInput('auto_plate_recognition'),
            'manual_weight_allowed' => $this->boolInput('manual_weight_allowed'),
            'manual_weight_reason_required' => $this->boolInput('manual_weight_reason_required'),
            'critical_confirmation_enabled' => $this->boolInput('critical_confirmation_enabled'),
            'auto_backup_enabled' => $this->boolInput('auto_backup_enabled'),
            'dashboard_silo_view' => in_array((string) $this->input('dashboard_silo_view'), ['horizontal', 'vertical'], true)
                ? (string) $this->input('dashboard_silo_view')
                : 'vertical',
            'qr_content_fields' => json_encode($this->qrContentFields(), JSON_UNESCAPED_UNICODE),
        ];

        $exists = $this->settingsExists('system_settings');
        $sql = $exists
            ? 'UPDATE system_settings SET operation_mode = :operation_mode, default_printer = :default_printer,
                 barcode_type = :barcode_type, auto_plate_recognition = :auto_plate_recognition,
                 manual_weight_allowed = :manual_weight_allowed, manual_weight_reason_required = :manual_weight_reason_required,
                 critical_confirmation_enabled = :critical_confirmation_enabled, auto_backup_enabled = :auto_backup_enabled,
                 dashboard_silo_view = :dashboard_silo_view, qr_content_fields = :qr_content_fields,
                 updated_at = NOW() WHERE id = 1'
            : 'INSERT INTO system_settings
                 (id, operation_mode, default_printer, barcode_type, auto_plate_recognition, manual_weight_allowed,
                  manual_weight_reason_required, critical_confirmation_enabled, auto_backup_enabled, dashboard_silo_view, qr_content_fields)
               VALUES
                 (1, :operation_mode, :default_printer, :barcode_type, :auto_plate_recognition, :manual_weight_allowed,
                  :manual_weight_reason_required, :critical_confirmation_enabled, :auto_backup_enabled, :dashboard_silo_view, :qr_content_fields)';

        Database::connection()->prepare($sql)->execute($payload);
        AuditLogger::log('settings.system_saved', 'system_settings', 1, $payload);
        $this->redirect('/settings?tab=system&message=saved');
    }

    public function saveCamera(): void
    {
        $payload = [
            'name' => trim((string) $this->input('name')),
            'usage_location' => (string) $this->input('usage_location'),
            'camera_type' => (string) $this->input('camera_type'),
            'connection_url' => $this->nullableInput('connection_url'),
            'port' => $this->intInputOrNull('port'),
            'username' => $this->nullableInput('username'),
            'is_active' => $this->boolInput('is_active'),
        ];
        $password = $this->nullableInput('password');
        $this->saveDevice('camera_settings', $payload, ['password_encrypted' => $password === null ? null : CryptoService::encrypt($password)], 'cameras');
    }

    public function saveScale(): void
    {
        $payload = [
            'name' => trim((string) $this->input('name')),
            'usage_location' => (string) $this->input('usage_location'),
            'communication_type' => (string) $this->input('communication_type'),
            'ip_address' => $this->nullableInput('ip_address'),
            'port' => $this->intInputOrNull('port'),
            'com_port' => $this->nullableInput('com_port'),
            'baud_rate' => $this->intInputOrNull('baud_rate'),
            'data_bits' => $this->intInputOrNull('data_bits'),
            'stop_bits' => $this->nullableInput('stop_bits'),
            'parity' => $this->nullableInput('parity'),
            'read_format' => $this->nullableInput('read_format'),
            'is_active' => $this->boolInput('is_active'),
        ];
        $this->saveDevice('scale_settings', $payload, [], 'scales');
    }

    public function saveBarrier(): void
    {
        $payload = [
            'name' => trim((string) $this->input('name')),
            'usage_location' => (string) $this->input('usage_location'),
            'control_type' => (string) $this->input('control_type'),
            'ip_address' => $this->nullableInput('ip_address'),
            'port' => $this->intInputOrNull('port'),
            'relay_number' => $this->nullableInput('relay_number'),
            'open_command' => $this->nullableInput('open_command'),
            'close_command' => $this->nullableInput('close_command'),
            'is_active' => $this->boolInput('is_active'),
        ];
        $this->saveDevice('barrier_settings', $payload, [], 'barriers');
    }

    public function toggle(): void
    {
        $table = $this->safeTable((string) $this->input('table'));
        $id = (int) $this->input('id');

        if ($table !== null && $id > 0) {
            $row = $this->find($table, $id);
            if ($row !== null) {
                Database::connection()
                    ->prepare('UPDATE ' . $table . ' SET is_active = :is_active, updated_at = NOW() WHERE id = :id')
                    ->execute(['id' => $id, 'is_active' => (int) ! (bool) $row['is_active']]);
                AuditLogger::log('settings.device_toggled', $table, $id);
            }
        }

        $this->redirect('/settings?tab=' . $this->tabForTable($table) . '&message=saved');
    }

    public function test(): void
    {
        $table = $this->safeTable((string) $this->input('table'));
        $id = (int) $this->input('id');
        $action = (string) $this->input('action', 'test');

        if ($table !== null && $id > 0) {
            AuditLogger::log('settings.device_test_simulation', $table, $id, [
                'action' => $action,
                'simulation' => true,
                'value' => $table === 'scale_settings' ? random_int(12000, 45000) : 'OK',
            ]);
        }

        $this->redirect('/settings?tab=' . $this->tabForTable($table) . '&message=test_ok');
    }

    private function saveDevice(string $table, array $payload, array $extra, string $tab): void
    {
        if ($payload['name'] === '') {
            $this->redirect('/settings?tab=' . $tab . '&message=invalid');
        }

        $id = (int) $this->input('id');
        $payload = array_merge($payload, array_filter($extra, static fn ($value): bool => $value !== null));

        if ($id > 0 && $this->find($table, $id) !== null) {
            $assignments = implode(', ', array_map(static fn (string $key): string => $key . ' = :' . $key, array_keys($payload)));
            $payload['id'] = $id;
            Database::connection()->prepare('UPDATE ' . $table . ' SET ' . $assignments . ', updated_at = NOW() WHERE id = :id')->execute($payload);
            AuditLogger::log('settings.device_updated', $table, $id, $payload);
        } else {
            $columns = implode(', ', array_keys($payload));
            $values = implode(', ', array_map(static fn (string $key): string => ':' . $key, array_keys($payload)));
            Database::connection()->prepare('INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $values . ')')->execute($payload);
            AuditLogger::log('settings.device_created', $table, (int) Database::connection()->lastInsertId(), $payload);
        }

        $this->redirect('/settings?tab=' . $tab . '&message=saved');
    }

    private function uploadedLogoPath(): ?string
    {
        if (empty($_FILES['logo']['tmp_name']) || ! is_uploaded_file($_FILES['logo']['tmp_name'])) {
            return null;
        }

        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $mime = mime_content_type($_FILES['logo']['tmp_name']) ?: '';

        if (! isset($allowed[$mime]) || (int) $_FILES['logo']['size'] > 2_000_000) {
            return null;
        }

        $directory = BASE_PATH . '/public/uploads';
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'company-logo-' . date('YmdHis') . '.' . $allowed[$mime];
        move_uploaded_file($_FILES['logo']['tmp_name'], $directory . '/' . $filename);

        return '/uploads/' . $filename;
    }

    private function rows(string $table): array
    {
        return Database::connection()->query('SELECT * FROM ' . $table . ' ORDER BY is_active DESC, id DESC')->fetchAll();
    }

    private function find(string $table, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT * FROM ' . $table . ' WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function settingsExists(string $table): bool
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() > 0;
    }

    private function qrContentFields(): array
    {
        $allowed = [
            'company_name', 'company_tax_number', 'plate_number', 'product_name', 'first_weight',
            'analysis_values', 'silo_code', 'silo_name', 'ticket_code', 'issued_at', 'driver_name',
            'dispatch_number', 'identity_number',
        ];
        $fields = $_POST['qr_content_fields'] ?? [];
        $fields = is_array($fields) ? $fields : [];
        $selected = array_values(array_intersect($allowed, $fields));

        return $selected === [] ? SettingsService::defaultQrFields() : $selected;
    }

    private function tab(): string
    {
        $tab = (string) $this->input('tab', 'company');

        return in_array($tab, self::TABS, true) ? $tab : 'company';
    }

    private function safeTable(string $table): ?string
    {
        return in_array($table, ['camera_settings', 'scale_settings', 'barrier_settings'], true) ? $table : null;
    }

    private function tabForTable(?string $table): string
    {
        return [
            'camera_settings' => 'cameras',
            'scale_settings' => 'scales',
            'barrier_settings' => 'barriers',
        ][$table ?? ''] ?? 'company';
    }
}
