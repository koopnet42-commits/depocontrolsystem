<?php

declare(strict_types=1);

$percent = static function (array $silo): int {
    $capacity = (float) $silo['capacity_kg'];
    $stock = (float) $silo['current_stock_kg'];

    if ($capacity <= 0) {
        return 0;
    }

    return max(0, min(100, (int) round(($stock / $capacity) * 100)));
};
$formatTon = static fn (float|int $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$alerts = [
    'not_empty' => ['alert--danger', 'Bu siloda ürün bulunduğu için kalıcı olarak silinemez. Pasife alabilirsiniz.'],
    'deleted' => ['alert--success', 'Boş silo kalıcı olarak silindi.'],
];
$alert = $alerts[$message ?? ''] ?? null;
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Silo Tanımları</h1>
        <p class="page-subtitle">Silo kapasite, doluluk ve kalite aralıklarını yönetin.</p>
    </div>
    <a class="button button--primary" href="/silos/create">Yeni Silo</a>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="silo-card-grid" aria-label="Silo kartları">
    <?php if ($silos === []): ?>
        <div class="panel empty-state">Henüz silo kaydı yok.</div>
    <?php endif; ?>

    <?php foreach ($silos as $silo): ?>
        <?php
        $fillPercent = $percent($silo);
        $capacity = (float) $silo['capacity_kg'];
        $stock = (float) $silo['current_stock_kg'];
        $free = max(0, $capacity - $stock);
        $visualType = ($silo['visual_type'] ?? 'vertical') === 'horizontal' ? 'horizontal' : 'vertical';
        ?>
        <article class="silo-card silo-card--control">
            <div class="silo-visual-card silo-visual-card--<?= htmlspecialchars($visualType) ?>">
                <div class="silo-visual">
                    <span class="silo-visual__fill" style="<?= $visualType === 'horizontal' ? 'width' : 'height' ?>: <?= $fillPercent ?>%"></span>
                    <div class="silo-visual__content">
                        <span><?= htmlspecialchars($silo['code']) ?></span>
                        <strong><?= htmlspecialchars($silo['name']) ?></strong>
                        <small>Ürün: <?= htmlspecialchars((string) ($silo['product_name'] ?? '-')) ?></small>
                        <b>%<?= $fillPercent ?> Dolu</b>
                        <small>Mevcut: <?= htmlspecialchars($formatTon($stock)) ?></small>
                        <small>Kapasite: <?= htmlspecialchars($formatTon($capacity)) ?></small>
                        <small>Boş kapasite: <?= htmlspecialchars($formatTon($free)) ?></small>
                    </div>
                </div>
            </div>
            <div class="silo-card__top silo-card__top--actions">
                <span class="badge <?= (int) $silo['is_active'] === 1 ? 'badge--success' : 'badge--muted' ?>">
                    <?= (int) $silo['is_active'] === 1 ? 'Aktif' : 'Pasif' ?>
                </span>
                <span class="badge badge--muted"><?= $visualType === 'horizontal' ? 'Yatay' : 'Dikey' ?></span>
            </div>
            <div class="silo-card__actions">
                <a class="button button--small" href="/silos/edit?id=<?= (int) $silo['id'] ?>">Düzenle</a>
                <form action="/silos/toggle-status" method="post" class="inline-form" data-confirm="Silo durumunu değiştirmek istediğinize emin misiniz?">
                    <input type="hidden" name="id" value="<?= (int) $silo['id'] ?>">
                    <button class="button button--small button--ghost" type="submit">
                        <?= (int) $silo['is_active'] === 1 ? 'Pasife Al' : 'Aktifleştir' ?>
                    </button>
                </form>
                <form action="/silos/delete" method="post" class="inline-form" data-confirm="Silo silinmeyecek, pasife alınacak. Devam edilsin mi?">
                    <input type="hidden" name="id" value="<?= (int) $silo['id'] ?>">
                    <button class="button button--small button--ghost" type="submit">Pasife Al</button>
                </form>
                <?php if ((float) $silo['current_stock_kg'] <= 0): ?>
                    <form action="/silos/destroy" method="post" class="inline-form" data-confirm="Bu boş silo kalıcı olarak silinecek. Emin misiniz?">
                        <input type="hidden" name="id" value="<?= (int) $silo['id'] ?>">
                        <button class="button button--small button--ghost" type="submit">Kalıcı Sil</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>
