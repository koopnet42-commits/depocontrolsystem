<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Services\SettingsService;
use App\Services\OutboundProcessService;
use App\Services\VehicleProcessService;
use App\Services\WeighbridgeScaleService;
use Throwable;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly WeighbridgeScaleService $scale = new WeighbridgeScaleService(),
        private readonly VehicleProcessService $processService = new VehicleProcessService(),
        private readonly OutboundProcessService $outboundProcessService = new OutboundProcessService(),
    ) {
    }

    public function index(): void
    {
        $modules = Auth::filterMenu(require BASE_PATH . '/data/modules.php');
        $system = SettingsService::system();

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'modules' => $modules,
            'silos' => $this->silos(),
            'summary' => $this->summary(),
            'processCounts' => $this->processService->counts(),
            'outboundProcessCounts' => $this->outboundProcessService->counts(),
            'outboundAlerts' => $this->outboundProcessService->alerts(),
            'recentOutboundOperations' => $this->outboundProcessService->recent(8),
            'delayedNotificationSummary' => $this->delayedNotificationSummary(),
            'scaleStatus' => $this->scale->status($this->activePlate()),
            'vehicleAlerts' => $this->vehicleAlerts(),
            'recentOperations' => $this->recentOperations(),
            'siloView' => in_array(($system['dashboard_silo_view'] ?? 'vertical'), ['horizontal', 'vertical'], true)
                ? $system['dashboard_silo_view']
                : 'vertical',
        ]);
    }

    public function scaleStatus(): void
    {
        $status = $this->scale->status($this->activePlate());
        $weightKg = isset($status['weight_kg']) && is_numeric($status['weight_kg'])
            ? (float) $status['weight_kg']
            : null;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'connected' => $weightKg !== null,
            'current_weight_kg' => $weightKg,
            'read_at' => $status['last_read_at'] ?? date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function activePlate(): ?string
    {
        try {
            $plate = Database::connection()
                ->query('SELECT plate_number
                         FROM (
                             SELECT v.plate_number, dn.updated_at AS queue_time
                             FROM delivery_notifications dn
                             INNER JOIN vehicles v ON v.id = dn.vehicle_id
                             WHERE dn.status IN ("giriş_bariyeri_açıldı", "kantarda")
                             UNION ALL
                             SELECT ol.plate_number, ol.updated_at AS queue_time
                             FROM outbound_loadings ol
                             WHERE ol.status IN ("OUTBOUND_ARRIVED", "OUTBOUND_SECOND_WEIGHING_WAITING")
                         ) active_plates
                         ORDER BY queue_time ASC
                         LIMIT 1')
                ->fetchColumn();

            return $plate === false ? null : (string) $plate;
        } catch (Throwable) {
            return null;
        }
    }

    private function vehicleAlerts(): array
    {
        try {
            return Database::connection()
                ->query('SELECT dn.id AS entry_id, dn.status, dn.updated_at, p.name AS product_name, v.plate_number, sa.result AS analysis_result
                         FROM delivery_notifications dn
                         INNER JOIN products p ON p.id = dn.product_id
                         INNER JOIN vehicles v ON v.id = dn.vehicle_id
                         LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
                         LEFT JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
                         WHERE dn.status IN ("kantara_geldi", "giriş_bariyeri_açıldı", "kantarda", "ilk_tartım_alındı", "analiz_bekliyor", "analizde", "analiz_yapıldı", "silo_belirlendi", "barkod_bekliyor", "siloya_yönlendirildi", "boşaltımda", "ikinci_tartım_bekliyor")
                         ORDER BY dn.updated_at ASC
                         LIMIT 12')
                ->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function silos(): array
    {
        try {
            $statement = Database::connection()->query(
                'SELECT s.*, p.name AS product_name
                 FROM silos s
                 LEFT JOIN products p ON p.id = s.product_id
                 ORDER BY s.is_active DESC, s.code ASC
                 LIMIT 24'
            );

            return $statement->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function summary(): array
    {
        $default = [
            'total_capacity' => 0,
            'total_stock' => 0,
            'pending_vehicles' => 0,
            'today_arrivals' => 0,
            'weighbridge_vehicles' => 0,
            'analysis_vehicles' => 0,
            'directed_vehicles' => 0,
            'completed_operations' => 0,
        ];

        try {
            $summary = $default;

            $statement = Database::connection()->query(
                'SELECT
                    COALESCE(SUM(capacity_kg), 0) AS total_capacity,
                    COALESCE(SUM(current_stock_kg), 0) AS total_stock
                 FROM silos'
            );
            $siloTotals = $statement->fetch();
            $summary['total_capacity'] = (float) $siloTotals['total_capacity'];
            $summary['total_stock'] = (float) $siloTotals['total_stock'];

            $summary['pending_vehicles'] = (int) Database::connection()
                ->query('SELECT COUNT(*) FROM delivery_notifications WHERE status IN ("pending", "ürün_bildirimi")')
                ->fetchColumn();

            $summary['today_arrivals'] = (int) Database::connection()
                ->query('SELECT COUNT(*) FROM delivery_notifications
                         WHERE status IN ("kantara_geldi", "giriş_bariyeri_açıldı", "kantarda", "ilk_tartım_alındı", "analiz_bekliyor", "analizde", "analiz_yapıldı", "silo_belirlendi", "barkod_bekliyor", "siloya_yönlendirildi", "boşaltımda", "ikinci_tartım_bekliyor", "tamamlandı")
                            AND DATE(updated_at) = DATE("now")')
                ->fetchColumn();

            $summary['weighbridge_vehicles'] = (int) Database::connection()
                ->query('SELECT COUNT(*) FROM delivery_notifications WHERE status IN ("kantara_geldi", "giriş_bariyeri_açıldı", "kantarda", "at_weighbridge")')
                ->fetchColumn();

            $summary['analysis_vehicles'] = (int) Database::connection()
                ->query('SELECT COUNT(*) FROM delivery_notifications WHERE status IN ("ilk_tartım_alındı", "analiz_bekliyor", "analizde")')
                ->fetchColumn();

            $summary['directed_vehicles'] = (int) Database::connection()
                ->query('SELECT COUNT(*) FROM delivery_notifications WHERE status IN ("siloya_yönlendirildi")')
                ->fetchColumn();

            $summary['completed_operations'] = (int) Database::connection()
                ->query('SELECT COUNT(*) FROM delivery_notifications WHERE status IN ("tamamlandı", "completed") AND DATE(updated_at) = DATE("now")')
                ->fetchColumn();

            return $summary;
        } catch (Throwable) {
            return $default;
        }
    }

    private function recentOperations(): array
    {
        try {
            return Database::connection()->query(
                'SELECT dn.id AS entry_id, dn.notification_number, dn.status, dn.updated_at, p.name AS product_name, sa.result AS analysis_result,
                        COALESCE(v.plate_number, "-") AS plate_number,
                        CASE WHEN dn.sender_type = "person" THEN COALESCE(dn.sender_name, "-") ELSE c.name END AS sender_name
                 FROM delivery_notifications dn
                 INNER JOIN companies c ON c.id = dn.company_id
                 INNER JOIN products p ON p.id = dn.product_id
                 LEFT JOIN vehicles v ON v.id = dn.vehicle_id
                 LEFT JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
                 LEFT JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
                 ORDER BY dn.updated_at DESC, dn.id DESC
                 LIMIT 8'
            )->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function delayedNotificationSummary(): array
    {
        try {
            $oldest = Database::connection()
                ->query('SELECT expected_arrival_date
                         FROM delivery_notifications
                         WHERE entry_type = "pre_notified"
                            AND status IN ("pending", "ürün_bildirimi")
                            AND expected_arrival_date IS NOT NULL
                            AND DATE(expected_arrival_date) < DATE("now")
                         ORDER BY expected_arrival_date ASC
                         LIMIT 1')
                ->fetchColumn();

            if ($oldest === false || $oldest === null || $oldest === '') {
                return ['oldest_delay' => 'Geciken araç yok'];
            }

            $timestamp = strtotime((string) $oldest);
            if ($timestamp === false) {
                return ['oldest_delay' => 'Belirlenen tarih geçti'];
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}($|\s+00:00:00$)/', (string) $oldest) === 1) {
                $timestamp = strtotime(substr((string) $oldest, 0, 10) . ' 23:59:59') ?: $timestamp;
            }

            $seconds = max(0, time() - $timestamp);
            $hours = (int) floor($seconds / 3600);
            if ($hours < 1) {
                return ['oldest_delay' => 'Belirlenen tarih geçti'];
            }

            return ['oldest_delay' => $hours < 24 ? $hours . ' saat gecikti' : (int) floor($hours / 24) . ' gün gecikti'];
        } catch (Throwable) {
            return ['oldest_delay' => 'Gecikme hesaplanamadı'];
        }
    }
}
