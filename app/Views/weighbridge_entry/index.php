<?php

declare(strict_types=1);

$alerts = [
    'not_found' => ['alert--danger', 'Kantar girişine uygun araç bulunamadı.'],
    'plate_invalid' => ['alert--danger', 'Plaka formatı geçersiz. Örnek: 34 ABC 123.'],
    'weighbridge_busy' => ['alert--danger', 'Kantarda işlemde olan araç var. Yeni araç alınamaz.'],
    'barrier_required' => ['alert--danger', 'Önce giriş bariyeri açılmalı ve araç kantara alınmalıdır.'],
    'barrier_opened' => ['alert--success', 'Giriş bariyeri açıldı, aracın kantara çıkması bekleniyor.'],
    'barrier_failed' => ['alert--danger', 'Bariyer simülasyonu başarısız oldu.'],
    'vehicle_on_scale' => ['alert--success', 'Araç kantara giriş yaptı. İlk tartım alanı açıldı.'],
    'weight_required' => ['alert--danger', 'İlk tartım 1.000 kg ile 100.000 kg arasında girilmelidir.'],
    'reason_required' => ['alert--danger', 'Manuel tartım değeri girildiğinde açıklama zorunludur.'],
    'weight_saved' => ['alert--success', 'İlk tartım kaydedildi. Araç analiz için bekleyenlere aktarıldı.'],
    'rollback_done' => ['alert--success', 'Kantar işlemi geri alındı. Araç bekleyenler listesine döndü.'],
    'rollback_forbidden' => ['alert--danger', 'Kantar işlemini geri almak için yetkiniz yok.'],
    'rollback_weight_locked' => ['alert--danger', 'Bu araç için 1. tartım kaydedilmiş. Geri alma işlemi sadece yetkili kullanıcı tarafından yapılabilir.'],
    'rollback_failed' => ['alert--danger', 'Kantar geri alma işlemi tamamlanamadı. Lütfen tekrar deneyin.'],
    'invalid' => ['alert--danger', 'Formdaki hatalı alanları kontrol edin.'],
];
$formatKg = static fn (mixed $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (mixed $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$statusLabels = [
    'pending' => 'Beklemede',
    'kantara_geldi' => 'Kantara geldi',
    'kantara_yonlendirildi' => 'Kantara yönlendirildi',
    'giriş_bariyeri_bekliyor' => 'Giriş bariyeri bekliyor',
    'giriş_bariyeri_açıldı' => 'Giriş bariyeri açıldı',
    'kantarda' => 'Kantarda',
    'ilk_tartım_alındı' => 'İlk tartım alındı',
    'analiz_bekliyor' => 'Analiz bekliyor',
    'analizde' => 'Analizde',
    'analiz_yapıldı' => 'Analiz yapıldı',
    'silo_belirlendi' => 'Silo belirlendi',
    'barkod_bekliyor' => 'Barkod bekliyor',
    'siloya_yönlendirildi' => 'Siloya yönlendirildi',
    'boşaltımda' => 'Boşaltımda',
    'ikinci_tartım_bekliyor' => 'İkinci tartım bekliyor',
    'boşaltıldı' => 'Boşaltıldı',
    'at_weighbridge' => 'Kantara geldi',
    'in_analysis' => 'Analizde',
    'unloaded' => 'Boşaltıldı',
];
$senderName = static fn (array $row): string => (string) (($row['sender_type'] ?? 'company') === 'person' ? ($row['sender_name'] ?? '-') : ($row['company_name'] ?? '-'));
$alert = $alerts[$message] ?? null;
$validation = $validation ?? ['errors' => [], 'old' => [], 'general' => null];
$fieldError = static fn (string $field): string => isset($validation['errors'][$field]) ? '<small class="field-error">' . htmlspecialchars((string) $validation['errors'][$field]) . '</small>' : '';
$fieldClass = static fn (string $field): string => isset($validation['errors'][$field]) ? ' field--error' : '';
$canRollbackWeighbridge = (bool) ($canRollbackWeighbridge ?? false);
$canAdminRollbackWeighbridge = (bool) ($canAdminRollbackWeighbridge ?? false);
$rollbackStatuses = ['kantara_geldi', 'giriş_bariyeri_açıldı', 'kantarda', 'at_weighbridge'];
$canShowRollback = static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), $rollbackStatuses, true);
$hasFirstWeight = static fn (array $row): bool => ($row['first_weight_kg'] ?? null) !== null && ($row['first_weight_kg'] ?? '') !== '';
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Kantar İşlemleri</h1>
        <p class="page-subtitle">Kantara gelen araçları, bariyer simülasyonunu ve ilk tartım sürecini yönetin.</p>
    </div>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel scale-panel">
    <div class="section-heading"><h2>Kantar Durumu</h2><span class="badge badge--info"><?= htmlspecialchars((string) $scaleStatus['mode']) ?></span></div>
    <div class="scale-readout">
        <div><span>Kantar durumu</span><strong><?= htmlspecialchars((string) $scaleStatus['status']) ?></strong></div>
        <div><span>Anlık ağırlık</span><strong><?= number_format((float) $scaleStatus['weight_kg'], 0, ',', '.') ?> kg</strong></div>
        <div><span>Ton karşılığı</span><strong><?= number_format((float) $scaleStatus['weight_ton'], 2, ',', '.') ?> ton</strong></div>
        <div><span>Son okuma</span><strong><?= htmlspecialchars((string) $scaleStatus['last_read_at']) ?></strong></div>
        <div><span>Aktif plaka</span><strong><?= htmlspecialchars((string) ($scaleStatus['active_plate'] ?? '-')) ?></strong></div>
        <div><span>Bariyer</span><strong><?= htmlspecialchars((string) $scaleStatus['barrier_status']) ?></strong></div>
    </div>
</section>

<section class="panel panel--form panel--wide-form">
    <form action="/weighbridge-entry" method="get" class="plate-search">
        <label class="field">
            <span>Plaka ile hızlı ara</span>
            <input type="text" name="plate" value="<?= htmlspecialchars($plate) ?>" placeholder="34 ABC 123">
        </label>
        <button class="button button--primary" type="submit">Plaka Ara</button>
    </form>
</section>

<section class="panel">
    <div class="section-heading"><h2>Araç Kantara Geldi</h2></div>
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead>
                <tr><th>Giriş no</th><th>Plaka</th><th>Gönderici</th><th>Ürün</th><th>Miktar</th><th>Şoför</th><th>Geliş zamanı</th><th>Durum</th><th class="table-actions">İşlemler</th></tr>
            </thead>
            <tbody>
                <?php if ($arrivals === []): ?>
                    <tr><td colspan="9" class="empty-state">Kantara gelen araç yok.</td></tr>
                <?php endif; ?>
                <?php foreach ($arrivals as $row): ?>
                    <tr class="clickable-row" data-vehicle-entry-id="<?= (int) $row['id'] ?>">
                        <td><strong><?= htmlspecialchars((string) $row['notification_number']) ?></strong></td>
                        <td><?= htmlspecialchars((string) $row['plate_number']) ?></td>
                        <td><?= htmlspecialchars($senderName($row)) ?></td>
                        <td><?= htmlspecialchars((string) $row['product_name']) ?></td>
                        <td><?= htmlspecialchars($formatTon($row['expected_quantity_kg'] ?? 0)) ?></td>
                        <td><?= htmlspecialchars((string) ($row['driver_name'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['expected_arrival_date'] ?: $row['updated_at'])) ?></td>
                        <td><span class="badge badge--muted"><?= htmlspecialchars($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                        <td class="table-actions">
                            <a class="button button--small" href="/weighbridge-entry?plate=<?= urlencode((string) $row['plate_number']) ?>">Detay</a>
                            <form action="/weighbridge-entry/open-barrier" method="post" class="inline-form" data-confirm="Giriş bariyerini simülasyon modunda açmak istediğinize emin misiniz?">
                                <input type="hidden" name="notification_id" value="<?= (int) $row['id'] ?>">
                                <button class="button button--small button--primary" type="submit">Girişi Aç</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel detail-panel">
    <div class="detail-header">
        <div>
            <div class="detail-kicker">Kantarda Aktif Araç</div>
            <h2><?= $activeRecord === null ? 'Aktif araç yok' : htmlspecialchars((string) $activeRecord['plate_number']) ?></h2>
        </div>
        <?php if ($activeRecord !== null): ?>
            <span class="badge badge--info"><?= htmlspecialchars($statusLabels[$activeRecord['status']] ?? $activeRecord['status']) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($activeRecord !== null): ?>
        <div class="detail-grid">
            <div><span>Ürün</span><strong><?= htmlspecialchars((string) $activeRecord['product_name']) ?></strong></div>
            <div><span>Gönderici</span><strong><?= htmlspecialchars($senderName($activeRecord)) ?></strong></div>
            <div><span>Bildirilen miktar</span><strong><?= htmlspecialchars($formatTon($activeRecord['expected_quantity_kg'] ?? 0)) ?></strong></div>
            <div><span>Şoför</span><strong><?= htmlspecialchars((string) ($activeRecord['driver_name'] ?? '-')) ?></strong></div>
            <div><span>Araç</span><strong><?= htmlspecialchars((string) ($activeRecord['vehicle_brand'] ?? '-')) ?></strong></div>
            <div><span>Bariyer</span><strong><?= $activeRecord['status'] === 'giriş_bariyeri_açıldı' ? 'Açıldı' : 'Kapandı / kantarda' ?></strong></div>
        </div>

        <div class="operation-row">
            <?php if ($canRollbackWeighbridge && $canShowRollback($activeRecord)): ?>
                <?php $activeHasFirstWeight = $hasFirstWeight($activeRecord); ?>
                <?php if (! $activeHasFirstWeight || $canAdminRollbackWeighbridge): ?>
                    <button
                        class="button button--ghost"
                        type="button"
                        data-open-rollback
                        data-notification-id="<?= (int) $activeRecord['id'] ?>"
                        data-plate="<?= htmlspecialchars((string) $activeRecord['plate_number']) ?>"
                        data-status="<?= htmlspecialchars($statusLabels[$activeRecord['status']] ?? $activeRecord['status']) ?>"
                        data-first-weight="<?= $activeHasFirstWeight ? '1' : '0' ?>"
                    >Geri Al</button>
                <?php else: ?>
                    <span class="table-muted">Bu araç için 1. tartım kaydedilmiş. Geri alma işlemi sadece yetkili kullanıcı tarafından yapılabilir.</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($activeRecord['status'] === 'giriş_bariyeri_açıldı'): ?>
                <form action="/weighbridge-entry/mark-on-scale" method="post">
                    <input type="hidden" name="notification_id" value="<?= (int) $activeRecord['id'] ?>">
                    <button class="button button--primary" type="submit">Kantar Giriş Yaptı</button>
                </form>
            <?php endif; ?>

            <?php if (in_array($activeRecord['status'], ['kantara_geldi', 'kantarda'], true)): ?>
                <form action="/weighbridge-entry/save-first-weight" method="post" class="weight-form">
                    <input type="hidden" name="notification_id" value="<?= (int) $activeRecord['id'] ?>">
                    <label class="field<?= $fieldClass('first_weight_kg') ?>"><span>İlk tartım kg</span><input type="number" name="first_weight_kg" value="<?= htmlspecialchars((string) ($validation['old']['first_weight_kg'] ?? '')) ?>" min="1000" max="100000" step="1" required><?= $fieldError('first_weight_kg') ?></label>
                    <label class="field"><span>Tartım zamanı</span><input type="text" value="<?= date('d.m.Y H:i') ?>" readonly></label>
                    <label class="field<?= $fieldClass('first_weight_reason') ?>"><span>Açıklama</span><input type="text" name="first_weight_reason" value="<?= htmlspecialchars((string) ($validation['old']['first_weight_reason'] ?? '')) ?>" placeholder="Simülasyon / operatör girişi" required><?= $fieldError('first_weight_reason') ?></label>
                    <button class="button button--primary" type="submit">1. Tartımı Yap</button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="empty-state">Girişi açılan veya kantarda bekleyen araç bulunmuyor.</p>
    <?php endif; ?>
</section>

<?php if ($notification !== null): ?>
    <section class="panel detail-panel">
        <div class="detail-header">
            <div><div class="detail-kicker">Plaka Arama Detayı</div><h2><?= htmlspecialchars((string) $notification['plate_number']) ?></h2></div>
            <span class="badge badge--info"><?= htmlspecialchars($statusLabels[$notification['status']] ?? $notification['status']) ?></span>
        </div>
        <div class="detail-grid">
            <div><span>Gönderici</span><strong><?= htmlspecialchars($senderName($notification)) ?></strong></div>
            <div><span>Ürün</span><strong><?= htmlspecialchars((string) $notification['product_name']) ?></strong></div>
            <div><span>Miktar</span><strong><?= htmlspecialchars($formatTon($notification['expected_quantity_kg'] ?? 0)) ?></strong></div>
            <div><span>Şoför</span><strong><?= htmlspecialchars((string) ($notification['driver_name'] ?? '-')) ?></strong></div>
        </div>
    </section>
<?php endif; ?>

<div class="modal-backdrop" data-rollback-backdrop hidden></div>
<dialog class="app-modal" id="rollback-modal">
    <form action="/weighbridge-entry/rollback" method="post" class="modal-form">
        <div class="modal-header">
            <div>
                <h2>Kantar İşlemini Geri Al</h2>
                <p>Bu araç için kantar girişi geri alınacak. Araç tekrar bekleyenler listesine dönecek.</p>
            </div>
            <button type="button" class="modal-close" data-close-rollback>×</button>
        </div>
        <input type="hidden" name="notification_id" id="rollback-notification-id">
        <div class="rollback-warning" id="rollback-admin-warning" hidden>
            Bu araç için 1. tartım kaydedilmiş. Yetkili geri alma yapılacak; tartım kaydı silinmeyecek, geçersiz sayıldı olarak işaretlenecek.
        </div>
        <div class="detail-grid detail-grid--compact">
            <div><span>Plaka</span><strong id="rollback-plate">-</strong></div>
            <div><span>Mevcut durum</span><strong id="rollback-status">-</strong></div>
        </div>
        <label class="field<?= $fieldClass('rollback_reason') ?>">
            <span>Geri alma nedeni</span>
            <select name="rollback_reason" id="rollback-reason" required>
                <option value="">Neden seçin</option>
                <option value="wrong_vehicle">Yanlış araç seçildi</option>
                <option value="plate_mixed">Plaka karıştı</option>
                <option value="not_on_scale">Araç kantara çıkmadı</option>
                <option value="operator_error">Operatör hatası</option>
                <option value="other">Diğer</option>
            </select>
            <?= $fieldError('rollback_reason') ?>
        </label>
        <label class="field<?= $fieldClass('rollback_note') ?>">
            <span>Açıklama</span>
            <textarea name="rollback_note" id="rollback-note" rows="3" placeholder="Gerekli durumlarda açıklama yazın."><?= htmlspecialchars((string) ($validation['old']['rollback_note'] ?? '')) ?></textarea>
            <?= $fieldError('rollback_note') ?>
        </label>
        <div class="modal-actions">
            <button class="button button--ghost" type="button" data-close-rollback>Vazgeç</button>
            <button class="button button--primary" type="submit">Geri Al</button>
        </div>
    </form>
</dialog>

<section class="panel">
    <div class="section-heading"><h2>Analiz İçin Bekleyen Araçlar</h2></div>
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead><tr><th>Plaka</th><th>Ürün</th><th>Gönderici</th><th>İlk tartım</th><th>Numune durumu</th><th>Bekleme süresi</th><th class="table-actions">İşlemler</th></tr></thead>
            <tbody>
                <?php if ($analysisWaiting === []): ?><tr><td colspan="7" class="empty-state">Analiz için bekleyen araç yok.</td></tr><?php endif; ?>
                <?php foreach ($analysisWaiting as $row): ?>
                    <tr class="clickable-row" data-vehicle-entry-id="<?= (int) $row['id'] ?>">
                        <td><?= htmlspecialchars((string) $row['plate_number']) ?></td>
                        <td><?= htmlspecialchars((string) $row['product_name']) ?></td>
                        <td><?= htmlspecialchars($senderName($row)) ?></td>
                        <td><?= htmlspecialchars($formatKg($row['first_weight_kg'])) ?><span class="table-muted"><?= htmlspecialchars($formatTon($row['first_weight_kg'])) ?></span></td>
                        <td><span class="badge badge--muted"><?= htmlspecialchars($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                        <td><?= htmlspecialchars((string) ($row['first_weighed_at'] ?? '-')) ?></td>
                        <td class="table-actions">
                            <a class="button button--small button--primary" href="/sample-analysis/edit?record_id=<?= (int) $row['weighbridge_record_id'] ?>">Analiz Ekranına Gönder</a>
                            <a class="button button--small" href="/weighbridge-entry?plate=<?= urlencode((string) $row['plate_number']) ?>">Detay</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('rollback-modal');
    const backdrop = document.querySelector('[data-rollback-backdrop]');
    const notificationInput = document.getElementById('rollback-notification-id');
    const plateText = document.getElementById('rollback-plate');
    const statusText = document.getElementById('rollback-status');
    const adminWarning = document.getElementById('rollback-admin-warning');
    const reasonSelect = document.getElementById('rollback-reason');
    const noteInput = document.getElementById('rollback-note');

    const closeModal = () => {
        if (modal?.open) modal.close();
        if (backdrop) backdrop.hidden = true;
    };

    document.querySelectorAll('[data-open-rollback]').forEach((button) => {
        button.addEventListener('click', () => {
            notificationInput.value = button.dataset.notificationId || '';
            plateText.textContent = button.dataset.plate || '-';
            statusText.textContent = button.dataset.status || '-';
            adminWarning.hidden = button.dataset.firstWeight !== '1';
            if (reasonSelect) reasonSelect.value = '';
            if (noteInput) noteInput.required = button.dataset.firstWeight === '1';
            if (backdrop) backdrop.hidden = false;
            if (modal && !modal.open) modal.showModal();
        });
    });

    reasonSelect?.addEventListener('change', () => {
        if (!noteInput) return;
        noteInput.required = reasonSelect.value === 'other' || !adminWarning.hidden;
    });

    document.querySelectorAll('[data-close-rollback]').forEach((button) => button.addEventListener('click', closeModal));
    backdrop?.addEventListener('click', closeModal);
});
</script>

<section class="panel">
    <div class="section-heading"><h2>İkinci Tartım Bekleyen Araçlar</h2></div>
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead><tr><th>Plaka</th><th>Ürün</th><th>Gönderici</th><th>Durum</th><th class="table-actions">İşlem</th></tr></thead>
            <tbody>
                <?php if ($secondWeighingWaiting === []): ?><tr><td colspan="5" class="empty-state">İkinci tartım bekleyen araç yok.</td></tr><?php endif; ?>
                <?php foreach ($secondWeighingWaiting as $row): ?>
                    <tr class="clickable-row" data-vehicle-entry-id="<?= (int) $row['id'] ?>">
                        <td><?= htmlspecialchars((string) $row['plate_number']) ?></td>
                        <td><?= htmlspecialchars((string) $row['product_name']) ?></td>
                        <td><?= htmlspecialchars($senderName($row)) ?></td>
                        <td><span class="badge badge--muted"><?= htmlspecialchars($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                        <td class="table-actions"><a class="button button--small" href="/second-weighing?plate=<?= urlencode((string) $row['plate_number']) ?>">İkinci Tartım</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="section-heading"><h2>Son Kantar İşlemleri</h2></div>
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead><tr><th>Fiş</th><th>Plaka</th><th>Ürün</th><th>İlk tartım</th><th>Durum</th></tr></thead>
            <tbody>
                <?php if ($recentRecords === []): ?><tr><td colspan="5" class="empty-state">Kantar işlemi yok.</td></tr><?php endif; ?>
                <?php foreach ($recentRecords as $row): ?>
                    <tr class="clickable-row" data-vehicle-entry-id="<?= (int) $row['id'] ?>">
                        <td><?= htmlspecialchars((string) ($row['ticket_number'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) $row['plate_number']) ?></td>
                        <td><?= htmlspecialchars((string) $row['product_name']) ?></td>
                        <td><?= htmlspecialchars($row['first_weight_kg'] === null ? '-' : $formatKg($row['first_weight_kg'])) ?></td>
                        <td><span class="badge badge--muted"><?= htmlspecialchars($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
