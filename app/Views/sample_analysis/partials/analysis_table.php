<?php

declare(strict_types=1);
?>
<div class="table-wrap table-wrap--spaced">
    <table class="data-table data-table--compact">
        <thead>
            <tr>
                <th>Plaka</th>
                <th>Ürün</th>
                <th>Gönderici</th>
                <th>Sonuç</th>
                <th>Değerler</th>
                <th>Silo</th>
                <th>Tarih</th>
                <th class="table-actions">İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($analysisRows === []): ?>
                <tr><td colspan="8" class="empty-state">Analiz kaydı bulunamadı.</td></tr>
            <?php endif; ?>
            <?php foreach ($analysisRows as $row): ?>
                <?php $isRejected = ($row['result'] ?? '') === 'rejected' || ($row['result_status'] ?? '') === 'ret'; ?>
                <tr class="clickable-row analysis-state-row analysis-state-row--<?= htmlspecialchars((string) ($row['result'] ?? 'pending')) ?>" data-vehicle-entry-id="<?= (int) $row['entry_id'] ?>" data-vehicle-step="3">
                    <td><strong><?= htmlspecialchars((string) $row['plate_number']) ?></strong></td>
                    <td><?= htmlspecialchars((string) $row['product_name']) ?></td>
                    <td><?= htmlspecialchars($senderName($row)) ?></td>
                    <td>
                        <span class="badge <?= $resultClass($row['result'] ?? null) ?>"><?= htmlspecialchars($resultOptions[$row['result']] ?? (string) $row['result']) ?></span>
                        <?php if (($row['result'] ?? '') === 'conditional'): ?>
                            <span class="table-muted"><?= htmlspecialchars($conditionalReasonText($row) ?: 'Şartlı kabul sebebi kaydedilmemiş') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="table-muted">R: <?= htmlspecialchars((string) ($row['moisture'] ?? '-')) ?> / P: <?= htmlspecialchars((string) ($row['protein'] ?? '-')) ?></span>
                        <span class="table-muted">H: <?= htmlspecialchars((string) ($row['hectoliter'] ?? '-')) ?> / G: <?= htmlspecialchars((string) ($row['gluten'] ?? '-')) ?></span>
                    </td>
                    <td><?= htmlspecialchars(trim((string) (($row['silo_code'] ?? '') . ' ' . ($row['silo_name'] ?? ''))) ?: '-') ?></td>
                    <td><?= htmlspecialchars((string) ($row['analyzed_at'] ?? '-')) ?></td>
                    <td class="table-actions">
                        <?php if ($isRejected): ?>
                            <a class="button button--small button--danger" href="/sample-analysis/rejection-print?analysis_id=<?= (int) $row['id'] ?>">Ret Fişi</a>
                        <?php else: ?>
                            <a class="button button--small" href="/sample-analysis/edit?record_id=<?= (int) $row['weighbridge_record_id'] ?>">Düzenle</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
