<?php

declare(strict_types=1);

$tabForTable = [
    'camera_settings' => 'cameras',
    'scale_settings' => 'scales',
    'barrier_settings' => 'barriers',
][$table];
?>
<section class="panel settings-list">
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead>
                <tr>
                    <th>Ad</th>
                    <th>Kullanım yeri</th>
                    <th>Tip</th>
                    <th>Bağlantı</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars((string) $row['usage_location']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['camera_type'] ?? $row['communication_type'] ?? $row['control_type'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['connection_url'] ?? $row['ip_address'] ?? '-')) ?></td>
                        <td><span class="badge <?= (int) $row['is_active'] === 1 ? 'badge--success' : 'badge--muted' ?>"><?= (int) $row['is_active'] === 1 ? 'Aktif' : 'Pasif' ?></span></td>
                        <td class="table-actions">
                            <a class="button button--small" href="/settings?tab=<?= htmlspecialchars($tabForTable) ?>&<?= htmlspecialchars($editKey) ?>=<?= (int) $row['id'] ?>">Düzenle</a>
                            <form action="/settings/test" method="post" class="inline-form">
                                <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="button button--small" type="submit"><?= $table === 'scale_settings' ? 'Test Okuma' : ($table === 'barrier_settings' ? 'Test Aç/Kapat' : 'Test Bağlantısı') ?></button>
                            </form>
                            <form action="/settings/toggle" method="post" class="inline-form" data-confirm="Durumu değiştirmek istediğinize emin misiniz?">
                                <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="button button--small button--ghost" type="submit"><?= (int) $row['is_active'] === 1 ? 'Pasife Al' : 'Aktifleştir' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="empty-state">Henüz kayıt yok.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
