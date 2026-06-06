<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Services\SettingsService;

$user = Auth::user();
$company = SettingsService::company();
$companyName = trim((string) ($company['company_name'] ?? '')) ?: 'Depo Otomasyon Sistemi';
$marqueeText = $companyName . ' - Depo Otomasyon Sistemi';
?>
<header class="topbar">
    <div>
        <span class="topbar__eyebrow">Lisanslı depoculuk işletme paneli</span>
        <strong><?= htmlspecialchars($title ?? 'Dashboard') ?></strong>
    </div>
    <div class="topbar__marquee" aria-label="Firma bilgisi">
        <span><?= htmlspecialchars($marqueeText) ?></span>
    </div>
    <div class="topbar__user">
        <span><?= htmlspecialchars($user['name'] ?? 'Kullanıcı') ?></span>
        <small><?= htmlspecialchars(Auth::roleLabel()) ?></small>
        <form action="/logout" method="post">
            <button class="button button--small" type="submit">Çıkış</button>
        </form>
    </div>
</header>
