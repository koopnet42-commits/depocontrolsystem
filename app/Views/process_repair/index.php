<?php

declare(strict_types=1);

$alerts = [
    'repaired' => ['alert--success', 'Süreç onarım işlemi kaydedildi.'],
    'invalid' => ['alert--danger', 'Bu onarım için eksik ilişki var. Önce gerekli kayıtları tamamlayın.'],
    'not_found' => ['alert--danger', 'Araç kaydı bulunamadı.'],
];
$alert = $alerts[$message] ?? null;
$formatKg = static fn (mixed $kg): string => $kg === null || $kg === '' ? '-' : number_format((float) $kg, 0, ',', '.') . ' kg';
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Araç Süreç Onarım</h1>
        <p class="page-subtitle">Bozulmuş araç statülerini ve eksik ilişkileri admin olarak düzeltin.</p>
    </div>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel filter-panel">
    <form action="/process-repair" method="get" class="plate-search">
        <label class="field">
            <span>Plaka ile ara</span>
            <input type="text" name="plate" value="<?= htmlspecialchars($plate) ?>" placeholder="42 ABC 123" required>
        </label>
        <button class="button button--primary" type="submit">Ara</button>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead>
                <tr>
                    <th>Kayıt</th>
                    <th>Plaka</th>
                    <th>Ürün</th>
                    <th>Mevcut Status</th>
                    <th>İlişkiler</th>
                    <th class="table-actions">Admin Onarım</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records === []): ?>
                    <tr><td colspan="6" class="empty-state">Plaka arayınca süreç kayıtları burada görünecek.</td></tr>
                <?php endif; ?>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string) $record['notification_number']) ?></strong><span class="table-muted">Entry #<?= (int) $record['entry_id'] ?></span></td>
                        <td><strong><?= htmlspecialchars((string) $record['plate_number']) ?></strong></td>
                        <td><?= htmlspecialchars((string) ($record['product_name'] ?? '-')) ?></td>
                        <td><span class="badge badge--info"><?= htmlspecialchars((string) $record['entry_status']) ?></span></td>
                        <td>
                            <span class="table-muted">product_id: <?= htmlspecialchars((string) ($record['product_id'] ?? '-')) ?></span>
                            <span class="table-muted">first_weight: <?= htmlspecialchars($formatKg($record['first_weight_kg'])) ?></span>
                            <span class="table-muted">analysis_id: <?= htmlspecialchars((string) ($record['analysis_id'] ?? '-')) ?></span>
                            <span class="table-muted">assigned_silo_id: <?= htmlspecialchars((string) ($record['assigned_silo_id'] ?? '-')) ?></span>
                            <span class="table-muted">barcode_ticket: <?= htmlspecialchars((string) ($record['barcode'] ?? '-')) ?></span>
                        </td>
                        <td class="table-actions">
                            <form action="/process-repair/repair" method="post" class="inline-form" data-confirm="Bu süreç onarım işlemi audit log'a yazılacak. Devam edilsin mi?">
                                <input type="hidden" name="entry_id" value="<?= (int) $record['entry_id'] ?>">
                                <input type="hidden" name="plate" value="<?= htmlspecialchars((string) $record['plate_number']) ?>">
                                <button class="button button--small" name="repair_action" value="analysis_waiting" type="submit">Analiz Bekliyor Yap</button>
                                <button class="button button--small" name="repair_action" value="send_barcode" type="submit">Barkod Ekranına Gönder</button>
                                <button class="button button--small" name="repair_action" value="direct_to_silo" type="submit">Siloya Yönlendir</button>
                                <button class="button button--small" name="repair_action" value="prepare_unloading" type="submit">Silo Boşaltıma Hazırla</button>
                                <select name="silo_id">
                                    <option value="">Silo seç</option>
                                    <?php foreach ($silos as $silo): ?>
                                        <option value="<?= (int) $silo['id'] ?>" <?= (int) ($record['assigned_silo_id'] ?? 0) === (int) $silo['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($silo['code'] . ' - ' . $silo['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button button--small button--primary" name="repair_action" value="assign_silo" type="submit">Silo Seç</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
