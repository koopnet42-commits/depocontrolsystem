<?php

declare(strict_types=1);
$formatKg = static fn (float|int|string|null $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (float|int|string|null $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$alerts = [
    'routed' => ['alert--success', 'Analiz kaydedildi, uygun silo atandı. Barkod / karekod basabilirsiniz.'],
    'manual_required' => ['alert--danger', 'Uygun silo bulunamadı. Barkod basmak için manuel silo seçin.'],
    'manual_assigned' => ['alert--success', 'Manuel silo seçimi kaydedildi. Barkod / karekod basabilirsiniz.'],
    'missing_silo' => ['alert--danger', 'Silo seçmeden barkod basılamaz.'],
    'silo_product_mismatch' => ['alert--danger', 'Seçtiğiniz silo bu ürüne ait değil. Ürün ile eşleşen bir silo seçmelisiniz.'],
    'not_found' => ['alert--danger', 'Barkod veya yönlendirilebilir araç kaydı bulunamadı.'],
];
$alert = $alerts[$message ?? ($_GET['message'] ?? '')] ?? null;
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Barkodlu Sevk Fişleri</h1>
        <p class="page-subtitle">Analizi tamamlanmış ve silo belirlenmiş araçlar için zorunlu yönlendirme fişi oluşturun.</p>
    </div>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel filter-panel">
    <form action="/barcode-tickets/lookup" method="get" class="plate-search">
        <label class="field">
            <span>Barkod okut / ticket kodu gir</span>
            <input type="text" name="code" placeholder="SVK-20260530-ABC123">
        </label>
        <button class="button button--primary" type="submit">Kaydı Bul</button>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead>
                <tr>
                    <th>Plaka</th>
                    <th>Firma</th>
                    <th>Ürün</th>
                    <th>Şoför</th>
                    <th>İlk Tartım</th>
                    <th>Hedef Silo</th>
                    <th>Durum</th>
                    <th>Barkod</th>
                    <th class="table-actions">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records === []): ?>
                    <tr>
                        <td colspan="9" class="empty-state">Fiş oluşturulabilecek araç yok.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($records as $record): ?>
                    <?php $siloMismatch = (int) ($record['assigned_silo_id'] ?? 0) > 0 && (int) ($record['silo_product_id'] ?? 0) !== (int) ($record['product_id'] ?? 0); ?>
                    <tr class="<?= (int) ($selectedRecordId ?? 0) === (int) $record['weighbridge_record_id'] ? 'operation-row-highlight' : '' ?>">
                        <td><strong><?= htmlspecialchars($record['plate_number']) ?></strong></td>
                        <td><?= htmlspecialchars($record['company_name']) ?></td>
                        <td><?= htmlspecialchars($record['product_name']) ?></td>
                        <td><?= htmlspecialchars((string) ($record['driver_name'] ?? '-')) ?></td>
                        <td>
                            <?= htmlspecialchars($formatKg($record['first_weight_kg'])) ?>
                            <span class="table-muted"><?= htmlspecialchars($formatTon($record['first_weight_kg'])) ?></span>
                            <span class="table-muted"><?= htmlspecialchars((string) $record['first_weighed_at']) ?></span>
                        </td>
                        <td>
                            <?php if ((int) ($record['assigned_silo_id'] ?? 0) > 0): ?>
                                <span class="table-muted">Sistem önerisi: <?= htmlspecialchars((string) (($record['silo_code'] ?? '-') . ' - ' . ($record['silo_name'] ?? '-'))) ?></span>
                                <strong>Nihai silo: <?= htmlspecialchars((string) (($record['silo_code'] ?? '-') . ' - ' . ($record['silo_name'] ?? '-'))) ?></strong>
                                <?php if ($siloMismatch): ?>
                                    <span class="badge badge--danger">Bu silo <?= htmlspecialchars((string) $record['product_name']) ?> ürünüyle eşleşmiyor</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge--danger">Silo seçilmedi</span>
                                <form action="/barcode-tickets/assign-silo" method="post" class="inline-form">
                                    <input type="hidden" name="record_id" value="<?= (int) $record['weighbridge_record_id'] ?>">
                                    <select name="silo_id" required>
                                        <option value="">Manuel silo seç</option>
                                        <?php foreach ($silos as $silo): ?>
                                            <?php $matchesProduct = (int) ($silo['product_id'] ?? 0) === (int) ($record['product_id'] ?? 0); ?>
                                            <option value="<?= (int) $silo['id'] ?>" <?= $matchesProduct ? '' : 'disabled' ?>>
                                                <?= htmlspecialchars($silo['code'] . ' - ' . $silo['name'] . ' / ' . ($silo['product_name'] ?? '-')) ?><?= $matchesProduct ? '' : ' - bu ürüne uygun değil' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="button button--small" type="submit">Silo Seç</button>
                                    <span class="table-muted">Sadece <?= htmlspecialchars((string) $record['product_name']) ?> ürünüyle eşleşen silolar seçilebilir.</span>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $record['barcode'] === null ? 'badge--muted' : 'badge--info' ?>"><?= htmlspecialchars($record['barcode'] === null ? 'Barkod bekliyor' : 'Siloya yönlendirildi') ?></span></td>
                        <td>
                            <?php if ($record['barcode'] === null): ?>
                                <span class="badge badge--muted">Oluşturulmadı</span>
                            <?php else: ?>
                                <strong><?= htmlspecialchars($record['barcode']) ?></strong>
                                <span class="table-muted"><?= htmlspecialchars((string) $record['issued_at']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <?php if ($record['barcode'] === null && (int) ($record['assigned_silo_id'] ?? 0) > 0 && ! $siloMismatch): ?>
                                <form action="/barcode-tickets/generate" method="post" class="inline-form" target="_blank" data-refresh-after-submit="1200">
                                    <input type="hidden" name="record_id" value="<?= (int) $record['weighbridge_record_id'] ?>">
                                    <button class="button button--small button--primary" type="submit">Barkod Bas</button>
                                </form>
                            <?php elseif ($record['barcode'] === null && $siloMismatch): ?>
                                <button class="button button--small" type="button" disabled>Ürün-silo uyumsuz</button>
                            <?php elseif ($record['barcode'] === null): ?>
                                <button class="button button--small" type="button" disabled>Silo seçmeden basılamaz</button>
                            <?php else: ?>
                                <a class="button button--small button--primary" href="/barcode-tickets/print?record_id=<?= (int) $record['weighbridge_record_id'] ?>" target="_blank">
                                    Yazdır
                                </a>
                                <a class="button button--small" href="/unloading-operations?code=<?= urlencode((string) $record['barcode']) ?>">Yönlendirme</a>
                                <a class="button button--small" href="/second-weighing?record_id=<?= (int) $record['weighbridge_record_id'] ?>">2. Tartım</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
