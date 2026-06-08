<?php

declare(strict_types=1);

$formatKg = static fn (mixed $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (mixed $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
?>
<header class="page-header print-hidden">
    <div>
        <h1 class="page-title">Ürün Çıkışı Dolum Barkodu</h1>
        <p class="page-subtitle">1. tartım sonrası basılan çıkış barkodu.</p>
    </div>
    <div class="print-toolbar">
        <a class="button button--ghost" href="/product-operations/entry?mode=outbound&outbound_id=<?= (int) $record['id'] ?>">Sürece Dön</a>
        <button class="button button--primary button--outbound" type="button" onclick="window.print()">Yazdır</button>
    </div>
</header>

<section class="print-ticket">
    <?php require BASE_PATH . '/app/Views/components/print_header.php'; ?>
    <div class="print-ticket__header">
        <div>
            <h1>Ürün Çıkışı Dolum Barkodu</h1>
            <p>Bu barkodla araç dolum/yükleme alanına alınır.</p>
        </div>
        <div class="print-ticket__date">
            <?= htmlspecialchars((string) ($record['outbound_barcode_issued_at'] ?? date('Y-m-d H:i:s'))) ?>
        </div>
    </div>

    <div class="barcode-box">
        <?= $barcodeSvg ?>
        <strong><?= htmlspecialchars((string) $record['outbound_barcode']) ?></strong>
    </div>

    <div class="ticket-grid">
        <div><span>Plaka</span><strong><?= htmlspecialchars((string) $record['plate_number']) ?></strong></div>
        <div><span>Alıcı</span><strong><?= htmlspecialchars((string) $record['sender_display']) ?></strong></div>
        <div><span>Ürün</span><strong><?= htmlspecialchars((string) $record['product_name']) ?></strong></div>
        <div><span>Kaynak silo</span><strong><?= htmlspecialchars((string) ($record['silo_code'] . ' - ' . $record['silo_name'])) ?></strong></div>
        <div><span>1. tartım</span><strong><?= htmlspecialchars($formatKg($record['first_weight_kg'])) ?></strong><small><?= htmlspecialchars($formatTon($record['first_weight_kg'])) ?></small></div>
        <div><span>Planlanan</span><strong><?= htmlspecialchars($formatKg($record['planned_quantity_kg'])) ?></strong></div>
        <div><span>İşlem no</span><strong><?= htmlspecialchars((string) $record['operation_number']) ?></strong></div>
        <div><span>Barkod</span><strong><?= htmlspecialchars((string) $record['outbound_barcode']) ?></strong></div>
    </div>
</section>
