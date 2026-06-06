<?php

declare(strict_types=1);

$analysisRows = [
    'Rutubet' => $record['moisture'],
    'Protein' => $record['protein'],
    'Hektolitre' => $record['hectoliter'],
    'Gluten' => $record['gluten'],
    'Süne oranı' => $record['sunn_pest_rate'],
    'Yabancı madde' => $record['foreign_material'],
    'Kırık tane' => $record['broken_grain'],
];
$formatKg = static fn (float|int|string|null $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (float|int|string|null $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$senderType = ($record['sender_type'] ?? 'company') === 'person' ? 'Şahıs Ürünü' : 'Firma Ürünü';
?>
<header class="page-header print-hidden">
    <div>
        <h1 class="page-title">Barkod / Karekod Yönlendirme Fişi</h1>
        <p class="page-subtitle">Fiş ayrı ekranda açıldı; ana operasyon ekranından çalışmaya devam edebilirsiniz.</p>
    </div>
    <div class="print-toolbar">
        <a class="button button--ghost" href="/barcode-tickets?record_id=<?= (int) $record['weighbridge_record_id'] ?>">Barkod Listesi</a>
        <a class="button" href="/unloading-operations?code=<?= urlencode((string) $record['barcode']) ?>">Yönlendirme Bilgisi</a>
        <a class="button" href="/second-weighing?record_id=<?= (int) $record['weighbridge_record_id'] ?>">2. Tartıma Git</a>
        <button class="button button--primary" type="button" onclick="window.print()">Yazdır</button>
    </div>
</header>

<section class="print-ticket">
    <?php require BASE_PATH . '/app/Views/components/print_header.php'; ?>
    <div class="print-ticket__header">
        <div>
            <h1>Barkod / Karekod Yönlendirme Fişi</h1>
            <p>Barkod basıldıktan sonra araç siloya yönlendirilir ve 2. tartım kuyruğuna alınır.</p>
        </div>
        <div class="print-ticket__date">
            <?= htmlspecialchars((string) ($record['issued_at'] ?? date('Y-m-d H:i:s'))) ?>
        </div>
    </div>

    <div class="barcode-box">
        <?= $barcodeSvg ?>
        <strong><?= htmlspecialchars($record['barcode']) ?></strong>
    </div>

    <div class="ticket-grid">
        <div>
            <span>Plaka</span>
            <strong><?= htmlspecialchars($record['plate_number']) ?></strong>
        </div>
        <div>
            <span>Gönderici Tipi</span>
            <strong><?= htmlspecialchars($senderType) ?></strong>
        </div>
        <div>
            <span><?= ($record['sender_type'] ?? 'company') === 'person' ? 'Ad Soyad' : 'Firma' ?></span>
            <strong><?= htmlspecialchars((string) (($record['sender_type'] ?? 'company') === 'person' ? ($record['sender_name'] ?? '-') : $record['company_name'])) ?></strong>
        </div>
        <div>
            <span><?= ($record['sender_type'] ?? 'company') === 'person' ? 'TC Kimlik No' : 'İrsaliye No' ?></span>
            <strong><?= htmlspecialchars((string) (($record['sender_type'] ?? 'company') === 'person' ? ($record['identity_number'] ?? '-') : ($record['dispatch_number'] ?? '-'))) ?></strong>
        </div>
        <div>
            <span>Ürün</span>
            <strong><?= htmlspecialchars($record['product_name']) ?></strong>
        </div>
        <div>
            <span>Şoför</span>
            <strong><?= htmlspecialchars((string) ($record['driver_name'] ?? '-')) ?></strong>
        </div>
        <div>
            <span>Şoför Telefon</span>
            <strong><?= htmlspecialchars((string) ($record['driver_phone'] ?? '-')) ?></strong>
        </div>
        <div>
            <span>İlk Tartım</span>
            <strong><?= htmlspecialchars($formatKg($record['first_weight_kg'])) ?></strong>
            <small><?= htmlspecialchars($formatTon($record['first_weight_kg'])) ?></small>
        </div>
        <div>
            <span>Sistem önerisi</span>
            <strong><?= htmlspecialchars($record['silo_code'] . ' - ' . $record['silo_name']) ?></strong>
        </div>
        <div>
            <span>Kullanıcı seçimi</span>
            <strong><?= htmlspecialchars($record['silo_code'] . ' - ' . $record['silo_name']) ?></strong>
        </div>
        <div>
            <span>Nihai yönlendirilen silo</span>
            <strong><?= htmlspecialchars($record['silo_code'] . ' - ' . $record['silo_name']) ?></strong>
        </div>
        <div>
            <span>Ticket kodu</span>
            <strong><?= htmlspecialchars($record['barcode']) ?></strong>
        </div>
        <div>
            <span>Kantar Fişi</span>
            <strong><?= htmlspecialchars($record['ticket_number']) ?></strong>
        </div>
    </div>

    <h2>Analiz Değerleri</h2>
    <div class="analysis-grid">
        <?php foreach ($analysisRows as $label => $value): ?>
            <div>
                <span><?= htmlspecialchars($label) ?></span>
                <strong><?= htmlspecialchars((string) ($value ?? '-')) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="ticket-note">
        <span>Numune açıklaması</span>
        <p><?= htmlspecialchars((string) ($record['notes'] ?? '-')) ?></p>
    </div>

    <div class="ticket-note">
        <span>Karekod JSON içeriği</span>
        <pre><?= htmlspecialchars((string) $qrPayload) ?></pre>
    </div>
</section>
