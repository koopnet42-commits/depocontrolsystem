<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class ReportService
{
    public const STATUS_OPTIONS = [
        'pending' => 'Beklemede',
        'ürün_bildirimi' => 'Ürün Bildirimi',
        'kantara_geldi' => 'Kantara Geldi',
        'giriş_bariyeri_açıldı' => 'Giriş Bariyeri Açıldı',
        'kantarda' => 'Kantarda',
        'ilk_tartım_alındı' => 'İlk Tartım Alındı',
        'analiz_bekliyor' => 'Analiz Bekliyor',
        'analizde' => 'Analizde',
        'analiz_yapıldı' => 'Analiz Yapıldı',
        'silo_belirlendi' => 'Silo Belirlendi',
        'barkod_bekliyor' => 'Barkod Bekliyor',
        'siloya_yönlendirildi' => 'Siloya Yönlendirildi',
        'ikinci_tartım_bekliyor' => '2. Tartım Bekliyor',
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
        'OUTBOUND_ARRIVED' => 'Ürün Çıkışı Geldi',
        'OUTBOUND_FIRST_WEIGHED' => 'Çıkış 1. Tartım Alındı',
        'OUTBOUND_SECOND_WEIGHING_WAITING' => 'Çıkış 2. Tartım Bekliyor',
        'OUTBOUND_COMPLETED' => 'Ürün Çıkışı Tamamlandı',
        'OUTBOUND_REJECTED' => 'Ürün Çıkışı Reddedildi',
    ];

    public const REPORT_TITLES = [
        'today_analysis' => 'Bugünkü Analiz',
        'daily_product_entries' => 'Günlük Ürün Girişleri',
        'daily_vehicles' => 'Günlük Gelen Araçlar',
        'company_product_entries' => 'Firma / Şahıs Bazlı Ürün Girişleri',
        'product_net_totals' => 'Ürün Bazlı Toplam Net Kg',
        'active_operations' => 'Aktif Süreçteki Araçlar',
        'silo_occupancy' => 'Silo Bazlı Doluluk',
        'analysis_results' => 'Analiz Sonuçları',
        'completed_operations' => 'Tamamlanan İşlemler',
        'cancelled_rejected' => 'İptal Edilen / Reddedilen Araçlar',
        'rejected_products' => 'Reddedilen Ürünler Raporu',
        'daily_product_outputs' => 'Günlük Ürün Çıkışları',
        'silo_in_out' => 'Silo Bazlı Giriş / Çıkış',
        'company_product_outputs' => 'Firma / Şahıs Bazlı Ürün Yükleme',
        'product_stock_movements' => 'Ürün Türü Bazlı Stok Hareketleri',
        'net_silo_stock' => 'Net Silo Stok Raporu',
    ];

    private const REJECTION_REASON_SQL = 'CASE sa.rejection_reason
        WHEN "high_moisture" THEN "Rutubet yüksek"
        WHEN "high_foreign_matter" THEN "Yabancı madde oranı yüksek"
        WHEN "high_sunn_pest_rate" THEN "Süne oranı yüksek"
        WHEN "low_hectoliter" THEN "Hektolitre düşük"
        WHEN "not_suitable" THEN "Ürün alım kriterlerine uygun değil"
        WHEN "other" THEN "Diğer"
        ELSE COALESCE(sa.rejection_reason, "-")
    END';

    private const ACTIVE_STATUSES = [
        'pending',
        'ürün_bildirimi',
        'kantara_geldi',
        'giriş_bariyeri_açıldı',
        'kantarda',
        'ilk_tartım_alındı',
        'analiz_bekliyor',
        'analizde',
        'analiz_yapıldı',
        'silo_belirlendi',
        'barkod_bekliyor',
        'siloya_yönlendirildi',
        'ikinci_tartım_bekliyor',
    ];

    private DateTimeZone $timezone;

    public function __construct()
    {
        $this->timezone = new DateTimeZone('Europe/Istanbul');
    }

    public function filters(array $input): array
    {
        $today = new DateTimeImmutable('now', $this->timezone);
        $dateFrom = trim((string) ($input['date_from'] ?? ''));
        $dateTo = trim((string) ($input['date_to'] ?? ''));

        if (! $this->validDate($dateFrom)) {
            $dateFrom = $today->format('Y-m-d');
        }

        if (! $this->validDate($dateTo)) {
            $dateTo = $dateFrom;
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'company_id' => trim((string) ($input['company_id'] ?? '')),
            'product_id' => trim((string) ($input['product_id'] ?? '')),
            'silo_id' => trim((string) ($input['silo_id'] ?? '')),
            'status' => trim((string) ($input['status'] ?? '')),
        ];
    }

    public function reports(array $filters): array
    {
        $reports = [
            'today_analysis' => [
                'columns' => ['Analiz Tarihi', 'Plaka', 'Gönderici Tipi', 'Gönderici', 'Ürün', 'Sonuç', 'Rutubet', 'Protein', 'Hektolitre', 'Durum'],
                'rows' => $this->analysisResults($filters),
            ],
            'daily_product_entries' => [
                'columns' => ['Giriş Tarihi', 'Bildirim No', 'Plaka', 'Gönderici Tipi', 'Gönderici', 'Ürün', 'Bildirilen Ton', 'Durum'],
                'rows' => $this->dailyProductEntries($filters),
            ],
            'daily_vehicles' => [
                'columns' => ['Tarih', 'Araç Sayısı'],
                'rows' => $this->dailyVehicles($filters),
            ],
            'company_product_entries' => [
                'columns' => ['Gönderici Tipi', 'Gönderici', 'Ürün', 'Araç Sayısı', 'Bildirilen Ton', 'Net Kg', 'Net Ton'],
                'rows' => $this->companyProductEntries($filters),
            ],
            'product_net_totals' => [
                'columns' => ['Ürün', 'Kayıt Sayısı', 'Toplam Net Kg', 'Toplam Net Ton'],
                'rows' => $this->productNetTotals($filters),
            ],
            'active_operations' => [
                'columns' => ['Son Güncelleme', 'Bildirim No', 'Plaka', 'Gönderici', 'Ürün', 'Durum', 'İlk Tartım', '2. Tartım'],
                'rows' => $this->activeOperations($filters),
            ],
            'silo_occupancy' => [
                'columns' => ['Silo Kodu', 'Silo Adı', 'Ürün', 'Kapasite Ton', 'Doluluk Ton', 'Doluluk %'],
                'rows' => $this->siloOccupancy($filters),
                'ignoreDate' => true,
            ],
            'analysis_results' => [
                'columns' => ['Tarih', 'Plaka', 'Gönderici Tipi', 'Gönderici', 'Ürün', 'Rutubet', 'Protein', 'Hektolitre', 'Gluten', 'Süne', 'Yabancı Madde', 'Kırık Tane', 'Sonuç'],
                'rows' => $this->analysisResults($filters),
            ],
            'completed_operations' => [
                'columns' => ['Kapanış Tarihi', 'Plaka', 'Gönderici Tipi', 'Gönderici', 'Ürün', 'Silo', 'Net Kg', 'Net Ton'],
                'rows' => $this->completedOperations($filters),
            ],
            'cancelled_rejected' => [
                'columns' => ['Tarih', 'Plaka', 'Gönderici Tipi', 'Gönderici', 'Ürün', 'Durum', 'Analiz Sonucu', 'Ret Sebebi'],
                'rows' => $this->cancelledRejected($filters),
            ],
            'rejected_products' => [
                'columns' => ['Tarih', 'Plaka', 'Gönderici Tipi', 'Gönderici', 'Ürün', 'Ret Sebebi', 'Açıklama'],
                'rows' => $this->rejectedProducts($filters),
            ],
            'daily_product_outputs' => [
                'columns' => ['Tarih', 'Çıkış No', 'Plaka', 'Alıcı Tipi', 'Alıcı', 'Ürün', 'Kaynak Silo', 'Planlanan Ton', 'Net Kg', 'Durum'],
                'rows' => $this->dailyProductOutputs($filters),
            ],
            'silo_in_out' => [
                'columns' => ['Silo Kodu', 'Silo Adı', 'Ürün', 'Giriş Kg', 'Çıkış Kg', 'Net Değişim Kg'],
                'rows' => $this->siloInOut($filters),
            ],
            'company_product_outputs' => [
                'columns' => ['Alıcı Tipi', 'Alıcı', 'Ürün', 'Araç Sayısı', 'Planlanan Ton', 'Net Kg', 'Net Ton'],
                'rows' => $this->companyProductOutputs($filters),
            ],
            'product_stock_movements' => [
                'columns' => ['Ürün', 'Hareket Tipi', 'Kayıt Sayısı', 'Toplam Kg', 'Toplam Ton'],
                'rows' => $this->productStockMovements($filters),
            ],
            'net_silo_stock' => [
                'columns' => ['Silo Kodu', 'Silo Adı', 'Ürün', 'Mevcut Kg', 'Mevcut Ton', 'Kapasite Ton', 'Doluluk %'],
                'rows' => $this->netSiloStock($filters),
                'ignoreDate' => true,
            ],
        ];

        $metadata = $this->metadata($filters);

        foreach ($reports as $key => $report) {
            $records = $report['rows'];
            $reports[$key]['records'] = $records;
            $reports[$key]['totalCount'] = count($records);
            $reports[$key]['dateRange'] = $metadata['dateRange'];
            $reports[$key]['appliedFilters'] = $metadata['appliedFilters'];
            $reports[$key]['groupedSummary'] = $this->groupedSummary($records);
        }

        return $reports;
    }

    public function response(array $filters, ?string $report = null): array
    {
        $reports = $this->reports($filters);
        if ($report !== null && isset($reports[$report])) {
            return $this->responseForReport($report, $reports[$report]);
        }

        return [
            'dateRange' => $this->metadata($filters)['dateRange'],
            'appliedFilters' => $this->metadata($filters)['appliedFilters'],
            'reports' => array_map(fn (array $item, string $key): array => $this->responseForReport($key, $item), $reports, array_keys($reports)),
            'dataQualityIssues' => $this->dataQualityIssues($filters),
        ];
    }

    public function companies(): array
    {
        return $this->fetch('SELECT id, name FROM companies ORDER BY name ASC');
    }

    public function products(): array
    {
        return $this->fetch('SELECT id, name FROM products ORDER BY name ASC');
    }

    public function silos(): array
    {
        return $this->fetch('SELECT id, code, name FROM silos ORDER BY code ASC');
    }

    public function dataQualityIssues(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'dn.created_at', 'dn');
        $where[] = '(dn.vehicle_id IS NULL OR dn.product_id IS NULL OR dn.company_id IS NULL OR (wr.first_weight_kg IS NOT NULL AND wr.second_weight_kg IS NOT NULL AND wr.second_weight_kg > wr.first_weight_kg) OR (dn.status = "tamamlandı" AND wr.second_weight_kg IS NULL))';

        return $this->fetch(
            'SELECT dn.notification_number, COALESCE(v.plate_number, "-") AS plate_number, dn.status,
                    CASE
                        WHEN dn.vehicle_id IS NULL THEN "Araç bağlantısı eksik"
                        WHEN dn.product_id IS NULL THEN "Ürün bağlantısı eksik"
                        WHEN dn.company_id IS NULL THEN "Gönderici bağlantısı eksik"
                        WHEN wr.second_weight_kg > wr.first_weight_kg THEN "2. tartım ilk tartımdan büyük"
                        WHEN dn.status = "tamamlandı" AND wr.second_weight_kg IS NULL THEN "Tamamlandı ama 2. tartım yok"
                        ELSE "Kontrol gerekli"
                    END AS issue
             FROM delivery_notifications dn
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY dn.updated_at DESC
             LIMIT 100',
            $params
        );
    }

    public function ensureSchema(): void
    {
        try {
            $database = Database::connection();
            $driver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);
            $columns = $driver === 'sqlite'
                ? array_column($database->query('PRAGMA table_info(sample_analysis)')->fetchAll(PDO::FETCH_ASSOC), 'name')
                : array_column($database->query('SHOW COLUMNS FROM sample_analysis')->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $definitions = $driver === 'sqlite'
                ? [
                    'result_status' => 'TEXT NOT NULL DEFAULT "kabul"',
                    'conditional_reason' => 'TEXT NULL',
                    'conditional_note' => 'TEXT NULL',
                    'rejection_reason' => 'TEXT NULL',
                    'rejection_note' => 'TEXT NULL',
                    'rejected_at' => 'TEXT NULL',
                    'rejected_by' => 'INTEGER NULL',
                ]
                : [
                    'result_status' => 'ENUM("kabul", "sartli_kabul", "ret") NOT NULL DEFAULT "kabul"',
                    'conditional_reason' => 'VARCHAR(120) NULL',
                    'conditional_note' => 'TEXT NULL',
                    'rejection_reason' => 'VARCHAR(120) NULL',
                    'rejection_note' => 'TEXT NULL',
                    'rejected_at' => 'TIMESTAMP NULL',
                    'rejected_by' => 'BIGINT UNSIGNED NULL',
                ];
            foreach ($definitions as $column => $definition) {
                if (! in_array($column, $columns, true)) {
                    $database->exec('ALTER TABLE sample_analysis ADD COLUMN ' . $column . ' ' . $definition);
                }
            }
            if ($driver === 'sqlite') {
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
                $outboundColumns = array_column($database->query('PRAGMA table_info(outbound_loadings)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                if (! in_array('operation_type', $outboundColumns, true)) {
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
        } catch (Throwable) {
        }
    }

    private function dailyProductEntries(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'dn.created_at', 'dn');

        return $this->fetch(
            'SELECT dn.created_at AS entry_date, dn.notification_number, COALESCE(v.plate_number, "-") AS plate_number,
                    CASE WHEN dn.sender_type = "person" THEN "Şahıs Ürünü" ELSE "Firma Ürünü" END AS sender_type_label,
                    CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE c.name END AS sender_name,
                    p.name AS product_name,
                    ROUND(COALESCE(dn.expected_quantity_kg, 0) / 1000, 2) AS expected_ton,
                    dn.status
             FROM delivery_notifications dn
             INNER JOIN companies c ON c.id = dn.company_id
             INNER JOIN products p ON p.id = dn.product_id
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY dn.created_at DESC, dn.id DESC',
            $params
        );
    }

    private function dailyVehicles(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'dn.created_at', 'dn');

        return $this->fetch(
            'SELECT DATE(dn.created_at) AS report_date, COUNT(*) AS vehicle_count
             FROM delivery_notifications dn
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY DATE(dn.created_at)
             ORDER BY report_date DESC',
            $params
        );
    }

    private function companyProductEntries(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'dn.created_at', 'dn');

        return $this->fetch(
            'SELECT
                CASE WHEN dn.sender_type = "person" THEN "Şahıs Ürünü" ELSE "Firma Ürünü" END AS sender_type_label,
                CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE c.name END AS sender_name,
                p.name AS product_name,
                COUNT(*) AS vehicle_count,
                ROUND(COALESCE(SUM(dn.expected_quantity_kg), 0) / 1000, 2) AS expected_ton,
                ROUND(COALESCE(SUM(wr.net_weight_kg), 0), 0) AS net_kg,
                ROUND(COALESCE(SUM(wr.net_weight_kg), 0) / 1000, 2) AS net_ton
             FROM delivery_notifications dn
             INNER JOIN companies c ON c.id = dn.company_id
             INNER JOIN products p ON p.id = dn.product_id
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY sender_type_label, sender_name, p.name
             ORDER BY sender_name ASC, p.name ASC',
            $params
        );
    }

    private function productNetTotals(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'dn.created_at', 'dn');

        return $this->fetch(
            'SELECT p.name AS product_name,
                    COUNT(*) AS record_count,
                    ROUND(COALESCE(SUM(wr.net_weight_kg), 0), 0) AS net_kg,
                    ROUND(COALESCE(SUM(wr.net_weight_kg), 0) / 1000, 2) AS net_ton
             FROM delivery_notifications dn
             INNER JOIN products p ON p.id = dn.product_id
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY p.name
             ORDER BY record_count DESC, net_ton DESC',
            $params
        );
    }

    private function activeOperations(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'dn.updated_at', 'dn');
        $statusPlaceholders = [];
        foreach (self::ACTIVE_STATUSES as $index => $status) {
            $key = 'active_status_' . $index;
            $statusPlaceholders[] = ':' . $key;
            $params[$key] = $status;
        }
        $where[] = 'dn.status IN (' . implode(', ', $statusPlaceholders) . ')';

        return $this->fetch(
            'SELECT dn.updated_at, dn.notification_number, COALESCE(v.plate_number, "-") AS plate_number,
                    CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE c.name END AS sender_name,
                    p.name AS product_name, dn.status,
                    ROUND(COALESCE(wr.first_weight_kg, 0), 0) AS first_weight_kg,
                    ROUND(COALESCE(wr.second_weight_kg, 0), 0) AS second_weight_kg
             FROM delivery_notifications dn
             INNER JOIN companies c ON c.id = dn.company_id
             INNER JOIN products p ON p.id = dn.product_id
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY dn.updated_at DESC, dn.id DESC',
            $params
        );
    }

    private function siloOccupancy(array $filters): array
    {
        $params = [];
        $where = ['1 = 1'];

        if ((int) $filters['product_id'] > 0) {
            $where[] = 's.product_id = :silo_product_id';
            $params['silo_product_id'] = (int) $filters['product_id'];
        }

        if ((int) $filters['silo_id'] > 0) {
            $where[] = 's.id = :silo_id';
            $params['silo_id'] = (int) $filters['silo_id'];
        }

        return $this->fetch(
            'SELECT s.code AS silo_code, s.name AS silo_name, COALESCE(p.name, "-") AS product_name,
                    ROUND(s.capacity_kg / 1000, 2) AS capacity_ton,
                    ROUND(s.current_stock_kg / 1000, 2) AS current_stock_ton,
                    CASE WHEN s.capacity_kg > 0 THEN ROUND((s.current_stock_kg / s.capacity_kg) * 100, 2) ELSE 0 END AS occupancy_percent
             FROM silos s
             LEFT JOIN products p ON p.id = s.product_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY s.code ASC',
            $params
        );
    }

    private function analysisResults(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'sa.analyzed_at', 'dn');
        $where[] = 'sa.analyzed_at IS NOT NULL';

        return $this->fetch(
            'SELECT sa.analyzed_at AS analysis_date, v.plate_number,
                    CASE WHEN dn.sender_type = "person" THEN "Şahıs Ürünü" ELSE "Firma Ürünü" END AS sender_type_label,
                    CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE c.name END AS sender_name,
                    p.name AS product_name, sa.moisture, sa.protein, sa.hectoliter, sa.gluten,
                    sa.sunn_pest_rate, sa.foreign_material, sa.broken_grain, sa.result,
                    dn.status
             FROM sample_analysis sa
             INNER JOIN weighbridge_records wr ON wr.id = sa.weighbridge_record_id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY sa.analyzed_at DESC, sa.id DESC',
            $params
        );
    }

    private function completedOperations(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'wr.second_weighed_at', 'dn');
        $where[] = 'wr.second_weighed_at IS NOT NULL';

        return $this->fetch(
            'SELECT wr.second_weighed_at AS completed_at, v.plate_number,
                    CASE WHEN dn.sender_type = "person" THEN "Şahıs Ürünü" ELSE "Firma Ürünü" END AS sender_type_label,
                    CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE c.name END AS sender_name,
                    p.name AS product_name, COALESCE(s.code, "-") AS silo_code,
                    ROUND(COALESCE(wr.net_weight_kg, 0), 0) AS net_kg,
                    ROUND(COALESCE(wr.net_weight_kg, 0) / 1000, 2) AS net_ton
             FROM weighbridge_records wr
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             LEFT JOIN silos s ON s.id = wr.assigned_silo_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY wr.second_weighed_at DESC',
            $params
        );
    }

    private function cancelledRejected(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'COALESCE(sa.rejected_at, sa.analyzed_at, dn.updated_at)', 'dn');
        $where[] = '(dn.status IN ("cancelled", "iptal", "ret", "alıma_girmedi") OR sa.result = "rejected" OR sa.result_status = "ret")';

        return $this->fetch(
            'SELECT COALESCE(sa.rejected_at, sa.analyzed_at, dn.updated_at) AS report_date, COALESCE(v.plate_number, "-") AS plate_number,
                    CASE WHEN dn.sender_type = "person" THEN "Şahıs Ürünü" ELSE "Firma Ürünü" END AS sender_type_label,
                    CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE c.name END AS sender_name,
                    p.name AS product_name, dn.status, COALESCE(sa.result, "-") AS analysis_result,
                    ' . self::REJECTION_REASON_SQL . ' AS rejection_reason
             FROM delivery_notifications dn
             INNER JOIN companies c ON c.id = dn.company_id
             INNER JOIN products p ON p.id = dn.product_id
             LEFT JOIN vehicles v ON v.id = dn.vehicle_id
             LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             LEFT JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY report_date DESC',
            $params
        );
    }

    private function rejectedProducts(array $filters): array
    {
        $params = [];
        $where = $this->baseWhere($filters, $params, 'COALESCE(sa.rejected_at, sa.analyzed_at, dn.updated_at)', 'dn');
        $where[] = '(dn.status IN ("ret", "alıma_girmedi") OR sa.result = "rejected" OR sa.result_status = "ret")';

        return $this->fetch(
            'SELECT COALESCE(sa.rejected_at, sa.analyzed_at, dn.updated_at) AS report_date,
                    COALESCE(v.plate_number, "-") AS plate_number,
                    CASE WHEN dn.sender_type = "person" THEN "Şahıs Ürünü" ELSE "Firma Ürünü" END AS sender_type_label,
                    CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE c.name END AS sender_name,
                    p.name AS product_name,
                    ' . self::REJECTION_REASON_SQL . ' AS rejection_reason,
                    COALESCE(sa.rejection_note, sa.notes, "-") AS rejection_note
             FROM sample_analysis sa
             INNER JOIN weighbridge_records wr ON wr.id = sa.weighbridge_record_id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY report_date DESC, v.plate_number ASC',
            $params
        );
    }

    private function dailyProductOutputs(array $filters): array
    {
        $params = [];
        $where = $this->outboundWhere($filters, $params, 'COALESCE(ol.completed_at, ol.updated_at, ol.created_at)');

        return $this->fetch(
            'SELECT COALESCE(ol.completed_at, ol.updated_at, ol.created_at) AS report_date,
                    ol.operation_number, ol.plate_number,
                    CASE WHEN ol.sender_type = "person" THEN "Şahıs Ürünü" ELSE "Firma Ürünü" END AS sender_type_label,
                    CASE WHEN ol.sender_type = "person" THEN COALESCE(ol.sender_name, "-") ELSE COALESCE(c.name, "-") END AS sender_name,
                    p.name AS product_name, COALESCE(s.code, "-") AS silo_code,
                    ROUND(COALESCE(ol.planned_quantity_kg, 0) / 1000, 2) AS planned_ton,
                    ROUND(COALESCE(ol.net_quantity_kg, 0), 0) AS net_kg,
                    ol.status
             FROM outbound_loadings ol
             LEFT JOIN companies c ON c.id = ol.company_id
             INNER JOIN products p ON p.id = ol.product_id
             INNER JOIN silos s ON s.id = ol.source_silo_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY report_date DESC, ol.id DESC',
            $params
        );
    }

    private function companyProductOutputs(array $filters): array
    {
        $params = [];
        $where = $this->outboundWhere($filters, $params, 'COALESCE(ol.completed_at, ol.updated_at, ol.created_at)');

        return $this->fetch(
            'SELECT
                CASE WHEN ol.sender_type = "person" THEN "Şahıs Ürünü" ELSE "Firma Ürünü" END AS sender_type_label,
                CASE WHEN ol.sender_type = "person" THEN COALESCE(ol.sender_name, "-") ELSE COALESCE(c.name, "-") END AS sender_name,
                p.name AS product_name,
                COUNT(*) AS vehicle_count,
                ROUND(COALESCE(SUM(ol.planned_quantity_kg), 0) / 1000, 2) AS planned_ton,
                ROUND(COALESCE(SUM(ol.net_quantity_kg), 0), 0) AS net_kg,
                ROUND(COALESCE(SUM(ol.net_quantity_kg), 0) / 1000, 2) AS net_ton
             FROM outbound_loadings ol
             LEFT JOIN companies c ON c.id = ol.company_id
             INNER JOIN products p ON p.id = ol.product_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY sender_type_label, sender_name, p.name
             ORDER BY sender_name ASC, p.name ASC',
            $params
        );
    }

    private function siloInOut(array $filters): array
    {
        $params = [];
        $where = $this->stockMovementWhere($filters, $params, 'ssm.created_at');

        return $this->fetch(
            'SELECT s.code AS silo_code, s.name AS silo_name, COALESCE(p.name, "-") AS product_name,
                    ROUND(COALESCE(SUM(CASE WHEN ssm.movement_type = "IN" THEN ssm.quantity_kg ELSE 0 END), 0), 0) AS in_kg,
                    ROUND(COALESCE(SUM(CASE WHEN ssm.movement_type = "OUT" THEN ssm.quantity_kg ELSE 0 END), 0), 0) AS out_kg,
                    ROUND(COALESCE(SUM(CASE WHEN ssm.movement_type = "IN" THEN ssm.quantity_kg ELSE -ssm.quantity_kg END), 0), 0) AS net_change_kg
             FROM silo_stock_movements ssm
             INNER JOIN silos s ON s.id = ssm.silo_id
             LEFT JOIN products p ON p.id = ssm.product_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY s.id, s.code, s.name, p.name
             ORDER BY s.code ASC, p.name ASC',
            $params
        );
    }

    private function productStockMovements(array $filters): array
    {
        $params = [];
        $where = $this->stockMovementWhere($filters, $params, 'ssm.created_at');

        return $this->fetch(
            'SELECT COALESCE(p.name, "-") AS product_name,
                    CASE WHEN ssm.movement_type = "OUT" THEN "Çıkış" ELSE "Giriş" END AS movement_type_label,
                    COUNT(*) AS record_count,
                    ROUND(COALESCE(SUM(ssm.quantity_kg), 0), 0) AS total_kg,
                    ROUND(COALESCE(SUM(ssm.quantity_kg), 0) / 1000, 2) AS total_ton
             FROM silo_stock_movements ssm
             LEFT JOIN products p ON p.id = ssm.product_id
             INNER JOIN silos s ON s.id = ssm.silo_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY p.name, ssm.movement_type
             ORDER BY p.name ASC, ssm.movement_type ASC',
            $params
        );
    }

    private function netSiloStock(array $filters): array
    {
        $params = [];
        $where = ['1 = 1'];

        if ((int) $filters['product_id'] > 0) {
            $where[] = 's.product_id = :net_product_id';
            $params['net_product_id'] = (int) $filters['product_id'];
        }

        if ((int) $filters['silo_id'] > 0) {
            $where[] = 's.id = :net_silo_id';
            $params['net_silo_id'] = (int) $filters['silo_id'];
        }

        return $this->fetch(
            'SELECT s.code AS silo_code, s.name AS silo_name, COALESCE(p.name, "-") AS product_name,
                    ROUND(COALESCE(s.current_stock_kg, 0), 0) AS current_stock_kg,
                    ROUND(COALESCE(s.current_stock_kg, 0) / 1000, 2) AS current_stock_ton,
                    ROUND(COALESCE(s.capacity_kg, 0) / 1000, 2) AS capacity_ton,
                    CASE WHEN s.capacity_kg > 0 THEN ROUND((s.current_stock_kg / s.capacity_kg) * 100, 2) ELSE 0 END AS occupancy_percent
             FROM silos s
             LEFT JOIN products p ON p.id = s.product_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY s.code ASC',
            $params
        );
    }

    private function baseWhere(array $filters, array &$params, string $dateExpression, string $notificationAlias): array
    {
        $range = $this->dateRange($filters);
        $where = [
            $dateExpression . ' >= :date_start',
            $dateExpression . ' <= :date_end',
        ];
        $params['date_start'] = $range['startSql'];
        $params['date_end'] = $range['endSql'];

        if ((int) $filters['company_id'] > 0) {
            $where[] = $notificationAlias . '.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        if ((int) $filters['product_id'] > 0) {
            $where[] = $notificationAlias . '.product_id = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }

        if ($filters['status'] !== '') {
            $where[] = $notificationAlias . '.status = :status';
            $params['status'] = $filters['status'];
        }

        if ((int) $filters['silo_id'] > 0) {
            $where[] = 'wr.assigned_silo_id = :assigned_silo_id';
            $params['assigned_silo_id'] = (int) $filters['silo_id'];
        }

        return $where;
    }

    private function outboundWhere(array $filters, array &$params, string $dateExpression): array
    {
        $range = $this->dateRange($filters);
        $where = [
            $dateExpression . ' >= :out_date_start',
            $dateExpression . ' <= :out_date_end',
        ];
        $params['out_date_start'] = $range['startSql'];
        $params['out_date_end'] = $range['endSql'];

        if ((int) $filters['company_id'] > 0) {
            $where[] = 'ol.company_id = :out_company_id';
            $params['out_company_id'] = (int) $filters['company_id'];
        }

        if ((int) $filters['product_id'] > 0) {
            $where[] = 'ol.product_id = :out_product_id';
            $params['out_product_id'] = (int) $filters['product_id'];
        }

        if ($filters['status'] !== '') {
            $where[] = 'ol.status = :out_status';
            $params['out_status'] = $filters['status'];
        }

        if ((int) $filters['silo_id'] > 0) {
            $where[] = 'ol.source_silo_id = :out_silo_id';
            $params['out_silo_id'] = (int) $filters['silo_id'];
        }

        return $where;
    }

    private function stockMovementWhere(array $filters, array &$params, string $dateExpression): array
    {
        $range = $this->dateRange($filters);
        $where = [
            $dateExpression . ' >= :movement_date_start',
            $dateExpression . ' <= :movement_date_end',
        ];
        $params['movement_date_start'] = $range['startSql'];
        $params['movement_date_end'] = $range['endSql'];

        if ((int) $filters['product_id'] > 0) {
            $where[] = 'ssm.product_id = :movement_product_id';
            $params['movement_product_id'] = (int) $filters['product_id'];
        }

        if ((int) $filters['silo_id'] > 0) {
            $where[] = 'ssm.silo_id = :movement_silo_id';
            $params['movement_silo_id'] = (int) $filters['silo_id'];
        }

        return $where;
    }

    private function metadata(array $filters): array
    {
        $range = $this->dateRange($filters);

        return [
            'dateRange' => [
                'start' => $range['startIso'],
                'end' => $range['endIso'],
            ],
            'appliedFilters' => [
                'companyId' => (int) $filters['company_id'] > 0 ? (int) $filters['company_id'] : null,
                'personId' => null,
                'productType' => (int) $filters['product_id'] > 0 ? (int) $filters['product_id'] : null,
                'siloId' => (int) $filters['silo_id'] > 0 ? (int) $filters['silo_id'] : null,
                'status' => $filters['status'] !== '' ? $filters['status'] : null,
            ],
        ];
    }

    private function dateRange(array $filters): array
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $filters['date_from'] . ' 00:00:00', $this->timezone)
            ?: new DateTimeImmutable('today', $this->timezone);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $filters['date_to'] . ' 23:59:59', $this->timezone)
            ?: $start->setTime(23, 59, 59);

        if ($end < $start) {
            $end = $start->setTime(23, 59, 59);
        }

        return [
            'startSql' => $start->format('Y-m-d H:i:s'),
            'endSql' => $end->format('Y-m-d H:i:s'),
            'startIso' => $start->format(DATE_ATOM),
            'endIso' => $end->format(DATE_ATOM),
        ];
    }

    private function groupedSummary(array $records): array
    {
        return [
            'byCompany' => $this->groupBy($records, ['sender_name', 'company_name', 'Gönderici']),
            'byProduct' => $this->groupBy($records, ['product_name', 'Ürün']),
            'byStatus' => $this->groupBy($records, ['status', 'delivery_status', 'result']),
        ];
    }

    private function groupBy(array $records, array $keys): array
    {
        $groups = [];
        foreach ($records as $record) {
            $label = '-';
            foreach ($keys as $key) {
                if (isset($record[$key]) && (string) $record[$key] !== '') {
                    $label = (string) $record[$key];
                    break;
                }
            }
            $groups[$label] = ($groups[$label] ?? 0) + 1;
        }

        ksort($groups);

        return array_map(static fn (string $label, int $count): array => ['label' => $label, 'count' => $count], array_keys($groups), array_values($groups));
    }

    private function responseForReport(string $key, array $report): array
    {
        return [
            'key' => $key,
            'title' => self::REPORT_TITLES[$key] ?? $key,
            'totalCount' => (int) $report['totalCount'],
            'dateRange' => $report['dateRange'],
            'appliedFilters' => $report['appliedFilters'],
            'records' => $report['records'],
            'groupedSummary' => $report['groupedSummary'],
        ];
    }

    private function validDate(string $value): bool
    {
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private function fetch(string $sql, array $params = []): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
