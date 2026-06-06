<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use App\Services\ProductAcceptanceService;
use App\Services\SiloRoutingService;
use App\Services\VehicleProcessHistory;
use PDO;
use Throwable;

final class SampleAnalysisController extends Controller
{
    public function __construct(
        private readonly SiloRoutingService $routingService = new SiloRoutingService(),
        private readonly ProductAcceptanceService $acceptanceService = new ProductAcceptanceService(),
    ) {
    }

    private const RESULT_OPTIONS = [
        'accepted' => 'Kabul',
        'conditional' => 'Şartlı Kabul',
        'rejected' => 'Ret',
    ];

    private const REJECTION_REASONS = [
        'high_moisture' => 'Rutubet yüksek',
        'high_foreign_matter' => 'Yabancı madde oranı yüksek',
        'high_sunn_pest_rate' => 'Süne oranı yüksek',
        'low_hectoliter' => 'Hektolitre düşük',
        'not_suitable' => 'Ürün alım kriterlerine uygun değil',
        'other' => 'Diğer',
    ];

    private const CONDITIONAL_REASONS = [
        'moisture_limit' => 'Rutubet sınırda',
        'foreign_material_limit' => 'Yabancı madde sınırda',
        'hectoliter_limit' => 'Hektolitre düşük / sınırda',
        'protein_limit' => 'Protein değeri sınırda',
        'quality_discount' => 'Kalite kesintisi ile kabul',
        'manager_approval' => 'Yetkili onayı ile kabul',
        'other' => 'Diğer',
    ];

    private const ANALYSIS_QUEUE_STATUSES = [
        'ilk_tartım_alındı',
        'analiz_bekliyor',
        'analizde',
        'in_analysis',
    ];

    public function index(): void
    {
        $this->ensureRejectionSchema();

        $filters = [
            'date_from' => trim((string) $this->input('date_from', '')),
            'date_to' => trim((string) $this->input('date_to', '')),
            'plate' => trim((string) $this->input('plate', '')),
            'product_id' => trim((string) $this->input('product_id', '')),
            'company_id' => trim((string) $this->input('company_id', '')),
            'sender_name' => trim((string) $this->input('sender_name', '')),
            'result' => trim((string) $this->input('result', '')),
            'silo_id' => trim((string) $this->input('silo_id', '')),
            'status' => trim((string) $this->input('status', '')),
        ];

        $this->view('sample_analysis/index', [
            'title' => 'Numune ve Analiz',
            'records' => $this->analysisQueue(),
            'todayAnalyses' => $this->todayAnalyses(),
            'searchResults' => $this->analysisSearch($filters),
            'filters' => $filters,
            'resultOptions' => self::RESULT_OPTIONS,
            'silos' => $this->silos(),
            'products' => $this->products(),
            'companies' => $this->companies(),
        ]);
    }

    public function edit(): void
    {
        $this->ensureRejectionSchema();

        $record = $this->findQueueRecord((int) $this->input('record_id'));

        if ($record === null) {
            http_response_code(404);
            echo 'Analiz bekleyen kantar kaydı bulunamadı.';
            return;
        }

        if (in_array(($record['delivery_status'] ?? ''), ['ilk_tartım_alındı', 'analiz_bekliyor'], true)) {
            VehicleProcessHistory::changeStatus((int) $record['delivery_notification_id'], 'analizde', 'Analiz ekranına alındı');
            $record['delivery_status'] = 'analizde';
        }

        $validation = $this->consumeValidation();
        $analysis = $this->findAnalysis((int) $record['weighbridge_record_id']) ?? $this->emptyAnalysis($record);
        if ($validation['old'] !== []) {
            $analysis = array_merge($analysis, $validation['old']);
        }

        $this->view('sample_analysis/form', [
            'title' => 'Analiz Kaydı',
            'record' => $record,
            'analysis' => $analysis,
            'resultOptions' => self::RESULT_OPTIONS,
            'rejectionReasons' => self::REJECTION_REASONS,
            'conditionalReasons' => self::CONDITIONAL_REASONS,
            'criteria' => $this->acceptanceService->activeCriteria((int) $record['product_id']),
            'message' => (string) $this->input('message', ''),
            'validation' => $validation,
        ]);
    }

    public function save(): void
    {
        $this->ensureRejectionSchema();

        $record = $this->findQueueRecord((int) $this->input('weighbridge_record_id'));

        if ($record === null) {
            $this->redirect('/sample-analysis');
        }

        $analysis = $this->findAnalysis((int) $record['weighbridge_record_id']);
        $payload = $this->payload($record);

        $errors = $this->analysisValidationErrors($payload);

        if ($errors !== []) {
            $this->redirectWithValidation('/sample-analysis/edit?record_id=' . (int) $record['weighbridge_record_id'] . '&message=invalid_values', $errors);
        }

        $acceptance = $this->acceptanceService->evaluate((int) $record['product_id'], $payload);
        $payload['acceptance_status'] = $acceptance['status'];
        $payload['acceptance_criteria_id'] = $acceptance['criteria_id'];
        $payload['result_status'] = $this->resultStatus($payload['result']);
        $payload['rejected_at'] = $payload['result'] === 'rejected' ? date('Y-m-d H:i:s') : null;
        $payload['rejected_by'] = $payload['result'] === 'rejected' ? (Auth::user()['id'] ?? null) : null;

        if ($analysis === null) {
            $payload['analysis_number'] = $this->analysisNumber();
            $statement = Database::connection()->prepare(
                'INSERT INTO sample_analysis
                    (analysis_number, weighbridge_record_id, product_id, moisture, protein, hectoliter,
                     gluten, sunn_pest_rate, foreign_material, broken_grain, result, result_status,
                     conditional_reason, conditional_note, rejection_reason, rejection_note, rejected_at, rejected_by, acceptance_status,
                     acceptance_criteria_id, status, analyzed_at, notes, analyzed_by)
                 VALUES
                    (:analysis_number, :weighbridge_record_id, :product_id, :moisture, :protein, :hectoliter,
                     :gluten, :sunn_pest_rate, :foreign_material, :broken_grain, :result, :result_status,
                     :conditional_reason, :conditional_note, :rejection_reason, :rejection_note, :rejected_at, :rejected_by, :acceptance_status,
                     :acceptance_criteria_id, "completed", NOW(), :notes, :analyzed_by)'
            );
            $payload['analyzed_by'] = Auth::user()['id'] ?? null;
            $statement->execute($payload);
            $analysisId = (int) Database::connection()->lastInsertId();
        } else {
            $payload['id'] = (int) $analysis['id'];
            $statement = Database::connection()->prepare(
                'UPDATE sample_analysis
                 SET moisture = :moisture,
                     protein = :protein,
                     hectoliter = :hectoliter,
                     gluten = :gluten,
                     sunn_pest_rate = :sunn_pest_rate,
                     foreign_material = :foreign_material,
                     broken_grain = :broken_grain,
                     result = :result,
                     result_status = :result_status,
                     conditional_reason = :conditional_reason,
                     conditional_note = :conditional_note,
                     rejection_reason = :rejection_reason,
                     rejection_note = :rejection_note,
                     rejected_at = :rejected_at,
                     rejected_by = :rejected_by,
                     acceptance_status = :acceptance_status,
                     acceptance_criteria_id = :acceptance_criteria_id,
                     status = "completed",
                     analyzed_at = NOW(),
                     notes = :notes
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $payload['id'],
                'moisture' => $payload['moisture'],
                'protein' => $payload['protein'],
                'hectoliter' => $payload['hectoliter'],
                'gluten' => $payload['gluten'],
                'sunn_pest_rate' => $payload['sunn_pest_rate'],
                'foreign_material' => $payload['foreign_material'],
                'broken_grain' => $payload['broken_grain'],
                'result' => $payload['result'],
                'result_status' => $payload['result_status'],
                'conditional_reason' => $payload['conditional_reason'],
                'conditional_note' => $payload['conditional_note'],
                'rejection_reason' => $payload['rejection_reason'],
                'rejection_note' => $payload['rejection_note'],
                'rejected_at' => $payload['rejected_at'],
                'rejected_by' => $payload['rejected_by'],
                'acceptance_status' => $payload['acceptance_status'],
                'acceptance_criteria_id' => $payload['acceptance_criteria_id'],
                'notes' => $payload['notes'],
            ]);
            $analysisId = (int) $analysis['id'];
        }

        if ($payload['result'] === 'rejected') {
            $documentId = $this->createOrUpdateRejectionDocument(
                (int) $record['delivery_notification_id'],
                $analysisId,
                (string) $payload['rejection_reason'],
                $payload['rejection_note']
            );
            Database::connection()
                ->prepare('UPDATE weighbridge_records SET assigned_silo_id = NULL, status = "rejected" WHERE id = :id')
                ->execute(['id' => (int) $record['weighbridge_record_id']]);
            VehicleProcessHistory::changeStatus(
                (int) $record['delivery_notification_id'],
                'ret',
                'Araç analiz sonucu reddedildi, operasyon sonlandırıldı.',
                $this->rejectionLabel((string) $payload['rejection_reason'])
            );
            Database::connection()
                ->prepare('UPDATE delivery_notifications SET operation_status = "closed", operation_closed_at = COALESCE(operation_closed_at, NOW()), updated_at = NOW() WHERE id = :id')
                ->execute(['id' => (int) $record['delivery_notification_id']]);
            AuditLogger::log('sample_analysis.rejected', 'sample_analysis', $analysisId, [
                'entry_id' => (int) $record['delivery_notification_id'],
                'document_id' => $documentId,
                'rejection_reason' => $payload['rejection_reason'],
                'rejection_note' => $payload['rejection_note'],
            ]);

            $this->redirect('/sample-analysis/rejection-print?analysis_id=' . $analysisId);
        }

        VehicleProcessHistory::changeStatus((int) $record['delivery_notification_id'], 'analiz_yapıldı', 'Analiz kaydedildi');

        $targetRule = $this->routingService->findTargetSilo($analysisId);

        if ($targetRule !== null) {
            $this->assignSilo(
                (int) $record['weighbridge_record_id'],
                (int) $record['delivery_notification_id'],
                (int) $targetRule['silo_id']
            );

            $this->redirect('/barcode-tickets?message=routed');
        }

        $this->redirect('/barcode-tickets?message=manual_required&record_id=' . (int) $record['weighbridge_record_id']);
    }

    public function manualSilo(): void
    {
        $record = $this->findAnalysisRecord((int) $this->input('weighbridge_record_id'));
        $siloId = (int) $this->input('silo_id');

        if ($record !== null && $siloId > 0) {
            $this->assignSilo(
                (int) $record['weighbridge_record_id'],
                (int) $record['delivery_notification_id'],
                $siloId
            );
        }

        $this->redirect('/barcode-tickets?message=manual_assigned');
    }

    public function rejectionPrint(): void
    {
        $this->ensureRejectionSchema();

        $record = $this->findRejectionPrintable((int) $this->input('analysis_id'), (int) $this->input('record_id'));

        if ($record === null) {
            http_response_code(404);
            echo 'Ret fişi bulunamadı.';
            return;
        }

        if (empty($record['rejection_document_id'])) {
            $this->createOrUpdateRejectionDocument(
                (int) $record['entry_id'],
                (int) $record['id'],
                (string) ($record['rejection_reason'] ?? 'not_suitable'),
                $record['rejection_note'] ?? null
            );
            $record = $this->findRejectionPrintable((int) $this->input('analysis_id'), (int) $this->input('record_id')) ?? $record;
        }

        $this->markRejectionPrinted((int) $record['rejection_document_id']);

        $this->view('sample_analysis/rejection_print', [
            'title' => 'Ürün Alım Ret Tutanağı',
            'record' => $record,
            'rejectionReasons' => self::REJECTION_REASONS,
        ]);
    }

    private function analysisQueue(): array
    {
        $statement = Database::connection()->query(
            'SELECT
                wr.id AS weighbridge_record_id,
                wr.ticket_number,
                wr.first_weight_kg,
                wr.first_weighed_at,
                wr.assigned_silo_id,
                dn.id AS delivery_notification_id,
                dn.expected_quantity_kg,
                c.name AS company_name,
                p.name AS product_name,
                v.plate_number,
                v.driver_name,
                v.driver_phone,
                sa.id AS analysis_id,
                sa.result,
                sa.analyzed_at,
                assigned_silo.code AS assigned_silo_code,
                assigned_silo.name AS assigned_silo_name
             FROM weighbridge_records wr
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             LEFT JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
             LEFT JOIN silos assigned_silo ON assigned_silo.id = wr.assigned_silo_id
             WHERE dn.status IN ("ilk_tartım_alındı", "analiz_bekliyor", "analizde", "in_analysis")
                AND wr.first_weight_kg IS NOT NULL
             ORDER BY wr.first_weighed_at ASC, wr.id ASC
             LIMIT 100'
        );

        return $statement->fetchAll();
    }

    private function todayAnalyses(): array
    {
        return $this->analysisRows('WHERE DATE(sa.analyzed_at) = DATE("now")', []);
    }

    private function analysisSearch(array $filters): array
    {
        $where = [];
        $params = [];

        if ($this->validDate($filters['date_from'])) {
            $where[] = 'DATE(COALESCE(sa.analyzed_at, sa.created_at)) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if ($this->validDate($filters['date_to'])) {
            $where[] = 'DATE(COALESCE(sa.analyzed_at, sa.created_at)) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if ($filters['plate'] !== '') {
            $where[] = 'REPLACE(v.plate_number, " ", "") LIKE :plate';
            $params['plate'] = '%' . strtoupper(preg_replace('/\s+/', '', $filters['plate'])) . '%';
        }

        if ((int) $filters['product_id'] > 0) {
            $where[] = 'wr.product_id = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }

        if ((int) $filters['company_id'] > 0) {
            $where[] = 'wr.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        if ($filters['sender_name'] !== '') {
            $where[] = '(dn.sender_name LIKE :sender_name OR c.name LIKE :sender_name)';
            $params['sender_name'] = '%' . $filters['sender_name'] . '%';
        }

        if ($filters['result'] !== '' && isset(self::RESULT_OPTIONS[$filters['result']])) {
            $where[] = 'sa.result = :result';
            $params['result'] = $filters['result'];
        }

        if ((int) $filters['silo_id'] > 0) {
            $where[] = 'wr.assigned_silo_id = :silo_id';
            $params['silo_id'] = (int) $filters['silo_id'];
        }

        if ($filters['status'] !== '') {
            $where[] = 'dn.status LIKE :status';
            $params['status'] = '%' . $filters['status'] . '%';
        }

        return $this->analysisRows($where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $params);
    }

    private function validDate(string $value): bool
    {
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private function analysisRows(string $where, array $params): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                sa.*,
                wr.id AS weighbridge_record_id,
                wr.ticket_number,
                wr.first_weight_kg,
                dn.id AS entry_id,
                dn.status AS delivery_status,
                dn.sender_type,
                dn.sender_name,
                c.name AS company_name,
                p.name AS product_name,
                v.plate_number,
                v.driver_name,
                rd.id AS rejection_document_id,
                rd.document_no AS rejection_document_no,
                s.code AS silo_code,
                s.name AS silo_name
             FROM sample_analysis sa
             INNER JOIN weighbridge_records wr ON wr.id = sa.weighbridge_record_id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             LEFT JOIN rejection_documents rd ON rd.analysis_id = sa.id
             LEFT JOIN silos s ON s.id = wr.assigned_silo_id
             ' . $where . '
             ORDER BY sa.analyzed_at DESC, sa.id DESC
             LIMIT 200'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function findQueueRecord(int $recordId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                wr.id AS weighbridge_record_id,
                wr.ticket_number,
                wr.company_id,
                wr.product_id,
                wr.vehicle_id,
                wr.delivery_notification_id,
                wr.assigned_silo_id,
                wr.first_weight_kg,
                wr.first_weighed_at,
                dn.expected_quantity_kg,
                dn.sender_type,
                dn.sender_name,
                dn.identity_number,
                dn.dispatch_number,
                dn.status AS delivery_status,
                dn.created_at AS entry_created_at,
                c.name AS company_name,
                p.name AS product_name,
                v.plate_number,
                v.driver_name,
                v.driver_phone
             FROM weighbridge_records wr
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             WHERE wr.id = :id
                AND dn.status IN ("ilk_tartım_alındı", "analiz_bekliyor", "analizde", "in_analysis")
                AND wr.first_weight_kg IS NOT NULL
             LIMIT 1'
        );
        $statement->execute(['id' => $recordId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findAnalysis(int $recordId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM sample_analysis WHERE weighbridge_record_id = :weighbridge_record_id LIMIT 1'
        );
        $statement->execute(['weighbridge_record_id' => $recordId]);
        $analysis = $statement->fetch(PDO::FETCH_ASSOC);

        return $analysis === false ? null : $analysis;
    }

    private function findAnalysisRecord(int $recordId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                wr.id AS weighbridge_record_id,
                wr.delivery_notification_id,
                wr.product_id,
                dn.status AS delivery_status
             FROM weighbridge_records wr
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             WHERE wr.id = :id
                AND dn.status IN ("analiz_yapıldı", "silo_belirlendi", "barkod_bekliyor", "analizde", "analiz_bekliyor")
             LIMIT 1'
        );
        $statement->execute(['id' => $recordId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function assignSilo(int $recordId, int $notificationId, int $siloId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE weighbridge_records
             SET assigned_silo_id = :assigned_silo_id,
                 status = "silo_assigned"
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $recordId,
            'assigned_silo_id' => $siloId,
        ]);

        VehicleProcessHistory::record($notificationId, 'analiz_yapıldı', 'silo_belirlendi', 'Silo belirlendi');
        VehicleProcessHistory::changeStatus($notificationId, 'barkod_bekliyor', 'Barkod bekliyor', 'Barkod basılınca araç siloya yönlendirilecek.');
    }

    private function silos(): array
    {
        return Database::connection()
            ->query('SELECT id, code, name FROM silos WHERE is_active = 1 ORDER BY code ASC')
            ->fetchAll();
    }

    private function products(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
    }

    private function companies(): array
    {
        return Database::connection()
            ->query('SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name ASC')
            ->fetchAll();
    }

    private function payload(array $record): array
    {
        return [
            'weighbridge_record_id' => (int) $record['weighbridge_record_id'],
            'product_id' => (int) $record['product_id'],
            'moisture' => $this->decimalInputOrNull('moisture'),
            'protein' => $this->decimalInputOrNull('protein'),
            'hectoliter' => $this->decimalInputOrNull('hectoliter'),
            'gluten' => $this->decimalInputOrNull('gluten'),
            'sunn_pest_rate' => $this->decimalInputOrNull('sunn_pest_rate'),
            'foreign_material' => $this->decimalInputOrNull('foreign_material'),
            'broken_grain' => $this->decimalInputOrNull('broken_grain'),
            'result' => $this->result(),
            'conditional_reason' => $this->nullableInput('conditional_reason'),
            'conditional_note' => $this->nullableInput('conditional_note'),
            'rejection_reason' => $this->nullableInput('rejection_reason'),
            'rejection_note' => $this->nullableInput('rejection_note'),
            'notes' => $this->nullableInput('notes'),
        ];
    }

    private function analysisValidationErrors(array $payload): array
    {
        $errors = [];

        foreach (['moisture', 'protein', 'gluten', 'sunn_pest_rate', 'foreign_material', 'broken_grain'] as $key) {
            if ($payload[$key] !== null && ((float) $payload[$key] < 0 || (float) $payload[$key] > 100)) {
                $errors[$key] = 'Değer 0 ile 100 arasında olmalıdır.';
            }
        }

        foreach (['moisture' => 'Rutubet', 'hectoliter' => 'Hektolitre', 'foreign_material' => 'Yabancı madde', 'result' => 'Analiz sonucu'] as $required => $label) {
            if ($payload[$required] === null || $payload[$required] === '') {
                $errors[$required] = $label . ' zorunludur.';
            }
        }

        if ($payload['hectoliter'] !== null && ((float) $payload['hectoliter'] < 0 || (float) $payload['hectoliter'] > 100)) {
            $errors['hectoliter'] = 'Hektolitre 0 ile 100 arasında olmalıdır.';
        }

        if ($payload['result'] === 'rejected') {
            if ($payload['rejection_reason'] === null || ! isset(self::REJECTION_REASONS[$payload['rejection_reason']])) {
                $errors['rejection_reason'] = 'Ret sebebi zorunludur.';
            }

            if ($payload['rejection_reason'] === 'other' && $payload['rejection_note'] === null) {
                $errors['rejection_note'] = 'Diğer seçildiğinde açıklama zorunludur.';
            }
        }

        if ($payload['result'] === 'conditional') {
            if ($payload['conditional_reason'] === null || ! isset(self::CONDITIONAL_REASONS[$payload['conditional_reason']])) {
                $errors['conditional_reason'] = 'Şartlı kabul sebebi zorunludur.';
            }

            if ($payload['conditional_reason'] === 'other' && $payload['conditional_note'] === null) {
                $errors['conditional_note'] = 'Diğer seçildiğinde açıklama zorunludur.';
            }
        }

        return $errors;
    }

    private function result(): string
    {
        $result = (string) $this->input('result', 'accepted');

        return isset(self::RESULT_OPTIONS[$result]) ? $result : 'accepted';
    }

    private function resultStatus(string $result): string
    {
        return match ($result) {
            'conditional' => 'sartli_kabul',
            'rejected' => 'ret',
            default => 'kabul',
        };
    }

    private function analysisNumber(): string
    {
        return 'AN-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    }

    private function emptyAnalysis(array $record): array
    {
        return [
            'id' => null,
            'weighbridge_record_id' => $record['weighbridge_record_id'],
            'moisture' => '',
            'protein' => '',
            'hectoliter' => '',
            'gluten' => '',
            'sunn_pest_rate' => '',
            'foreign_material' => '',
            'broken_grain' => '',
            'result' => 'accepted',
            'result_status' => 'kabul',
            'conditional_reason' => '',
            'conditional_note' => '',
            'rejection_reason' => '',
            'rejection_note' => '',
            'acceptance_status' => 'requires_approval',
            'acceptance_criteria_id' => null,
            'notes' => '',
        ];
    }

    private function rejectionLabel(string $reason): string
    {
        return self::REJECTION_REASONS[$reason] ?? $reason;
    }

    private function createOrUpdateRejectionDocument(int $entryId, int $analysisId, string $reason, ?string $note): int
    {
        $existing = Database::connection()
            ->prepare('SELECT id FROM rejection_documents WHERE analysis_id = :analysis_id LIMIT 1');
        $existing->execute(['analysis_id' => $analysisId]);
        $id = $existing->fetchColumn();

        if ($id !== false) {
            Database::connection()
                ->prepare('UPDATE rejection_documents
                           SET rejection_reason = :rejection_reason, rejection_note = :rejection_note, updated_at = NOW()
                           WHERE id = :id')
                ->execute([
                    'id' => (int) $id,
                    'rejection_reason' => $reason,
                    'rejection_note' => $note,
                ]);

            return (int) $id;
        }

        Database::connection()
            ->prepare('INSERT INTO rejection_documents
                       (entry_id, analysis_id, document_no, rejection_reason, rejection_note, created_at, updated_at)
                       VALUES
                       (:entry_id, :analysis_id, :document_no, :rejection_reason, :rejection_note, NOW(), NOW())')
            ->execute([
                'entry_id' => $entryId,
                'analysis_id' => $analysisId,
                'document_no' => $this->rejectionDocumentNo(),
                'rejection_reason' => $reason,
                'rejection_note' => $note,
            ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function rejectionDocumentNo(): string
    {
        return 'RET-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    }

    private function markRejectionPrinted(int $documentId): void
    {
        if ($documentId <= 0) {
            return;
        }

        Database::connection()
            ->prepare('UPDATE rejection_documents SET printed_at = COALESCE(printed_at, NOW()), printed_by = COALESCE(printed_by, :user_id) WHERE id = :id')
            ->execute([
                'id' => $documentId,
                'user_id' => Auth::user()['id'] ?? null,
            ]);
    }

    private function findRejectionPrintable(int $analysisId, int $recordId): ?array
    {
        $where = $analysisId > 0 ? 'sa.id = :id' : 'wr.id = :id';
        $id = $analysisId > 0 ? $analysisId : $recordId;

        if ($id <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT
                sa.*,
                rd.id AS rejection_document_id,
                rd.document_no,
                rd.printed_at,
                wr.id AS weighbridge_record_id,
                wr.ticket_number,
                wr.first_weight_kg,
                dn.id AS entry_id,
                dn.notification_number,
                dn.sender_type,
                dn.sender_name,
                dn.dispatch_number,
                dn.identity_number,
                c.name AS company_name,
                p.name AS product_name,
                v.plate_number,
                v.driver_name,
                u.name AS analyzed_by_name
             FROM sample_analysis sa
             INNER JOIN weighbridge_records wr ON wr.id = sa.weighbridge_record_id
             INNER JOIN delivery_notifications dn ON dn.id = wr.delivery_notification_id
             INNER JOIN companies c ON c.id = wr.company_id
             INNER JOIN products p ON p.id = wr.product_id
             INNER JOIN vehicles v ON v.id = wr.vehicle_id
             LEFT JOIN users u ON u.id = sa.analyzed_by
             LEFT JOIN rejection_documents rd ON rd.analysis_id = sa.id
             WHERE ' . $where . ' AND (sa.result = "rejected" OR sa.result_status = "ret" OR dn.status = "ret")
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function ensureRejectionSchema(): void
    {
        $database = Database::connection();
        $driver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);

        $columns = $driver === 'sqlite'
            ? array_column($database->query('PRAGMA table_info(sample_analysis)')->fetchAll(PDO::FETCH_ASSOC), 'name')
            : array_column($database->query('SHOW COLUMNS FROM sample_analysis')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $notificationColumns = $driver === 'sqlite'
            ? array_column($database->query('PRAGMA table_info(delivery_notifications)')->fetchAll(PDO::FETCH_ASSOC), 'name')
            : array_column($database->query('SHOW COLUMNS FROM delivery_notifications')->fetchAll(PDO::FETCH_ASSOC), 'Field');

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
                try {
                    $database->exec('ALTER TABLE sample_analysis ADD COLUMN ' . $column . ' ' . $definition);
                } catch (Throwable) {
                }
            }
        }

        $notificationDefinitions = $driver === 'sqlite'
            ? [
                'operation_status' => 'TEXT NOT NULL DEFAULT "open"',
                'operation_closed_at' => 'TEXT NULL',
            ]
            : [
                'operation_status' => 'VARCHAR(40) NOT NULL DEFAULT "open"',
                'operation_closed_at' => 'TIMESTAMP NULL',
            ];

        foreach ($notificationDefinitions as $column => $definition) {
            if (! in_array($column, $notificationColumns, true)) {
                try {
                    $database->exec('ALTER TABLE delivery_notifications ADD COLUMN ' . $column . ' ' . $definition);
                } catch (Throwable) {
                }
            }
        }

        $database->exec(
            'UPDATE sample_analysis
             SET result_status = "ret",
                 rejection_reason = COALESCE(NULLIF(rejection_reason, ""), "not_suitable"),
                 rejected_at = COALESCE(rejected_at, analyzed_at, NOW())
             WHERE result = "rejected" OR result_status = "ret"'
        );
        $database->exec(
            'UPDATE weighbridge_records
             SET assigned_silo_id = NULL, status = "rejected"
             WHERE id IN (
                SELECT weighbridge_record_id
                FROM sample_analysis
                WHERE result = "rejected" OR result_status = "ret"
             )'
        );
        $database->exec(
            'UPDATE delivery_notifications
             SET status = "ret",
                 operation_status = "closed",
                 operation_closed_at = COALESCE(operation_closed_at, NOW()),
                 updated_at = NOW()
             WHERE id IN (
                SELECT wr.delivery_notification_id
                FROM weighbridge_records wr
                INNER JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
                WHERE sa.result = "rejected" OR sa.result_status = "ret"
             )'
        );
        $database->exec(
            'UPDATE barcode_tickets
             SET status = "cancelled", updated_at = NOW()
             WHERE sample_analysis_id IN (
                SELECT id
                FROM sample_analysis
                WHERE result = "rejected" OR result_status = "ret"
             )'
        );
        $database->exec(
            $driver === 'sqlite'
                ? 'CREATE TABLE IF NOT EXISTS vehicle_process_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entry_id INTEGER NOT NULL,
                    old_status TEXT NULL,
                    new_status TEXT NOT NULL,
                    action_name TEXT NOT NULL,
                    description TEXT NULL,
                    user_id INTEGER NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )'
                : 'CREATE TABLE IF NOT EXISTS vehicle_process_history (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    entry_id BIGINT UNSIGNED NOT NULL,
                    old_status VARCHAR(80) NULL,
                    new_status VARCHAR(80) NOT NULL,
                    action_name VARCHAR(160) NOT NULL,
                    description TEXT NULL,
                    user_id BIGINT UNSIGNED NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX vehicle_process_history_entry_id_index (entry_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $database->exec(
            'INSERT INTO vehicle_process_history (entry_id, old_status, new_status, action_name, description, user_id, created_at)
             SELECT dn.id, NULL, "ret", "Araç analiz sonucu reddedildi, operasyon sonlandırıldı.", COALESCE(sa.rejection_reason, "not_suitable"), NULL, NOW()
             FROM delivery_notifications dn
             INNER JOIN weighbridge_records wr ON wr.delivery_notification_id = dn.id
             INNER JOIN sample_analysis sa ON sa.weighbridge_record_id = wr.id
             WHERE dn.status = "ret"
                AND (sa.result = "rejected" OR sa.result_status = "ret")
                AND NOT EXISTS (
                    SELECT 1
                    FROM vehicle_process_history vph
                    WHERE vph.entry_id = dn.id
                       AND vph.new_status = "ret"
                       AND vph.action_name = "Araç analiz sonucu reddedildi, operasyon sonlandırıldı."
                )'
        );

        $database->exec(
            $driver === 'sqlite'
                ? 'CREATE TABLE IF NOT EXISTS rejection_documents (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entry_id INTEGER NOT NULL,
                    analysis_id INTEGER NOT NULL,
                    document_no TEXT NOT NULL UNIQUE,
                    rejection_reason TEXT NOT NULL,
                    rejection_note TEXT NULL,
                    printed_at TEXT NULL,
                    printed_by INTEGER NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )'
                : 'CREATE TABLE IF NOT EXISTS rejection_documents (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    entry_id BIGINT UNSIGNED NOT NULL,
                    analysis_id BIGINT UNSIGNED NOT NULL,
                    document_no VARCHAR(80) NOT NULL UNIQUE,
                    rejection_reason VARCHAR(120) NOT NULL,
                    rejection_note TEXT NULL,
                    printed_at TIMESTAMP NULL,
                    printed_by BIGINT UNSIGNED NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
