<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Services\SettingsService;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentQuery = $_SERVER['QUERY_STRING'] ?? '';
$currentUri = $currentPath . ($currentQuery === '' ? '' : '?' . $currentQuery);
$company = SettingsService::company();
$companyName = trim((string) ($company['company_name'] ?? '')) ?: 'Depo Otomasyon Sistemi';
$companyMeta = trim((string) ($company['license_number'] ?? '')) ?: trim((string) ($company['address'] ?? ''));
$isActiveRoute = static function (string $route) use ($currentPath, $currentUri): bool {
    if (str_contains($route, '?')) {
        return str_starts_with($currentUri, $route);
    }

    return $route === '/dashboard'
        ? $currentPath === '/dashboard' || $currentPath === '/'
        : str_starts_with($currentPath, $route);
};
$menuGroups = [
    ['title' => 'Dashboard', 'route' => '/dashboard'],
    [
        'title' => 'ÜRÜN',
        'children' => [
            ['title' => 'Ürün İşlemleri', 'route' => '/product-operations'],
            ['title' => 'Raporlar', 'route' => '/reports'],
        ],
    ],
    [
        'title' => 'Kantar İşlemleri',
        'children' => [
            ['title' => 'Kantar Ekranı', 'route' => '/weighbridge-entry'],
        ],
    ],
    [
        'title' => 'Analiz İşlemleri',
        'children' => [
            ['title' => 'Analiz Bekleyenler', 'route' => '/sample-analysis'],
            ['title' => 'Analiz Geçmişi', 'route' => '/sample-analysis'],
        ],
    ],
    [
        'title' => 'Silo İşlemleri',
        'children' => [
            ['title' => 'Silo Tanımları', 'route' => '/silos'],
            ['title' => 'Silo Yönlendirme', 'route' => '/silo-rules'],
            ['title' => 'Yönlendirme Bilgisi', 'route' => '/unloading-operations'],
        ],
    ],
    [
        'title' => 'Ayarlar',
        'children' => [
            ['title' => 'Firma Tanımları', 'route' => '/companies'],
            ['title' => 'Ürün Tanımları', 'route' => '/products'],
            ['title' => 'Firma Bilgileri', 'route' => '/settings?tab=company'],
            ['title' => 'Kamera Ayarları', 'route' => '/settings?tab=cameras'],
            ['title' => 'Kantar Ayarları', 'route' => '/settings?tab=scales'],
            ['title' => 'Bariyer Ayarları', 'route' => '/settings?tab=barriers'],
            ['title' => 'Sistem Ayarları', 'route' => '/settings?tab=system'],
            ['title' => 'Araç Süreç Onarım', 'route' => '/process-repair'],
        ],
    ],
    ['title' => 'Güvenlik / Kullanıcı Kurtarma', 'route' => '/security-recovery'],
];

$visibleMenuGroups = array_values(array_filter(array_map(static function (array $item): ?array {
    if (isset($item['children'])) {
        $children = Auth::filterMenu($item['children']);
        return $children === [] ? null : [...$item, 'children' => $children];
    }

    return Auth::canAccessPath($item['route']) ? $item : null;
}, $menuGroups)));
?>
<aside class="sidebar">
    <div class="sidebar-company">
        <div class="sidebar-company__logo">
            <?php if (! empty($company['logo_path'])): ?>
                <img src="<?= htmlspecialchars((string) $company['logo_path']) ?>" alt="Firma logosu">
            <?php else: ?>
                <span>DOS</span>
            <?php endif; ?>
        </div>
        <div class="sidebar-company__name"><?= htmlspecialchars($companyName) ?></div>
        <div class="sidebar-company__meta"><?= htmlspecialchars($companyMeta !== '' ? $companyMeta : Auth::roleLabel()) ?></div>
    </div>

    <nav class="menu" aria-label="Ana menü">
        <?php foreach ($visibleMenuGroups as $item): ?>
            <?php if (isset($item['children'])): ?>
                <?php
                $groupActive = false;
                foreach ($item['children'] as $child) {
                    if ($isActiveRoute($child['route'])) {
                        $groupActive = true;
                        break;
                    }
                }
                $groupKey = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $item['title']));
                ?>
                <div class="menu__group <?= $groupActive ? 'menu__group--active' : '' ?>" data-menu-group="<?= htmlspecialchars($groupKey) ?>">
                    <button class="menu__group-title" type="button" aria-expanded="<?= $groupActive ? 'true' : 'false' ?>">
                        <span><?= htmlspecialchars($item['title']) ?></span>
                        <span class="menu__chevron">›</span>
                    </button>
                    <div class="menu__children">
                    <?php foreach ($item['children'] as $child): ?>
                        <a
                            class="menu__item menu__item--child <?= $isActiveRoute($child['route']) ? 'menu__item--active' : '' ?>"
                            href="<?= htmlspecialchars($child['route']) ?>"
                        >
                            <?= htmlspecialchars($child['title']) ?>
                        </a>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <a
                    class="menu__item <?= $isActiveRoute($item['route']) ? 'menu__item--active' : '' ?>"
                    href="<?= htmlspecialchars($item['route']) ?>"
                >
                    <?= htmlspecialchars($item['title']) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-clock" aria-live="polite">
        <span id="sidebar-date">Tarih: --.--.----</span>
        <span id="sidebar-time">Saat: --:--:--</span>
        <span id="sidebar-day">Gün: --</span>
    </div>
</aside>
