<?php

declare(strict_types=1);

$selected = static fn (string $value, string $current): string => $value === $current ? 'selected' : '';
$formatTon = static fn (float|int|string|null $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$statusClass = static function (string $status): string {
    return match ($status) {
        'completed', 'unloaded' => 'badge--success',
        'cancelled' => 'badge--danger',
        'at_weighbridge', 'in_analysis', 'directed_to_silo' => 'badge--info',
        default => 'badge--muted',
    };
};
$alerts = [
    'saved' => ['alert--success', 'Ürün bildirimi kaydedildi. Bekleyen ürün bildirimleri ve dashboard son işlemler bölümünde görünecek.'],
    'updated' => ['alert--success', 'Ürün bildirimi güncellendi.'],
];
$alert = $alerts[$message ?? ''] ?? null;
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Ürün Ön Bildirimleri</h1>
        <p class="page-subtitle">Firmalardan gelen araç ve ürün bildirimlerini takip edin.</p>
    </div>
    <a class="button button--primary" href="/delivery-notifications/create">Yeni Bildirim</a>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel filter-panel">
    <form action="/delivery-notifications" method="get" class="filter-grid">
        <label class="field">
            <span>Plaka</span>
            <input type="text" name="plate" value="<?= htmlspecialchars($filters['plate']) ?>" placeholder="34 ABC 123">
        </label>

        <label class="field">
            <span>Firma</span>
            <select name="company_id">
                <option value="">Tümü</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int) $company['id'] ?>" <?= $selected((string) $company['id'], $filters['company_id']) ?>>
                        <?= htmlspecialchars($company['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Ürün</span>
            <select name="product_id">
                <option value="">Tümü</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>" <?= $selected((string) $product['id'], $filters['product_id']) ?>>
                        <?= htmlspecialchars($product['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Tarih</span>
            <input type="date" name="date" value="<?= htmlspecialchars($filters['date']) ?>">
        </label>

        <label class="field">
            <span>Durum</span>
            <select name="status">
                <option value="">Tümü</option>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $selected($value, $filters['status']) ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="filter-actions">
            <button class="button button--primary" type="submit">Filtrele</button>
            <a class="button button--ghost" href="/delivery-notifications">Temizle</a>
        </div>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead>
                <tr>
                    <th>Bildirim</th>
                    <th>Gönderici</th>
                    <th>Ürün</th>
                    <th>Miktar</th>
                    <th>Araç / Şoför</th>
                    <th>Tarihler</th>
                    <th>Durum</th>
                    <th class="table-actions">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($notifications === []): ?>
                    <tr>
                        <td colspan="8" class="empty-state">Filtreye uygun ön bildirim yok.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($notifications as $notification): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($notification['notification_number']) ?></strong>
                            <?php if (! empty($notification['notes'])): ?>
                                <span class="table-muted"><?= htmlspecialchars($notification['notes']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars((string) (($notification['sender_type'] ?? 'company') === 'person' ? ($notification['sender_name'] ?? '-') : $notification['company_name'])) ?></strong>
                            <span class="table-muted">
                                <?= htmlspecialchars(($notification['sender_type'] ?? 'company') === 'person' ? 'Şahıs Ürünü' : 'Firma Ürünü') ?>
                                <?php if (($notification['sender_type'] ?? 'company') === 'company' && ! empty($notification['dispatch_number'])): ?>
                                    / İrsaliye: <?= htmlspecialchars($notification['dispatch_number']) ?>
                                <?php endif; ?>
                                <?php if (($notification['sender_type'] ?? 'company') === 'person' && ! empty($notification['identity_number'])): ?>
                                    / TC: <?= htmlspecialchars($notification['identity_number']) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($notification['product_name']) ?></td>
                        <td><?= htmlspecialchars($formatTon($notification['expected_quantity_kg'] ?? 0)) ?></td>
                        <td>
                            <strong><?= htmlspecialchars((string) ($notification['plate_number'] ?? '-')) ?></strong>
                            <span class="table-muted">
                                <?= htmlspecialchars((string) ($notification['vehicle_brand'] ?? '-')) ?>
                                /
                                <?= htmlspecialchars((string) ($notification['driver_name'] ?? '-')) ?>
                                <?php if (! empty($notification['driver_phone'])): ?>
                                    - <?= htmlspecialchars($notification['driver_phone']) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <span class="table-muted">Yükleme: <?= htmlspecialchars((string) ($notification['loading_date'] ?? '-')) ?></span>
                            <span class="table-muted">Geliş: <?= htmlspecialchars((string) ($notification['expected_arrival_date'] ?? '-')) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $statusClass($notification['status']) ?>">
                                <?= htmlspecialchars($statusOptions[$notification['status']] ?? $notification['status']) ?>
                            </span>
                        </td>
                        <td class="table-actions">
                            <a class="button button--small" href="/delivery-notifications/edit?id=<?= (int) $notification['id'] ?>">Düzenle</a>
                            <?php if ($notification['status'] !== 'cancelled'): ?>
                                <form action="/delivery-notifications/cancel" method="post" class="inline-form" data-confirm="Ön bildirimi iptal etmek istediğinize emin misiniz?">
                                    <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                    <button class="button button--small button--ghost" type="submit">İptal Et</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
