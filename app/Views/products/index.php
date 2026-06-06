<?php

declare(strict_types=1);
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Ürün Tanımları</h1>
        <p class="page-subtitle">Depoya kabul edilen ürün türlerini yönetin.</p>
    </div>
    <a class="button button--primary" href="/products/create">Yeni Ürün</a>
</header>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ürün Adı</th>
                    <th>Ürün Kodu</th>
                    <th>Açıklama</th>
                    <th>Durum</th>
                    <th class="table-actions">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($products === []): ?>
                    <tr>
                        <td colspan="5" class="empty-state">Henüz ürün kaydı yok.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($product['name']) ?></strong></td>
                        <td><?= htmlspecialchars((string) ($product['code'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($product['description'] ?? '-')) ?></td>
                        <td>
                            <span class="badge <?= (int) $product['is_active'] === 1 ? 'badge--success' : 'badge--muted' ?>">
                                <?= (int) $product['is_active'] === 1 ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </td>
                        <td class="table-actions">
                            <a class="button button--small" href="/products/edit?id=<?= (int) $product['id'] ?>">Düzenle</a>
                            <form action="/products/toggle-status" method="post" class="inline-form">
                                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                <button class="button button--small button--ghost" type="submit">
                                    <?= (int) $product['is_active'] === 1 ? 'Pasife Al' : 'Aktifleştir' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
