<?php

declare(strict_types=1);

$alerts = [
    'not_found' => ['alert--danger', 'Girilen ticket kodu ile eşleşen barkod fişi bulunamadı.'],
    'invalid_status' => ['alert--danger', 'Bu barkod fişi bu aşamada boşaltım için kullanılamaz.'],
    'disabled' => ['alert--info', 'Silo boşaltım manuel işlem ekranı pasif. Araçlar barkod sonrası otomatik 2. tartıma alınır.'],
];
$alert = $alerts[$message] ?? null;
$formatKg = static fn (float|int|string|null $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (float|int|string|null $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$analysisRows = $ticket === null ? [] : [
    'Rutubet' => $ticket['moisture'],
    'Protein' => $ticket['protein'],
    'Hektolitre' => $ticket['hectoliter'],
    'Gluten' => $ticket['gluten'],
    'Süne oranı' => $ticket['sunn_pest_rate'],
    'Yabancı madde' => $ticket['foreign_material'],
    'Kırık tane' => $ticket['broken_grain'],
];
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Silo Boşaltım</h1>
        <p class="page-subtitle">Barkod ticket kodu ile yönlendirme bilgisini görüntüleyin. Boşaltım süreci harici operasyon tarafından yönetilir.</p>
    </div>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel filter-panel">
    <form action="/unloading-operations" method="get" class="plate-search">
        <label class="field">
            <span>Ticket kodu</span>
            <input type="text" name="code" value="<?= htmlspecialchars($code) ?>" placeholder="SVK-20260530-ABC123" autofocus required>
        </label>
        <button class="button button--primary" type="submit">Kodu Getir</button>
    </form>
</section>

<section class="panel">
    <div class="section-heading"><h2>Siloya Yönlendirilmiş Araçlar</h2></div>
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead><tr><th>Ticket</th><th>Plaka</th><th>Ürün</th><th>Gönderici</th><th>Hedef silo</th><th>İlk tartım</th><th>Durum</th><th class="table-actions">İşlem</th></tr></thead>
            <tbody>
            <?php if ($routedTickets === []): ?><tr><td colspan="8" class="empty-state">Siloya yönlendirilmiş araç yok.</td></tr><?php endif; ?>
                <?php foreach ($routedTickets as $row): ?>
                    <tr class="clickable-row" data-vehicle-entry-id="<?= (int) $row['entry_id'] ?>">
                        <td><strong><?= htmlspecialchars((string) $row['barcode']) ?></strong></td>
                        <td><?= htmlspecialchars((string) $row['plate_number']) ?></td>
                        <td><?= htmlspecialchars((string) $row['product_name']) ?></td>
                        <td><?= htmlspecialchars((string) $row['sender_name']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['silo_code'] . ' - ' . $row['silo_name'])) ?></td>
                        <td><?= htmlspecialchars($formatKg($row['first_weight_kg'])) ?><span class="table-muted"><?= htmlspecialchars($formatTon($row['first_weight_kg'])) ?></span></td>
                        <td><span class="badge badge--info"><?= htmlspecialchars((string) $row['display_status']) ?></span></td>
                        <td class="table-actions"><a class="button button--small button--primary" href="/unloading-operations?code=<?= urlencode((string) $row['barcode']) ?>">Seç</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($ticket !== null): ?>
    <section class="panel detail-panel">
        <div class="detail-header">
            <div>
                <div class="detail-kicker">Barkod Fişi</div>
                <h2><?= htmlspecialchars($ticket['barcode']) ?></h2>
            </div>
            <span class="badge badge--info">2. tartım bekliyor</span>
        </div>

        <div class="detail-grid">
            <div>
                <span>Plaka</span>
                <strong><?= htmlspecialchars($ticket['plate_number']) ?></strong>
            </div>
            <div>
                <span>Firma</span>
                <strong><?= htmlspecialchars($ticket['company_name']) ?></strong>
            </div>
            <div>
                <span>Ürün</span>
                <strong><?= htmlspecialchars($ticket['product_name']) ?></strong>
            </div>
            <div>
                <span>Şoför</span>
                <strong><?= htmlspecialchars((string) ($ticket['driver_name'] ?? '-')) ?></strong>
            </div>
            <div>
                <span>İlk Tartım</span>
                <strong><?= htmlspecialchars($formatKg($ticket['first_weight_kg'])) ?></strong>
                <span class="table-muted"><?= htmlspecialchars($formatTon($ticket['first_weight_kg'])) ?></span>
            </div>
            <div>
                <span>Hedef Silo</span>
                <strong><?= htmlspecialchars($ticket['silo_code'] . ' - ' . $ticket['silo_name']) ?></strong>
            </div>
            <div>
                <span>Kantar Fişi</span>
                <strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong>
            </div>
            <div>
                <span>Barkod Durumu</span>
                <strong><?= htmlspecialchars($ticket['barcode_status']) ?></strong>
            </div>
        </div>

        <h2 class="subsection-title">Analiz Bilgileri</h2>
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
            <p><?= htmlspecialchars((string) ($ticket['analysis_notes'] ?? '-')) ?></p>
        </div>

        <div class="operation-info-banner">
            <strong>Araç siloya yönlendirildi.</strong>
            <span>Boşaltım manuel olarak bu ekrandan yönetilmez. Araç barkod basımı sonrası 2. tartım kuyruğuna otomatik alınır.</span>
            <a class="button button--small button--primary" href="/second-weighing?record_id=<?= (int) $ticket['weighbridge_record_id'] ?>">2. Tartım Ekranına Git</a>
        </div>

        <?php if ($operation !== null): ?>
            <p class="operation-note">
                Başlangıç: <?= htmlspecialchars((string) ($operation['started_at'] ?? '-')) ?>,
                Bitiş: <?= htmlspecialchars((string) ($operation['completed_at'] ?? '-')) ?>
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>
