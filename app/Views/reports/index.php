<?php

declare(strict_types=1);

$selected = static fn (string $value, string $current): string => $value === $current ? 'selected' : '';
$queryFor = static function (array $filters, string $report): string {
    return http_build_query(array_filter([...$filters, 'report' => $report], static fn ($value): bool => $value !== ''));
};
$reportRowClass = static function (array $row): string {
    $text = implode(' ', array_map(static fn ($value): string => (string) ($value ?? ''), $row));
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

    if (str_contains($haystack, 'rejected') || str_contains($haystack, 'ret') || str_contains($haystack, 'alıma girmedi')) {
        return 'analysis-state-row analysis-state-row--rejected';
    }

    if (str_contains($haystack, 'conditional') || str_contains($haystack, 'şartlı')) {
        return 'analysis-state-row analysis-state-row--conditional';
    }

    if (str_contains($haystack, 'accepted') || str_contains($haystack, 'kabul')) {
        return 'analysis-state-row analysis-state-row--accepted';
    }

    return '';
};
$dateRange = $reportResponse['dateRange'] ?? ['start' => '-', 'end' => '-'];
$appliedFilters = $reportResponse['appliedFilters'] ?? [];
$filterSummary = [
    'Tarih' => ($filters['date_from'] ?? '-') . ' / ' . ($filters['date_to'] ?? '-'),
    'Firma' => $appliedFilters['companyId'] ?? 'Tümü',
    'Ürün' => $appliedFilters['productType'] ?? 'Tümü',
    'Silo' => $appliedFilters['siloId'] ?? 'Tümü',
    'Durum' => $appliedFilters['status'] ?? 'Tümü',
];
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Raporlar</h1>
        <p class="page-subtitle">Depo operasyonlarını tarih, firma, ürün, silo ve duruma göre inceleyin.</p>
    </div>
</header>

<section class="panel filter-panel">
    <form action="/reports" method="get" class="filter-grid filter-grid--reports">
        <label class="field">
            <span>Başlangıç</span>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
        </label>

        <label class="field">
            <span>Bitiş</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
        </label>

        <label class="field">
            <span>Firma</span>
            <select name="company_id">
                <option value="">Tümü</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int) $company['id'] ?>" <?= $selected((string) $company['id'], $filters['company_id']) ?>>
                        <?= htmlspecialchars($company['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Ürün</span>
            <select name="product_id">
                <option value="">Tümü</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>" <?= $selected((string) $product['id'], $filters['product_id']) ?>>
                        <?= htmlspecialchars($product['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Silo</span>
            <select name="silo_id">
                <option value="">Tümü</option>
                <?php foreach ($silos as $silo): ?>
                    <option value="<?= (int) $silo['id'] ?>" <?= $selected((string) $silo['id'], $filters['silo_id']) ?>>
                        <?= htmlspecialchars($silo['code'] . ' - ' . $silo['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Durum</span>
            <select name="status">
                <option value="">Tümü</option>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $selected($value, $filters['status']) ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="filter-actions">
            <button class="button button--primary" type="submit">Filtrele</button>
            <a class="button button--ghost" href="/reports">Temizle</a>
        </div>
    </form>
</section>

<section class="panel report-meta-panel">
    <div>
        <span>Rapor tarih aralığı</span>
        <strong><?= htmlspecialchars((string) $dateRange['start']) ?></strong>
        <strong><?= htmlspecialchars((string) $dateRange['end']) ?></strong>
    </div>
    <?php foreach ($filterSummary as $label => $value): ?>
        <div>
            <span><?= htmlspecialchars($label) ?></span>
            <strong><?= htmlspecialchars((string) $value) ?></strong>
        </div>
    <?php endforeach; ?>
</section>

<div class="report-grid">
    <?php foreach ($reports as $key => $report): ?>
        <section class="panel report-panel">
            <div class="report-panel__header">
                <div>
                    <h2><?= htmlspecialchars($reportTitles[$key] ?? $key) ?></h2>
                    <span class="table-muted">Toplam kayıt: <?= (int) ($report['totalCount'] ?? count($report['rows'])) ?></span>
                </div>
                <div class="report-panel__actions">
                    <a class="button button--small" href="/reports/data?<?= htmlspecialchars($queryFor($filters, $key)) ?>">JSON</a>
                    <a class="button button--small" href="/reports/export?<?= htmlspecialchars($queryFor($filters, $key)) ?>">CSV</a>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table data-table--compact">
                    <thead>
                        <tr>
                            <?php foreach ($report['columns'] as $column): ?>
                                <th><?= htmlspecialchars($column) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($report['rows'] === []): ?>
                            <tr>
                                <td colspan="<?= count($report['columns']) ?>" class="empty-state">Bugün kayıt yok.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($report['rows'] as $row): ?>
                            <tr class="<?= htmlspecialchars($reportRowClass($row)) ?>">
                                <?php foreach ($row as $value): ?>
                                    <td><?= htmlspecialchars((string) ($value ?? '-')) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<section class="panel report-panel">
    <div class="report-panel__header">
        <div>
            <h2>Rapor Veri Kontrolü</h2>
            <span class="table-muted">Eksik bağlantı veya tutarsız tartım bilgisi olan kayıtlar.</span>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead><tr><th>Bildirim No</th><th>Plaka</th><th>Durum</th><th>Kontrol</th></tr></thead>
            <tbody>
                <?php if (($dataQualityIssues ?? []) === []): ?>
                    <tr><td colspan="4" class="empty-state">Veri kontrol hatası yok.</td></tr>
                <?php endif; ?>
                <?php foreach (($dataQualityIssues ?? []) as $issue): ?>
                    <tr class="analysis-state-row analysis-state-row--conditional">
                        <td><?= htmlspecialchars((string) ($issue['notification_number'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($issue['plate_number'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($issue['status'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($issue['issue'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
