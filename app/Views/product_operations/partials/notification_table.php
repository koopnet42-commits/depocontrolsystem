<?php

declare(strict_types=1);
?>
<div class="table-wrap">
    <table class="data-table data-table--compact operation-table">
        <thead>
            <tr>
                <th>Bildirim no</th>
                <th>Firma/şahıs</th>
                <th>Ürün</th>
                <th>Plaka</th>
                <th>Tahmini geliş tarihi</th>
                <th>Durum</th>
                <th>Son işlem</th>
                <th class="table-actions">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($notifications === []): ?>
                <tr><td colspan="8" class="empty-state">Ön bildirim kaydı yok.</td></tr>
            <?php endif; ?>
            <?php foreach ($notifications as $notification): ?>
                <?php $isWaiting = in_array($notification['status'], ['pending', 'ürün_bildirimi'], true); ?>
                <?php $operationType = (string) ($notification['operation_type'] ?? 'PRODUCT_IN'); ?>
                <tr class="clickable-row <?= htmlspecialchars($statusClass($notification)) ?> <?= $operationType === 'PRODUCT_OUT' ? 'operation-row-state--outbound' : 'operation-row-state--inbound' ?>" data-row-detail="<?= (int) $notification['id'] ?>">
                    <td>
                        <strong><?= htmlspecialchars((string) $notification['notification_number']) ?></strong>
                        <span class="badge <?= $operationType === 'PRODUCT_OUT' ? 'badge--danger' : 'badge--success' ?>"><?= $operationType === 'PRODUCT_OUT' ? 'Ürün Çıkışı' : 'Ürün Girişi' ?></span>
                    </td>
                    <td>
                        <?= htmlspecialchars($senderName($notification)) ?>
                        <span class="table-muted"><?= htmlspecialchars($senderLabel($notification)) ?></span>
                    </td>
                    <td><?= htmlspecialchars((string) $notification['product_name']) ?></td>
                    <td><strong><?= htmlspecialchars((string) ($notification['plate_number'] ?? '-')) ?></strong></td>
                    <td><?= htmlspecialchars((string) ($notification['expected_arrival_date'] ?: '-')) ?></td>
                    <td><span class="badge <?= htmlspecialchars($statusBadgeClass($notification)) ?>"><?= htmlspecialchars($statusLabels[$notification['status']] ?? $notification['status']) ?></span></td>
                    <td><?= htmlspecialchars($lastAction($notification)) ?></td>
                    <td class="table-actions operation-actions">
                        <button class="button button--small" type="button" data-open-detail="<?= (int) $notification['id'] ?>">Detay</button>
                        <?php if ($isWaiting): ?>
                            <button class="button button--small" type="button" data-edit-notification="<?= (int) $notification['id'] ?>">Düzenle</button>
                            <a class="button button--small button--primary" href="/product-operations/entry?mode=inbound&notification_id=<?= (int) $notification['id'] ?>">Akış Başlat</a>
                            <button class="button button--small button--ghost" type="button" data-open-cancel="<?= (int) $notification['id'] ?>">İptal</button>
                        <?php else: ?>
                            <button class="button button--small" type="button" disabled>Düzenle</button>
                            <button class="button button--small" type="button" disabled>Akış Başlat</button>
                            <button class="button button--small" type="button" disabled>İptal</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
