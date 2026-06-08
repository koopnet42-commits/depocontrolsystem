<?php

declare(strict_types=1);

?>
<section class="panel operation-panel pre-notification-board" id="vehicle-monitor">
    <div class="section-heading section-heading--actions">
        <div>
            <h2>Ön Bildirimi Yapılmış Araçlar</h2>
            <span class="table-muted">Gelen ürün ve giden ürün planlarını yan yana takip edin.</span>
        </div>
    </div>
    <div class="pre-notification-columns">
        <div class="pre-notification-column pre-notification-column--inbound">
            <div class="pre-notification-column__header">
                <div>
                    <strong>Gelen Ürün</strong>
                    <span>Tesise ürün getirecek araçlar</span>
                </div>
            </div>
            <div class="pre-notification-list">
                <?php if (($incomingPreNotificationRows ?? []) === []): ?>
                    <div class="empty-state entry-empty">Gelen ürün ön bildirimi yok.</div>
                <?php endif; ?>
                <?php foreach (($incomingPreNotificationRows ?? []) as $notification): ?>
                    <?php $isDelayed = $isDelayedNotification($notification); ?>
                    <article class="pre-notification-item <?= htmlspecialchars($statusClass($notification)) ?>" data-row-detail="<?= (int) $notification['id'] ?>">
                        <div>
                            <strong><?= htmlspecialchars((string) ($notification['plate_number'] ?? '-')) ?></strong>
                            <span><?= htmlspecialchars((string) ($notification['product_name'] ?? '-')) ?></span>
                            <small><?= htmlspecialchars($senderName($notification)) ?></small>
                        </div>
                        <div>
                            <small>Tahmini geliş</small>
                            <b><?= htmlspecialchars((string) (($notification['expected_arrival_date'] ?? '') ?: '-')) ?></b>
                            <span class="badge <?= htmlspecialchars($statusBadgeClass($notification)) ?>"><?= htmlspecialchars($isDelayed ? 'Gecikmiş' : ($statusLabels[$notification['status']] ?? $notification['status'])) ?></span>
                        </div>
                        <div class="pre-notification-item__actions">
                            <a class="button button--small button--primary" href="/product-operations/entry?mode=inbound&notification_id=<?= (int) $notification['id'] ?>">Akış Başlat</a>
                            <button class="button button--small button--ghost" type="button" data-open-detail="<?= (int) $notification['id'] ?>">Detay</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pre-notification-column pre-notification-column--outbound">
            <div class="pre-notification-column__header">
                <div>
                    <strong>Giden Ürün</strong>
                    <span>Bizden ürün alacak araçlar</span>
                </div>
            </div>
            <div class="pre-notification-list">
                <?php if (($outboundPreNotificationRows ?? []) === []): ?>
                    <div class="empty-state entry-empty">Giden ürün ön bildirimi yok.</div>
                <?php endif; ?>
                <?php foreach (($outboundPreNotificationRows ?? []) as $notification): ?>
                    <article class="pre-notification-item <?= htmlspecialchars($outboundStatusClass($notification)) ?>" data-outbound-id="<?= (int) $notification['id'] ?>">
                        <div>
                            <strong><?= htmlspecialchars((string) ($notification['plate_number'] ?? '-')) ?></strong>
                            <span><?= htmlspecialchars((string) ($notification['product_name'] ?? '-')) ?></span>
                            <small><?= htmlspecialchars((string) ($notification['sender_display'] ?? '-')) ?></small>
                        </div>
                        <div>
                            <small>Planlanan</small>
                            <b><?= htmlspecialchars($formatKg($notification['planned_quantity_kg'] ?? 0)) ?></b>
                            <span class="badge <?= htmlspecialchars($outboundStatusBadgeClass($notification)) ?>"><?= htmlspecialchars($outboundStatusLabel((string) $notification['status'])) ?></span>
                        </div>
                        <div class="pre-notification-item__actions">
                            <a class="button button--small button--outbound" href="/product-operations/entry?mode=outbound&outbound_id=<?= (int) $notification['id'] ?>">Akış Başlat</a>
                            <a class="button button--small button--ghost" href="/product-operations/entry?mode=outbound&outbound_id=<?= (int) $notification['id'] ?>">Detay</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
