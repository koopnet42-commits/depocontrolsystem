<?php

declare(strict_types=1);

use App\Services\SettingsService;

$companySettings = SettingsService::company();
?>
<div class="print-letterhead">
    <?php if (! empty($companySettings['logo_path'])): ?>
        <img src="<?= htmlspecialchars($companySettings['logo_path']) ?>" alt="Firma logosu">
    <?php endif; ?>
    <div>
        <h1><?= htmlspecialchars((string) ($companySettings['company_name'] ?? 'Depo Otomasyon Sistemi')) ?></h1>
        <p><?= htmlspecialchars((string) ($companySettings['address'] ?? '')) ?></p>
        <p>
            Vergi Dairesi: <?= htmlspecialchars((string) ($companySettings['tax_office'] ?? '-')) ?> /
            Vergi No: <?= htmlspecialchars((string) ($companySettings['tax_number'] ?? '-')) ?>
        </p>
        <p>Telefon: <?= htmlspecialchars((string) ($companySettings['phone'] ?? '-')) ?></p>
    </div>
</div>
