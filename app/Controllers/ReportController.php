<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ReportService;

final class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports = new ReportService())
    {
    }

    public function index(): void
    {
        $this->reports->ensureSchema();
        $filters = $this->filters();

        $this->view('reports/index', [
            'title' => 'Raporlar',
            'filters' => $filters,
            'companies' => $this->reports->companies(),
            'products' => $this->reports->products(),
            'silos' => $this->reports->silos(),
            'statusOptions' => ReportService::STATUS_OPTIONS,
            'reportTitles' => ReportService::REPORT_TITLES,
            'reports' => $this->reports->reports($filters),
            'reportResponse' => $this->reports->response($filters),
            'dataQualityIssues' => $this->reports->dataQualityIssues($filters),
        ]);
    }

    public function data(): void
    {
        $this->reports->ensureSchema();
        $filters = $this->filters();
        $report = trim((string) $this->input('report', ''));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->reports->response($filters, $report !== '' ? $report : null), JSON_UNESCAPED_UNICODE);
    }

    public function export(): void
    {
        $this->reports->ensureSchema();
        $filters = $this->filters();
        $report = (string) $this->input('report', 'daily_product_entries');
        $reports = $this->reports->reports($filters);

        if (! isset($reports[$report])) {
            http_response_code(404);
            echo 'Rapor bulunamadı.';
            return;
        }

        $filename = $report . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, $reports[$report]['columns'], ';', '"', '');

        foreach ($reports[$report]['rows'] as $row) {
            fputcsv($output, array_map(static fn ($value): string => (string) ($value ?? ''), array_values($row)), ';', '"', '');
        }

        fclose($output);
    }

    private function filters(): array
    {
        return $this->reports->filters([
            'date_from' => $this->input('date_from', ''),
            'date_to' => $this->input('date_to', ''),
            'company_id' => $this->input('company_id', ''),
            'product_id' => $this->input('product_id', ''),
            'silo_id' => $this->input('silo_id', ''),
            'status' => $this->input('status', ''),
        ]);
    }
}
