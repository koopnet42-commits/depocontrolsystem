<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class SettingsService
{
    public static function company(): array
    {
        $settings = Database::connection()
            ->query('SELECT * FROM company_settings ORDER BY id ASC LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);

        return $settings === false ? self::emptyCompany() : $settings;
    }

    public static function system(): array
    {
        $settings = Database::connection()
            ->query('SELECT * FROM system_settings ORDER BY id ASC LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);

        return $settings === false ? self::emptySystem() : $settings;
    }

    public static function emptyCompany(): array
    {
        return [
            'company_name' => 'Depo Otomasyon Sistemi',
            'logo_path' => null,
            'address' => null,
            'tax_office' => null,
            'tax_number' => null,
            'phone' => null,
            'email' => null,
            'website' => null,
            'contact_person' => null,
            'license_number' => null,
            'letterhead_text' => null,
        ];
    }

    private static function emptySystem(): array
    {
        return [
            'operation_mode' => 'simulation',
            'default_printer' => null,
            'barcode_type' => 'QR',
            'auto_plate_recognition' => 0,
            'manual_weight_allowed' => 1,
            'manual_weight_reason_required' => 1,
            'critical_confirmation_enabled' => 1,
            'auto_backup_enabled' => 0,
            'dashboard_silo_view' => 'vertical',
            'qr_content_fields' => json_encode(self::defaultQrFields(), JSON_UNESCAPED_UNICODE),
        ];
    }

    public static function defaultQrFields(): array
    {
        return ['company_name', 'plate_number', 'product_name', 'first_weight', 'silo_code', 'ticket_code', 'issued_at'];
    }
}
