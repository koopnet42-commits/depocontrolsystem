<?php

declare(strict_types=1);

$formatKg = static fn (float|int|string|null $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (float|int|string|null $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$senderType = ($record['sender_type'] ?? 'company') === 'person' ? 'Şahıs Ürünü' : 'Firma Ürünü';
$senderName = ($record['sender_type'] ?? 'company') === 'person' ? ($record['sender_name'] ?? '-') : ($record['company_name'] ?? '-');
$senderDocument = ($record['sender_type'] ?? 'company') === 'person' ? ($record['identity_number'] ?? '-') : ($record['dispatch_number'] ?? '-');
$reason = $rejectionReasons[$record['rejection_reason'] ?? ''] ?? ($record['rejection_reason'] ?? '-');
$analysisRows = [
    'Rutubet' => $record['moisture'] ?? null,
    'Protein' => $record['protein'] ?? null,
    'Hektolitre' => $record['hectoliter'] ?? null,
    'Gluten' => $record['gluten'] ?? null,
    'Süne oranı' => $record['sunn_pest_rate'] ?? null,
    'Yabancı madde' => $record['foreign_material'] ?? null,
    'Kırık tane' => $record['broken_grain'] ?? null,
];
?>
<header class="page-header print-hidden">
    <div>
        <h1 class="page-title">Ürün Alım Ret Tutanağı</h1>
        <p class="page-subtitle">Analiz sonucu alıma uygun olmayan ürün için yazdırılabilir tutanak.</p>
    </div>
    <button class="button button--primary" type="button" onclick="window.print()">Yazdır</button>
</header>

<section class="print-ticket">
    <?php require BASE_PATH . '/app/Views/components/print_header.php'; ?>

    <div class="print-ticket__header">
        <div>
            <h1>Ürün Alım Ret Tutanağı</h1>
            <p>Belge No: <?= htmlspecialchars((string) ($record['document_no'] ?? '-')) ?></p>
        </div>
        <div class="print-ticket__date">
            <?= htmlspecialchars((string) ($record['rejected_at'] ?? $record['analyzed_at'] ?? date('Y-m-d H:i:s'))) ?>
        </div>
    </div>

    <div class="ticket-grid">
        <div><span>Plaka</span><strong><?= htmlspecialchars((string) $record['plate_number']) ?></strong></div>
        <div><span>Gönderici Tipi</span><strong><?= htmlspecialchars($senderType) ?></strong></div>
        <div><span>Firma / Şahıs</span><strong><?= htmlspecialchars((string) $senderName) ?></strong></div>
        <div><span>İrsaliye / TC</span><strong><?= htmlspecialchars((string) $senderDocument) ?></strong></div>
        <div><span>Ürün</span><strong><?= htmlspecialchars((string) $record['product_name']) ?></strong></div>
        <div><span>Şoför</span><strong><?= htmlspecialchars((string) ($record['driver_name'] ?? '-')) ?></strong></div>
        <div>
            <span>İlk Tartım</span>
            <strong><?= htmlspecialchars($formatKg($record['first_weight_kg'])) ?></strong>
            <small><?= htmlspecialchars($formatTon($record['first_weight_kg'])) ?></small>
        </div>
        <div><span>Analiz Tarihi</span><strong><?= htmlspecialchars((string) ($record['analyzed_at'] ?? '-')) ?></strong></div>
        <div><span>Analizi Yapan</span><strong><?= htmlspecialchars((string) ($record['analyzed_by_name'] ?? '-')) ?></strong></div>
        <div><span>Durum</span><strong>Reddedildi / Alıma Girmedi</strong></div>
    </div>

    <h2>Analiz Değerleri</h2>
    <div class="analysis-grid">
        <?php foreach ($analysisRows as $label => $value): ?>
            <div><span><?= htmlspecialchars($label) ?></span><strong><?= htmlspecialchars((string) ($value ?? '-')) ?></strong></div>
        <?php endforeach; ?>
    </div>

    <div class="ticket-note">
        <span>Ret sebebi</span>
        <p><?= htmlspecialchars((string) $reason) ?></p>
    </div>

    <div class="ticket-note">
        <span>Açıklama</span>
        <p><?= htmlspecialchars((string) ($record['rejection_note'] ?: $record['notes'] ?: '-')) ?></p>
    </div>

    <div class="signature-grid">
        <div><span>Laboratuvar görevlisi</span></div>
        <div><span>Kantar görevlisi</span></div>
        <div><span>Araç şoförü / teslim eden</span></div>
    </div>
</section>
