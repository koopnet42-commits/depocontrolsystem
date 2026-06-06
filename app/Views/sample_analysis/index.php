<?php

declare(strict_types=1);

$resultClass = static function (?string $result): string {
    return match ($result) {
        'accepted' => 'badge--success',
        'conditional' => 'badge--warning',
        'rejected' => 'badge--danger',
        default => 'badge--muted',
    };
};
$message = $_GET['message'] ?? '';
$formatKg = static fn (mixed $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (mixed $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$senderName = static fn (array $row): string => (string) (($row['sender_type'] ?? 'company') === 'person' ? ($row['sender_name'] ?? '-') : ($row['company_name'] ?? '-'));
$conditionalLabels = [
    'moisture_limit' => 'Rutubet sınırda',
    'foreign_material_limit' => 'Yabancı madde sınırda',
    'hectoliter_limit' => 'Hektolitre düşük / sınırda',
    'protein_limit' => 'Protein değeri sınırda',
    'quality_discount' => 'Kalite kesintisi ile kabul',
    'manager_approval' => 'Yetkili onayı ile kabul',
    'other' => 'Diğer',
];
$conditionalReasonText = static fn (array $row): string => (string) ($conditionalLabels[(string) ($row['conditional_reason'] ?? '')] ?? ($row['conditional_reason'] ?? ''));
$hasSearchFilter = array_filter($filters, static fn (mixed $value): bool => trim((string) $value) !== '') !== [];
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Numune ve Analiz</h1>
        <p class="page-subtitle">Analiz bekleyen araçlar, günlük analiz arşivi ve geçmiş arama.</p>
    </div>
</header>

<?php if ($message === 'routed'): ?>
    <div class="alert alert--success">Analiz kaydedildi, uygun silo atandı. Barkod basılınca araç siloya yönlendirilecek.</div>
<?php elseif ($message === 'manual_required'): ?>
    <div class="alert alert--danger">Uygun silo bulunamadı, manuel seçim gerekli.</div>
<?php elseif ($message === 'manual_assigned'): ?>
    <div class="alert alert--success">Manuel silo seçimi kaydedildi. Barkod basılınca araç siloya yönlendirilecek.</div>
<?php endif; ?>

<section class="panel product-workspace">
    <div class="tabs" role="tablist">
        <button class="tab-button<?= $hasSearchFilter ? '' : ' tab-button--active' ?>" data-tab="waiting" type="button">Analiz Bekleyenler</button>
        <button class="tab-button" data-tab="today" type="button">Bugün Analizi Yapılanlar</button>
        <button class="tab-button<?= $hasSearchFilter ? ' tab-button--active' : '' ?>" data-tab="search" type="button">Analiz Arama</button>
    </div>

    <div class="tab-panel<?= $hasSearchFilter ? '' : ' tab-panel--active' ?>" data-panel="waiting">
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead><tr><th>Kantar Fişi</th><th>Plaka</th><th>Gönderici</th><th>Ürün</th><th>1. Tartım</th><th>Şoför</th><th>Analiz / Silo</th><th class="table-actions">İşlem</th></tr></thead>
                <tbody>
                    <?php if ($records === []): ?><tr><td colspan="8" class="empty-state">Analiz bekleyen araç yok.</td></tr><?php endif; ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($record['ticket_number']) ?></strong><span class="table-muted"><?= htmlspecialchars((string) $record['first_weighed_at']) ?></span></td>
                            <td><strong><?= htmlspecialchars($record['plate_number']) ?></strong></td>
                            <td><?= htmlspecialchars($record['company_name']) ?></td>
                            <td><?= htmlspecialchars($record['product_name']) ?></td>
                            <td><?= htmlspecialchars($formatKg($record['first_weight_kg'])) ?><span class="table-muted"><?= htmlspecialchars($formatTon($record['first_weight_kg'])) ?></span></td>
                            <td><?= htmlspecialchars((string) ($record['driver_name'] ?? '-')) ?></td>
                            <td><span class="badge badge--muted"><?= $record['analysis_id'] === null ? 'Bekliyor' : 'Tamamlandı' ?></span></td>
                            <td class="table-actions"><a class="button button--small button--primary" href="/sample-analysis/edit?record_id=<?= (int) $record['weighbridge_record_id'] ?>">Analiz Gir</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tab-panel" data-panel="today">
        <?php $analysisRows = $todayAnalyses; require BASE_PATH . '/app/Views/sample_analysis/partials/analysis_table.php'; ?>
    </div>

    <div class="tab-panel<?= $hasSearchFilter ? ' tab-panel--active' : '' ?>" data-panel="search">
        <form action="/sample-analysis" method="get" class="filter-grid">
            <label class="field"><span>Tarih başlangıç</span><input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>"></label>
            <label class="field"><span>Tarih bitiş</span><input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>"></label>
            <label class="field"><span>Plaka</span><input type="text" name="plate" value="<?= htmlspecialchars($filters['plate']) ?>"></label>
            <label class="field"><span>Ürün</span><select name="product_id"><option value="">Tümü</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= (string) $product['id'] === $filters['product_id'] ? 'selected' : '' ?>><?= htmlspecialchars($product['name']) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Firma</span><select name="company_id"><option value="">Tümü</option><?php foreach ($companies as $company): ?><option value="<?= (int) $company['id'] ?>" <?= (string) $company['id'] === $filters['company_id'] ? 'selected' : '' ?>><?= htmlspecialchars($company['name']) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Şahıs adı</span><input type="text" name="sender_name" value="<?= htmlspecialchars($filters['sender_name']) ?>"></label>
            <label class="field"><span>Analiz sonucu</span><select name="result"><option value="">Tümü</option><?php foreach ($resultOptions as $value => $label): ?><option value="<?= htmlspecialchars($value) ?>" <?= $value === $filters['result'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Silo</span><select name="silo_id"><option value="">Tümü</option><?php foreach ($silos as $silo): ?><option value="<?= (int) $silo['id'] ?>" <?= (string) $silo['id'] === $filters['silo_id'] ? 'selected' : '' ?>><?= htmlspecialchars($silo['code'] . ' - ' . $silo['name']) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Durum</span><input type="text" name="status" value="<?= htmlspecialchars($filters['status']) ?>" placeholder="analizde, analiz_tamamlandı..."></label>
            <div class="filter-actions"><button class="button button--primary" type="submit">Ara</button><a class="button button--ghost" href="/sample-analysis">Temizle</a></div>
        </form>
        <?php $analysisRows = $searchResults; require BASE_PATH . '/app/Views/sample_analysis/partials/analysis_table.php'; ?>
    </div>
</section>

<script>
document.querySelectorAll('.tab-button').forEach((button) => button.addEventListener('click', () => {
    document.querySelectorAll('.tab-button').forEach((item) => item.classList.remove('tab-button--active'));
    document.querySelectorAll('.tab-panel').forEach((item) => item.classList.remove('tab-panel--active'));
    button.classList.add('tab-button--active');
    document.querySelector(`[data-panel="${button.dataset.tab}"]`)?.classList.add('tab-panel--active');
}));
</script>
