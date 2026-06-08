<?php

declare(strict_types=1);

$formatTon = static fn (float|int $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$totalFillPercent = (float) $summary['total_capacity'] > 0
    ? max(0, min(100, (int) round(((float) $summary['total_stock'] / (float) $summary['total_capacity']) * 100)))
    : 0;
$statusLabels = [
    'pending' => 'Beklemede',
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
    'boşaltımda' => 'Boşaltımda',
    'ikinci_tartım_bekliyor' => 'İkinci Tartım Bekliyor',
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
];
$outboundStatusLabels = [
    'OUTBOUND_PRE_NOTIFIED' => 'Çıkış ön bildirimi',
    'OUTBOUND_ARRIVED' => '1. tartım bekliyor',
    'OUTBOUND_FIRST_WEIGHED' => 'Barkod basıldı',
    'OUTBOUND_LOADING_ASSIGNED_TO_SILO' => 'Doluma gönderildi',
    'OUTBOUND_ANALYSIS_PENDING' => 'Analiz bekliyor',
    'OUTBOUND_ANALYSIS_DONE' => 'Analiz tamamlandı',
    'OUTBOUND_SECOND_WEIGHING_WAITING' => '2. tartım bekliyor',
    'OUTBOUND_COMPLETED' => 'Tamamlandı',
    'OUTBOUND_REJECTED' => 'İptal / ret',
];
$outboundProcessCounts = $outboundProcessCounts ?? [];
$outboundAlerts = $outboundAlerts ?? [];
$recentOutboundOperations = $recentOutboundOperations ?? [];
$operationRowClass = static function (array $row): string {
    $status = (string) ($row['status'] ?? '');
    $result = (string) ($row['analysis_result'] ?? '');

    if (in_array($status, ['ret', 'alıma_girmedi'], true) || $result === 'rejected') {
        return 'analysis-state-row analysis-state-row--rejected';
    }

    return match ($result) {
        'accepted' => 'analysis-state-row analysis-state-row--accepted',
        'conditional' => 'analysis-state-row analysis-state-row--conditional',
        default => '',
    };
};
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Depo operasyonlarının hızlı durum özeti.</p>
    </div>
</header>

<section class="dashboard-section-heading dashboard-section-heading--inbound operation-inbound">
    <h2>Ürün Girişi Araç İzleme</h2>
    <p>Ön bildirim, kantar, analiz, silo yönlendirme ve 2. tartım adımlarındaki giriş süreçleri.</p>
</section>

<section class="summary-grid summary-grid--six operation-inbound" aria-label="Giriş operasyon özeti">
    <?php
    $cards = [
        ['Bekleyen araçlar', (int) ($processCounts['waiting'] ?? 0), 'Bildirim aşaması', 'warning', 'waiting'],
        ['Kantara gelen araçlar', (int) ($processCounts['arrived'] ?? 0), 'Kantar bekliyor', 'info', 'arrived'],
        ['1. tartımı alınanlar', (int) ($processCounts['first_weighed'] ?? 0), 'Analize gidecek', 'info', 'first_weighed'],
        ['Analiz bekleyenler', (int) ($processCounts['analysis_waiting'] ?? 0), 'Laboratuvar bekliyor', 'info', 'analysis_waiting'],
        ['Analizi yapılanlar', (int) ($processCounts['analyzed'] ?? 0), 'Barkod bekliyor', 'warning', 'analyzed'],
        ['Siloya yönlendirilenler', (int) ($processCounts['directed'] ?? 0), 'Boşaltım bekliyor', 'success', 'directed'],
        ['Boşaltımda olanlar', (int) ($processCounts['unloading'] ?? 0), 'Silo sahasında', 'warning', 'unloading'],
        ['2. tartım bekleyenler', (int) ($processCounts['second_waiting'] ?? 0), 'Kapanış bekliyor', 'info', 'second_waiting'],
        ['Reddedilen / Alıma girmeyen', (int) ($processCounts['rejected'] ?? 0), 'Ret kayıtları', 'danger', 'rejected'],
        ['Tamamlananlar', (int) ($processCounts['completed'] ?? 0), 'Kapandı', 'default', 'completed'],
    ];
    if ((int) ($processCounts['delayed_notifications'] ?? 0) > 0) {
        array_splice($cards, -1, 0, [[
            'Geciken Ön Bildirimler',
            (int) $processCounts['delayed_notifications'],
            'En eski: ' . (string) ($delayedNotificationSummary['oldest_delay'] ?? 'Belirlenen tarih geçti'),
            'warning',
            'delayed_notifications',
        ]]);
    }
    foreach ($cards as [$label, $value, $meta, $tone, $group]) {
        unset($outboundGroup);
        require BASE_PATH . '/app/Views/components/dashboard_card.php';
    }
    ?>
</section>

<section class="dashboard-section-heading dashboard-section-heading--outbound operation-outbound">
    <h2>Ürün Çıkışı Araç İzleme</h2>
    <p>Boş araç girişi, yükleme alanı ve 2. tartım adımlarındaki çıkış süreçleri.</p>
</section>

<section class="summary-grid summary-grid--six operation-outbound" aria-label="Çıkış operasyon özeti">
    <?php
    $outboundCards = [
        ['Çıkış ön bildirimi bekleyen', (int) ($outboundProcessCounts['waiting'] ?? 0), 'Henüz başlamadı', 'warning', 'waiting'],
        ['1. tartım bekleyenler', (int) ($outboundProcessCounts['first_weigh_waiting'] ?? 0), 'Boş araç tartımı', 'danger', 'first_weigh_waiting'],
        ['Barkod basılan çıkışlar', (int) ($outboundProcessCounts['loading_waiting'] ?? 0), 'Doluma gönderilebilir', 'danger', 'loading_waiting'],
        ['Dolum alanında', (int) ($outboundProcessCounts['loading'] ?? 0), 'Dolum bekliyor', 'danger', 'loading'],
        ['2. tartım bekleyenler', (int) ($outboundProcessCounts['second_waiting'] ?? 0), 'Dolu araç tartımı', 'danger', 'second_waiting'],
        ['Tamamlanan çıkışlar', (int) ($outboundProcessCounts['completed'] ?? 0), 'Kapandı', 'default', 'completed'],
    ];
    foreach ($outboundCards as [$label, $value, $meta, $tone, $outboundGroup]) {
        unset($group);
        require BASE_PATH . '/app/Views/components/dashboard_card.php';
    }
    ?>
</section>

<?php
$dashboardWeightKg = isset($scaleStatus['weight_kg']) && is_numeric($scaleStatus['weight_kg'])
    ? (float) $scaleStatus['weight_kg']
    : null;
?>
<section
    class="dashboard-scale-card"
    data-scale-dashboard
    data-scale-connected="<?= $dashboardWeightKg === null ? '0' : '1' ?>"
    data-current-weight-kg="<?= htmlspecialchars((string) ($dashboardWeightKg ?? '')) ?>"
    aria-label="Online Kantar"
>
    <div class="dashboard-scale-card__header">
        <span class="dashboard-scale-card__dot" aria-hidden="true"></span>
        <h2>Online Kantar</h2>
    </div>
    <div class="dashboard-scale-display" aria-live="polite">
        <?php if ($dashboardWeightKg === null): ?>
            <strong data-scale-weight>Kantar Bağlantısı Yok</strong>
            <small data-scale-ton>-</small>
        <?php else: ?>
            <strong data-scale-weight><?= number_format($dashboardWeightKg, 0, ',', '.') ?> kg</strong>
            <small data-scale-ton><?= number_format($dashboardWeightKg / 1000, 2, ',', '.') ?> ton</small>
        <?php endif; ?>
    </div>
</section>

<script>
(() => {
    const panel = document.querySelector('[data-scale-dashboard]');
    if (!panel) return;

    const weightEl = panel.querySelector('[data-scale-weight]');
    const tonEl = panel.querySelector('[data-scale-ton]');
    const formatterKg = new Intl.NumberFormat('tr-TR', {maximumFractionDigits: 0});
    const formatterTon = new Intl.NumberFormat('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const render = (weightKg) => {
        const numericWeight = Number(weightKg);
        if (!Number.isFinite(numericWeight)) {
            panel.dataset.scaleConnected = '0';
            weightEl.textContent = 'Kantar Bağlantısı Yok';
            tonEl.textContent = '-';
            return;
        }

        panel.dataset.scaleConnected = '1';
        weightEl.textContent = `${formatterKg.format(numericWeight)} kg`;
        tonEl.textContent = `${formatterTon.format(numericWeight / 1000)} ton`;
    };

    const refreshScale = async () => {
        try {
            const response = await fetch('/dashboard/scale-status', {
                headers: {'Accept': 'application/json'},
            });
            if (!response.ok) {
                render(null);
                return;
            }
            const payload = await response.json();
            render(payload.connected ? payload.current_weight_kg : null);
        } catch (error) {
            render(null);
        }
    };

    render(panel.dataset.currentWeightKg);
    setInterval(refreshScale, 3000);
})();
</script>

<?php if ($outboundAlerts !== []): ?>
    <section class="panel operation-panel--outbound">
        <div class="section-heading"><h2>Ürün Çıkışı Durum Uyarıları</h2></div>
        <div class="vehicle-alert-grid">
            <?php foreach ($outboundAlerts as $alertRow): ?>
                <button class="vehicle-alert vehicle-alert--outbound" type="button" data-outbound-id="<?= (int) $alertRow['outbound_id'] ?>">
                    <span><?= htmlspecialchars((string) $alertRow['product_name']) ?></span>
                    <strong><?= htmlspecialchars((string) $alertRow['plate_number']) ?></strong>
                    <small><?= htmlspecialchars($outboundStatusLabels[$alertRow['status']] ?? $alertRow['status']) ?></small>
                    <small>Son işlem: <?= htmlspecialchars((string) $alertRow['updated_at']) ?></small>
                </button>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($vehicleAlerts !== []): ?>
    <section class="panel">
        <div class="section-heading"><h2>Ürün Girişi Durum Uyarıları</h2></div>
        <div class="vehicle-alert-grid">
            <?php foreach ($vehicleAlerts as $alertRow): ?>
                <button class="vehicle-alert <?= htmlspecialchars($operationRowClass($alertRow)) ?>" type="button" data-vehicle-entry-id="<?= (int) $alertRow['entry_id'] ?>">
                    <span><?= htmlspecialchars((string) $alertRow['product_name']) ?></span>
                    <strong><?= htmlspecialchars((string) $alertRow['plate_number']) ?></strong>
                    <small><?= htmlspecialchars($statusLabels[$alertRow['status']] ?? $alertRow['status']) ?></small>
                    <small>Son işlem: <?= htmlspecialchars((string) $alertRow['updated_at']) ?></small>
                </button>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($silos !== []): ?>
    <section class="dashboard-section" aria-label="Silo Dolulukları" data-silo-dashboard data-initial-view="<?= htmlspecialchars($siloView) ?>">
        <div class="section-heading">
            <h2>Silo Dolulukları</h2>
            <div class="section-heading__actions">
                <a class="button button--small" href="/silos">Yönet</a>
            </div>
        </div>
        <div class="dashboard-silo-grid dashboard-silo-grid--mixed" data-silo-grid>
            <?php foreach ($silos as $silo): ?>
                <?php
                $capacity = (float) $silo['capacity_kg'];
                $stock = (float) $silo['current_stock_kg'];
                $free = max(0, $capacity - $stock);
                $fillPercent = $capacity > 0 ? max(0, min(100, (int) round(($stock / $capacity) * 100))) : 0;
                $visualType = ($silo['visual_type'] ?? 'vertical') === 'horizontal' ? 'horizontal' : 'vertical';
                ?>
                <a class="silo-visual-card silo-visual-card--<?= htmlspecialchars($visualType) ?>" href="/silos/edit?id=<?= (int) $silo['id'] ?>" data-silo-card>
                    <div class="silo-visual">
                        <span class="silo-visual__fill" data-fill-percent="<?= $fillPercent ?>" style="<?= $visualType === 'horizontal' ? 'width' : 'height' ?>: <?= $fillPercent ?>%"></span>
                        <div class="silo-visual__content">
                            <span><?= htmlspecialchars($silo['code']) ?></span>
                            <strong><?= htmlspecialchars($silo['name']) ?></strong>
                            <small>Ürün: <?= htmlspecialchars((string) ($silo['product_name'] ?? '-')) ?></small>
                            <b>%<?= $fillPercent ?> Dolu</b>
                            <small>Mevcut: <?= htmlspecialchars($formatTon($stock)) ?></small>
                            <small>Kapasite: <?= htmlspecialchars($formatTon($capacity)) ?></small>
                            <small>Boş kapasite: <?= htmlspecialchars($formatTon($free)) ?></small>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($recentOutboundOperations !== []): ?>
    <section class="panel operation-panel--outbound">
        <div class="section-heading">
            <h2>Son Ürün Çıkışları</h2>
        </div>
        <div class="table-wrap">
            <table class="data-table data-table--compact">
                <thead>
                    <tr>
                        <th>İşlem No</th>
                        <th>Plaka</th>
                        <th>Alıcı</th>
                        <th>Ürün</th>
                        <th>Durum</th>
                        <th>Son Güncelleme</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOutboundOperations as $operation): ?>
                        <tr class="clickable-row operation-row-state--outbound" data-outbound-id="<?= (int) $operation['outbound_id'] ?>">
                            <td><strong><?= htmlspecialchars((string) $operation['operation_number']) ?></strong></td>
                            <td><?= htmlspecialchars((string) $operation['plate_number']) ?></td>
                            <td><?= htmlspecialchars((string) $operation['sender_display']) ?></td>
                            <td><?= htmlspecialchars((string) $operation['product_name']) ?></td>
                            <td><?= htmlspecialchars($outboundStatusLabels[$operation['status']] ?? $operation['status']) ?></td>
                            <td><?= htmlspecialchars((string) $operation['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="section-heading">
        <h2>Son Ürün Girişleri</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead>
                <tr>
                    <th>Bildirim</th>
                    <th>Plaka</th>
                    <th>Gönderici</th>
                    <th>Ürün</th>
                    <th>Durum</th>
                    <th>Son Güncelleme</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentOperations === []): ?>
                    <tr><td colspan="6" class="empty-state">Henüz işlem yok.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentOperations as $operation): ?>
                    <tr class="clickable-row <?= htmlspecialchars($operationRowClass($operation)) ?>" data-vehicle-entry-id="<?= (int) $operation['entry_id'] ?>">
                        <td><strong><?= htmlspecialchars($operation['notification_number']) ?></strong></td>
                        <td><?= htmlspecialchars($operation['plate_number']) ?></td>
                        <td><?= htmlspecialchars($operation['sender_name']) ?></td>
                        <td><?= htmlspecialchars($operation['product_name']) ?></td>
                        <td><?= htmlspecialchars($statusLabels[$operation['status']] ?? $operation['status']) ?></td>
                        <td><?= htmlspecialchars((string) $operation['updated_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
