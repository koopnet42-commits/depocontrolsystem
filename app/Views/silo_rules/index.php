<?php

declare(strict_types=1);
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Silo Yönlendirme Kuralları</h1>
        <p class="page-subtitle">Analiz değerlerine göre hedef silo eşleştirmelerini yönetin.</p>
    </div>
    <a class="button button--primary" href="/silo-rules/create">Yeni Kural</a>
</header>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead>
                <tr>
                    <th>Kural</th>
                    <th>Ürün</th>
                    <th>Kalite Aralığı</th>
                    <th>Hedef Silo</th>
                    <th>Öncelik</th>
                    <th>Durum</th>
                    <th class="table-actions">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rules === []): ?>
                    <tr>
                        <td colspan="7" class="empty-state">Henüz silo yönlendirme kuralı yok.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rules as $rule): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($rule['name']) ?></strong></td>
                        <td><?= htmlspecialchars($rule['product_name']) ?></td>
                        <td>
                            <span class="table-muted">Rutubet: <?= htmlspecialchars((string) ($rule['min_moisture'] ?? '-')) ?> / <?= htmlspecialchars((string) ($rule['max_moisture'] ?? '-')) ?></span>
                            <span class="table-muted">Protein min: <?= htmlspecialchars((string) ($rule['min_protein'] ?? '-')) ?></span>
                            <span class="table-muted">Hektolitre min: <?= htmlspecialchars((string) ($rule['min_hectoliter'] ?? '-')) ?></span>
                            <span class="table-muted">Yabancı max: <?= htmlspecialchars((string) ($rule['max_foreign_material'] ?? '-')) ?>, Süne max: <?= htmlspecialchars((string) ($rule['max_sunn_pest_rate'] ?? '-')) ?></span>
                        </td>
                        <td><?= htmlspecialchars($rule['silo_code'] . ' - ' . $rule['silo_name']) ?></td>
                        <td><?= (int) $rule['priority'] ?></td>
                        <td>
                            <span class="badge <?= (int) $rule['is_active'] === 1 ? 'badge--success' : 'badge--muted' ?>">
                                <?= (int) $rule['is_active'] === 1 ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </td>
                        <td class="table-actions">
                            <a class="button button--small" href="/silo-rules/edit?id=<?= (int) $rule['id'] ?>">Düzenle</a>
                            <form action="/silo-rules/toggle-status" method="post" class="inline-form" data-confirm="Kural durumunu değiştirmek istediğinize emin misiniz?">
                                <input type="hidden" name="id" value="<?= (int) $rule['id'] ?>">
                                <button class="button button--small button--ghost" type="submit">
                                    <?= (int) $rule['is_active'] === 1 ? 'Pasife Al' : 'Aktifleştir' ?>
                                </button>
                            </form>
                            <form action="/silo-rules/delete" method="post" class="inline-form" data-confirm="Kural silinmeyecek, pasife alınacak. Devam edilsin mi?">
                                <input type="hidden" name="id" value="<?= (int) $rule['id'] ?>">
                                <button class="button button--small button--ghost" type="submit">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
