<?php

declare(strict_types=1);

$messages = [
    'saved' => ['alert--success', 'Firma kaydı oluşturuldu.'],
    'updated' => ['alert--success', 'Firma bilgileri güncellendi.'],
    'status' => ['alert--success', 'Firma aktif/pasif durumu güncellendi.'],
];
$message = (string) ($message ?? '');
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Firma Tanımları</h1>
        <p class="page-subtitle">Ürün gönderen firma kayıtlarını yönetin.</p>
    </div>
    <a class="button button--primary" href="/companies/create">Yeni Firma</a>
</header>

<?php if (isset($messages[$message])): ?>
    <div class="alert <?= htmlspecialchars($messages[$message][0]) ?>">
        <?= htmlspecialchars($messages[$message][1]) ?>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Firma Adı</th>
                    <th>Vergi No</th>
                    <th>Telefon</th>
                    <th>Yetkili Kişi</th>
                    <th>Durum</th>
                    <th class="table-actions">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($companies === []): ?>
                    <tr>
                        <td colspan="6" class="empty-state">Henüz firma kaydı yok.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($companies as $company): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($company['name']) ?></strong>
                            <?php if (! empty($company['address'])): ?>
                                <span class="table-muted"><?= htmlspecialchars($company['address']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string) ($company['tax_number'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($company['phone'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string) ($company['contact_person'] ?? '-')) ?></td>
                        <td>
                            <span class="badge <?= (int) $company['is_active'] === 1 ? 'badge--success' : 'badge--muted' ?>">
                                <?= (int) $company['is_active'] === 1 ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </td>
                        <td class="table-actions">
                            <a class="button button--small" href="/companies/edit?id=<?= (int) $company['id'] ?>">Düzenle</a>
                            <form action="/companies/toggle-status" method="post" class="inline-form">
                                <input type="hidden" name="id" value="<?= (int) $company['id'] ?>">
                                <button class="button button--small button--ghost" type="submit">
                                    <?= (int) $company['is_active'] === 1 ? 'Pasife Al' : 'Aktifleştir' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
