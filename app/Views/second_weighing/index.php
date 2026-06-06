<?php

declare(strict_types=1);

$alerts = [
    'not_found' => ['alert--danger', 'İkinci tartım bekleyen araç bulunamadı.'],
    'weight_required' => ['alert--danger', 'İkinci tartım 1.000 kg ile 100.000 kg arasında girilmelidir.'],
    'weight_too_high' => ['alert--danger', 'İkinci tartım ilk tartımdan büyük olamaz.'],
    'reason_required' => ['alert--danger', 'Manuel ikinci tartım değeri için açıklama zorunludur.'],
    'invalid' => ['alert--danger', 'Formdaki hatalı alanları kontrol edin.'],
    'completed' => ['alert--success', 'İkinci tartım tamamlandı. Net kg kaydedildi, silo doluluğu güncellendi ve araç işlemi kapatıldı.'],
];
$alert = $alerts[$message] ?? null;
$formatKg = static fn (float|int|string|null $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (float|int|string|null $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$validation = $validation ?? ['errors' => [], 'old' => [], 'general' => null];
$fieldError = static fn (string $field): string => isset($validation['errors'][$field]) ? '<small class="field-error">' . htmlspecialchars((string) $validation['errors'][$field]) . '</small>' : '';
$fieldClass = static fn (string $field): string => isset($validation['errors'][$field]) ? ' field--error' : '';
$statusLabel = static function (array $row): string {
    if (($row['operation_type'] ?? '') === 'PRODUCT_OUT') {
        return 'Ürün çıkışı / 2. tartım bekliyor';
    }

    if (($row['delivery_status'] ?? '') === 'ikinci_tartım_bekliyor') {
        return '2. tartım bekliyor';
    }

    return (string) ($row['delivery_status'] ?? '-');
};
$operationLabel = static fn (array $row): string => ($row['operation_type'] ?? 'PRODUCT_IN') === 'PRODUCT_OUT' ? 'Ürün Çıkışı' : 'Ürün Girişi';
?>
<header class="page-header">
    <div>
        <h1 class="page-title">İkinci Tartım</h1>
        <p class="page-subtitle">Siloya yönlendirilen veya boşaltımı tamamlanan araçların kapanış tartımını alın.</p>
    </div>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel filter-panel second-weighing-search">
    <form action="/second-weighing" method="get" class="plate-search plate-search--compact">
        <label class="field">
            <span>Plaka / barkod / firma / ürün / şoför</span>
            <input type="text" name="q" value="<?= htmlspecialchars((string) ($query ?? $plate)) ?>" placeholder="Barkod okutun veya arayın" autofocus>
        </label>
        <button class="button button--primary" type="submit">Ara</button>
    </form>
</section>

<?php if (($query ?? '') !== '' && $record === null && ($waitingRecords ?? []) === [] && $message === ''): ?>
    <div class="alert alert--danger">
        Bu arama için ikinci tartım bekleyen araç bulunamadı.
    </div>
<?php endif; ?>

<section class="panel second-weighing-queue">
    <div class="section-heading">
        <div>
            <h2>2. Tartıma Gelen Araçlar</h2>
            <span class="table-muted">Ürün girişi ve ürün çıkışı için ikinci tartımı bekleyen araçlar. Barkod, plaka, firma, ürün, şoför veya silo ile arayın.</span>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead>
                <tr>
                    <th>İşlem tipi</th>
                    <th>Plaka</th>
                    <th>Ürün</th>
                    <th>Firma</th>
                    <th>İlk Tartım</th>
                    <th>Silo</th>
                    <th>Durum</th>
                    <th>Yönlendirme / Boşaltım</th>
                    <th class="table-actions">İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($waitingRecords ?? []) === []): ?>
                    <tr><td colspan="9" class="empty-state">İkinci tartım bekleyen araç yok.</td></tr>
                <?php endif; ?>
                <?php foreach (($waitingRecords ?? []) as $row): ?>
                    <?php
                    $selected = $record !== null && (string) ($record['operation_type'] ?? 'PRODUCT_IN') === (string) ($row['operation_type'] ?? 'PRODUCT_IN') && (int) ($record['weighbridge_record_id'] ?? 0) === (int) ($row['weighbridge_record_id'] ?? 0) && (int) ($record['outbound_id'] ?? 0) === (int) ($row['outbound_id'] ?? 0);
                    $isOutbound = ($row['operation_type'] ?? 'PRODUCT_IN') === 'PRODUCT_OUT';
                    ?>
                    <tr class="<?= $isOutbound ? '' : 'clickable-row' ?> <?= $selected ? 'operation-row-highlight' : '' ?> <?= $isOutbound ? 'second-weighing-row--outbound' : 'second-weighing-row--inbound' ?>" <?= $isOutbound ? '' : 'data-vehicle-entry-id="' . (int) $row['delivery_notification_id'] . '" data-vehicle-step="7"' ?>>
                        <td><span class="badge badge--info"><?= htmlspecialchars($operationLabel($row)) ?></span></td>
                        <td><strong><?= htmlspecialchars((string) $row['plate_number']) ?></strong><span class="table-muted"><?= htmlspecialchars((string) $row['ticket_number']) ?></span></td>
                        <td><?= htmlspecialchars((string) $row['product_name']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['sender_display'] ?? $row['company_name'])) ?></td>
                        <td><?= htmlspecialchars($formatKg($row['first_weight_kg'])) ?><span class="table-muted"><?= htmlspecialchars($formatTon($row['first_weight_kg'])) ?></span></td>
                        <td><?= htmlspecialchars((string) ($row['silo_code'] . ' - ' . $row['silo_name'])) ?></td>
                        <td><span class="badge badge--info"><?= htmlspecialchars($statusLabel($row)) ?></span></td>
                        <td>
                            <span class="table-muted">Yönlendirme: <?= htmlspecialchars((string) ($row['directed_at'] ?? '-')) ?></span>
                            <span class="table-muted">Boşaltım: <?= htmlspecialchars((string) ($row['unloading_completed_at'] ?? 'Kayıt yok')) ?></span>
                        </td>
                        <td class="table-actions">
                            <?php if (($row['operation_type'] ?? 'PRODUCT_IN') === 'PRODUCT_OUT'): ?>
                                <a class="button button--small button--primary" href="/second-weighing?outbound_id=<?= (int) $row['outbound_id'] ?>">2. Tartım Al</a>
                            <?php else: ?>
                                <a class="button button--small button--primary" href="/second-weighing?record_id=<?= (int) $row['weighbridge_record_id'] ?>">2. Tartım Al</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($record !== null): ?>
    <section class="panel detail-panel">
        <div class="detail-header">
            <div>
                <div class="detail-kicker"><?= htmlspecialchars($operationLabel($record)) ?> / İkinci Tartım Bekliyor</div>
                <h2><?= htmlspecialchars($record['plate_number']) ?></h2>
            </div>
            <span class="badge badge--info">Hazır / 2. tartım bekliyor</span>
        </div>

        <div class="detail-grid">
            <div>
                <span>Kantar Fişi</span>
                <strong><?= htmlspecialchars($record['ticket_number']) ?></strong>
            </div>
            <div>
                <span>Gönderici</span>
                <strong><?= htmlspecialchars((string) ($record['sender_display'] ?? $record['company_name'])) ?></strong>
            </div>
            <div>
                <span>Ürün</span>
                <strong><?= htmlspecialchars($record['product_name']) ?></strong>
            </div>
            <div>
                <span>Şoför</span>
                <strong><?= htmlspecialchars((string) ($record['driver_name'] ?? '-')) ?></strong>
            </div>
            <div>
                <span>İlk Tartım</span>
                <strong id="first-weight" data-first-kg="<?= htmlspecialchars((string) (float) $record['first_weight_kg']) ?>"><?= htmlspecialchars($formatKg($record['first_weight_kg'])) ?></strong>
                <span class="table-muted"><?= htmlspecialchars($formatTon($record['first_weight_kg'])) ?></span>
            </div>
            <div>
                <span><?= ($record['operation_type'] ?? 'PRODUCT_IN') === 'PRODUCT_OUT' ? 'Kaynak Silo' : 'Hedef Silo' ?></span>
                <strong><?= htmlspecialchars($record['silo_code'] . ' - ' . $record['silo_name']) ?></strong>
            </div>
            <div>
                <span>Yönlendirme</span>
                <strong><?= htmlspecialchars((string) ($record['directed_at'] ?? '-')) ?></strong>
            </div>
        </div>

        <form action="/second-weighing/complete" method="post" class="operation-row" data-confirm="İkinci tartımı tamamlayıp silo doluluğunu güncellemek istediğinize emin misiniz?">
            <?php if (($record['operation_type'] ?? 'PRODUCT_IN') === 'PRODUCT_OUT'): ?>
                <input type="hidden" name="outbound_id" value="<?= (int) $record['outbound_id'] ?>">
            <?php else: ?>
                <input type="hidden" name="record_id" value="<?= (int) $record['weighbridge_record_id'] ?>">
            <?php endif; ?>
            <label class="field<?= $fieldClass('second_weight_kg') ?>">
                <span><?= ($record['operation_type'] ?? 'PRODUCT_IN') === 'PRODUCT_OUT' ? 'İkinci tartım kg (dolu araç)' : 'İkinci tartım kg (boş araç)' ?></span>
                <input
                    id="second-weight"
                    type="number"
                    name="second_weight_kg"
                    value="<?= htmlspecialchars((string) ($validation['old']['second_weight_kg'] ?? '')) ?>"
                    min="1000"
                    <?= ($record['operation_type'] ?? 'PRODUCT_IN') === 'PRODUCT_OUT' ? '' : 'max="' . htmlspecialchars((string) (float) $record['first_weight_kg']) . '"' ?>
                    step="1"
                    required
                >
                <?= $fieldError('second_weight_kg') ?>
            </label>
            <div class="net-preview">
                <span>Net miktar</span>
                <strong id="net-weight">-</strong>
            </div>
            <label class="field<?= $fieldClass('second_weight_reason') ?>">
                <span>Manuel giriş nedeni</span>
                <input type="text" name="second_weight_reason" value="<?= htmlspecialchars((string) ($validation['old']['second_weight_reason'] ?? '')) ?>" placeholder="Simülasyon / operatör girişi" required>
                <?= $fieldError('second_weight_reason') ?>
            </label>
            <button class="button button--primary" type="submit">İşlemi Tamamla</button>
        </form>
    </section>

    <script>
        const firstWeight = Number(document.getElementById('first-weight').dataset.firstKg);
        const operationType = <?= json_encode((string) ($record['operation_type'] ?? 'PRODUCT_IN')) ?>;
        const secondWeight = document.getElementById('second-weight');
        const netWeight = document.getElementById('net-weight');

        secondWeight.addEventListener('input', () => {
            const second = Number(secondWeight.value);
            const net = operationType === 'PRODUCT_OUT' ? second - firstWeight : firstWeight - second;
            netWeight.textContent = Number.isFinite(second) && secondWeight.value !== ''
                ? `${net.toLocaleString('tr-TR', {maximumFractionDigits: 0})} kg (${(net / 1000).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ton)`
                : '-';
        });
    </script>
<?php endif; ?>
