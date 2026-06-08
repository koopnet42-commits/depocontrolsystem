<?php

declare(strict_types=1);

$screen = $screen ?? 'pre_notifications';
$isEntryScreen = $screen === 'entry';
$operationMode = $operationMode ?? ($isEntryScreen ? 'inbound' : 'inbound_pre');
$isOutboundMode = $isEntryScreen && in_array($operationMode, ['outbound', 'outbound_pre'], true);
$isPreNotificationMode = $isEntryScreen && in_array($operationMode, ['inbound_pre', 'outbound_pre'], true);
$transferNotification = $isEntryScreen && $operationMode === 'inbound' && is_array($selectedNotification ?? null) ? $selectedNotification : null;
$formatKg = static fn (mixed $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (mixed $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$toTonValue = static fn (mixed $kg): string => $kg === '' || $kg === null ? '' : rtrim(rtrim(number_format(((float) $kg) / 1000, 3, '.', ''), '0'), '.');
$senderLabel = static fn (array $row): string => ($row['sender_type'] ?? 'company') === 'person' ? 'Şahıs Ürünü' : 'Firma Ürünü';
$senderName = static fn (array $row): string => (string) (($row['sender_type'] ?? 'company') === 'person' ? ($row['sender_name'] ?? '-') : ($row['company_name'] ?? '-'));
$lastAction = static function (array $row) use ($histories): string {
    $items = $histories[(int) $row['id']] ?? [];
    $last = $items === [] ? null : end($items);

    return is_array($last) ? (string) ($last['action_name'] ?? '-') : '-';
};
$delaySeconds = static function (array $row): int {
    $status = (string) ($row['status'] ?? '');
    $expected = (string) ($row['expected_arrival_date'] ?? '');

    if (! in_array($status, ['pending', 'ürün_bildirimi'], true) || $expected === '') {
        return 0;
    }

    $timestamp = strtotime($expected);
    if ($timestamp === false) {
        return 0;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}($|\s+00:00:00$)/', $expected) === 1) {
        $timestamp = strtotime(substr($expected, 0, 10) . ' 23:59:59') ?: $timestamp;
    }

    return max(0, time() - $timestamp);
};
$delayText = static function (array $row) use ($delaySeconds): string {
    $seconds = $delaySeconds($row);

    if ($seconds <= 0) {
        return '';
    }

    $hours = (int) floor($seconds / 3600);
    if ($hours < 1) {
        return 'Belirlenen tarih geçti';
    }

    if ($hours < 24) {
        return $hours . ' saat gecikti';
    }

    return (int) floor($hours / 24) . ' gün gecikti';
};
$isDelayedNotification = static fn (array $row): bool => $delaySeconds($row) > 0;
$statusLabels = [
    'pending' => 'Beklemede',
    'ürün_bildirimi' => 'Henüz gelmemiş',
    'kantara_geldi' => 'İşlemde',
    'kantara_yonlendirildi' => 'İşlemde',
    'giriş_bariyeri_bekliyor' => 'İşlemde',
    'giriş_bariyeri_açıldı' => 'İşlemde',
    'kantarda' => 'İşlemde',
    'ilk_tartım_alındı' => 'İşlemde',
    'analiz_bekliyor' => 'İşlemde',
    'analizde' => 'İşlemde',
    'analiz_yapıldı' => 'İşlemde',
    'analiz_tamamlandı' => 'İşlemde',
    'silo_belirlendi' => 'İşlemde',
    'barkod_bekliyor' => 'İşlemde',
    'barkod_basıldı' => 'İşlemde',
    'siloya_yönlendirildi' => 'İşlemde',
    'boşaltımda' => 'İşlemde',
    'boşaltıldı' => 'İşlemde',
    'ikinci_tartım_bekliyor' => 'İşlemde',
    'tamamlandı' => 'Tamamlanmış',
    'iptal' => 'İptal',
    'ret' => 'Ret',
    'alıma_girmedi' => 'Ret',
    'at_weighbridge' => 'İşlemde',
    'in_analysis' => 'İşlemde',
    'directed_to_silo' => 'İşlemde',
    'unloaded' => 'İşlemde',
    'completed' => 'Tamamlanmış',
    'cancelled' => 'İptal',
];
$statusClass = static function (array $row): string {
    $status = (string) ($row['status'] ?? '');
    $expected = (string) ($row['expected_arrival_date'] ?? '');

    if (in_array($status, ['pending', 'ürün_bildirimi'], true) && $expected !== '') {
        $timestamp = strtotime($expected);
        if ($timestamp !== false) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}($|\s+00:00:00$)/', $expected) === 1) {
                $timestamp = strtotime(substr($expected, 0, 10) . ' 23:59:59') ?: $timestamp;
            }

            if (time() > $timestamp) {
                return 'operation-row-state operation-row-state--delayed';
            }
        }
    }

    if (in_array($status, ['tamamlandı', 'completed'], true)) {
        return 'operation-row-state operation-row-state--done';
    }

    if (in_array($status, ['ret', 'alıma_girmedi', 'iptal', 'cancelled'], true)) {
        return 'operation-row-state operation-row-state--rejected';
    }

    if (in_array($status, ['pending', 'ürün_bildirimi'], true)) {
        return 'operation-row-state operation-row-state--waiting';
    }

    return 'operation-row-state operation-row-state--active';
};
$statusBadgeClass = static function (array $row) use ($statusClass): string {
    $class = $statusClass($row);

    return str_contains($class, '--done') ? 'badge--success' : (str_contains($class, '--rejected') ? 'badge--danger' : (str_contains($class, '--delayed') ? 'badge--warning' : (str_contains($class, '--active') ? 'badge--warning' : 'badge--info')));
};
$waitingStatuses = ['pending', 'ürün_bildirimi'];
$activeProcessStatuses = [
    'kantara_geldi',
    'kantara_yonlendirildi',
    'giriş_bariyeri_bekliyor',
    'giriş_bariyeri_açıldı',
    'kantarda',
    'ilk_tartım_alındı',
    'analiz_bekliyor',
    'analizde',
    'analiz_yapıldı',
    'analiz_tamamlandı',
    'silo_belirlendi',
    'barkod_bekliyor',
    'barkod_basıldı',
    'siloya_yönlendirildi',
    'boşaltımda',
    'boşaltıldı',
    'ikinci_tartım_bekliyor',
    'at_weighbridge',
    'in_analysis',
    'directed_to_silo',
    'unloaded',
];
$entryPreNotifications = array_values(array_filter($notifications ?? [], static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), [...$waitingStatuses, ...$activeProcessStatuses], true)));
$incomingPreNotificationRows = array_values(array_filter($notifications ?? [], static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), $waitingStatuses, true) && (string) ($row['operation_type'] ?? 'PRODUCT_IN') !== 'PRODUCT_OUT'));
$activeProcessEntries = array_values(array_filter($incomingEntries ?? [], static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), $activeProcessStatuses, true)));
$outboundRecords = $outboundRecords ?? [];
$outboundHistories = $outboundHistories ?? [];
$outboundProcessCounts = $outboundProcessCounts ?? [];
$outboundWaitingStatuses = ['OUTBOUND_PRE_NOTIFIED'];
$outboundActiveStatuses = [
    'OUTBOUND_ARRIVED',
    'OUTBOUND_FIRST_WEIGHED',
    'OUTBOUND_LOADING_ASSIGNED_TO_SILO',
    'OUTBOUND_ANALYSIS_PENDING',
    'OUTBOUND_ANALYSIS_DONE',
    'OUTBOUND_SECOND_WEIGHING_WAITING',
];
$outboundPreEntries = array_values(array_filter($outboundRecords ?? [], static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), $outboundWaitingStatuses, true)));
$outboundPreNotificationRows = $outboundPreEntries;
$activeOutboundEntries = array_values(array_filter($outboundRecords ?? [], static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), $outboundActiveStatuses, true)));
$outboundLastAction = static function (array $row) use ($outboundHistories): string {
    $items = $outboundHistories[(int) $row['id']] ?? [];
    $last = $items === [] ? null : end($items);

    return is_array($last) ? (string) ($last['action_name'] ?? '-') : '-';
};
$outboundStageLabel = static function (string $status): string {
    if ($status === 'OUTBOUND_ARRIVED') {
        return '1. Tartım';
    }

    if ($status === 'OUTBOUND_FIRST_WEIGHED') {
        return 'Barkod';
    }

    if ($status === 'OUTBOUND_LOADING_ASSIGNED_TO_SILO') {
        return 'Dolum';
    }

    if (in_array($status, ['OUTBOUND_ANALYSIS_PENDING', 'OUTBOUND_ANALYSIS_DONE'], true)) {
        return 'Analiz';
    }

    if ($status === 'OUTBOUND_SECOND_WEIGHING_WAITING') {
        return '2. Tartım';
    }

    return 'Süreç';
};
$outboundStatusClass = static function (array $row): string {
    $status = (string) ($row['status'] ?? '');

    if ($status === 'OUTBOUND_COMPLETED') {
        return 'operation-row-state operation-row-state--done';
    }

    if ($status === 'OUTBOUND_REJECTED') {
        return 'operation-row-state operation-row-state--rejected';
    }

    if ($status === 'OUTBOUND_PRE_NOTIFIED') {
        return 'operation-row-state operation-row-state--waiting operation-row-state--outbound';
    }

    return 'operation-row-state operation-row-state--active operation-row-state--outbound';
};
$outboundStatusBadgeClass = static function (array $row) use ($outboundStatusClass): string {
    $class = $outboundStatusClass($row);

    return str_contains($class, '--done') ? 'badge--success' : (str_contains($class, '--rejected') ? 'badge--danger' : (str_contains($class, '--active') ? 'badge--danger' : 'badge--warning'));
};
$stageLabel = static function (string $status): string {
    if (in_array($status, ['kantara_geldi', 'kantara_yonlendirildi', 'giriş_bariyeri_bekliyor', 'giriş_bariyeri_açıldı', 'kantarda', 'at_weighbridge'], true)) {
        return 'Kantar';
    }

    if (in_array($status, ['ilk_tartım_alındı', 'analiz_bekliyor', 'analizde', 'analiz_yapıldı', 'analiz_tamamlandı', 'in_analysis'], true)) {
        return 'Analiz';
    }

    if (in_array($status, ['silo_belirlendi', 'barkod_bekliyor', 'barkod_basıldı', 'siloya_yönlendirildi', 'boşaltımda', 'boşaltıldı', 'directed_to_silo', 'unloaded'], true)) {
        return 'Silo';
    }

    if ($status === 'ikinci_tartım_bekliyor') {
        return '2. Tartım';
    }

    return 'Süreç';
};
$messages = [
    'saved' => ['alert--success', 'Kayıt oluşturuldu ve liste güncellendi.'],
    'updated' => ['alert--success', 'Kayıt güncellendi.'],
    'cancelled' => ['alert--success', 'Ön bildirim iptal edildi.'],
    'transferred' => ['alert--success', 'Ön bildirim gelen ürün girişine aktarıldı.'],
    'already_transferred' => ['alert--danger', 'Bu ön bildirim daha önce aktarılmış veya işlemde.'],
    'not_found' => ['alert--danger', 'Beklemede ön bildirim bulunamadı.'],
    'invalid' => ['alert--danger', 'Formdaki zorunlu alanları kontrol edin.'],
    'active_plate_exists' => ['alert--danger', 'Bu plaka için aktif bir süreç zaten var.'],
    'cancel_reason_required' => ['alert--danger', 'İptal gerekçesi zorunludur.'],
    'note_required' => ['alert--danger', 'Not alanı zorunludur.'],
    'company_notified' => ['alert--success', 'Firma haberdar edildi notu kaydedildi.'],
    'note_added' => ['alert--success', 'Not kaydedildi.'],
    'created' => ['alert--success', 'Ürün çıkışı kaydı oluşturuldu.'],
    'created_to_weighbridge' => ['alert--success', 'Ürün çıkışı kaydı oluşturuldu. Araç kantar ekranına aktarıldı.'],
    'first_saved' => ['alert--success', 'Ürün çıkışı 1. tartımı kaydedildi ve çıkış barkodu basıldı.'],
    'first_required' => ['alert--danger', 'Önce ürün çıkışı 1. tartımı kaydedilmelidir.'],
    'barcode_required' => ['alert--danger', 'Doluma göndermek için önce çıkış barkodu basılmalıdır.'],
    'silo_mismatch' => ['alert--danger', 'Seçilen silo ürün tipiyle uyumlu değil.'],
    'started' => ['alert--success', 'Çıkış ön bildirimi sürece aktarıldı. 1. tartım bekleniyor.'],
    'started_to_weighbridge' => ['alert--success', 'Çıkış ön bildirimi sürece aktarıldı. Araç kantar ekranına aktarıldı.'],
    'saved_to_weighbridge' => ['alert--success', 'Araç kaydı oluşturuldu. Araç kantar ekranına aktarıldı.'],
    'transferred_to_weighbridge' => ['alert--success', 'Ön bildirim akışa alındı. Araç kantar ekranına aktarıldı.'],
    'loading_assigned' => ['alert--success', 'Araç barkodla doluma gönderildi.'],
    'filling_done' => ['alert--success', 'Dolum tamamlandı. Araç analiz bekliyor.'],
    'analysis_done' => ['alert--success', 'Analiz tamamlandı. Araç 2. tartıma gönderilebilir.'],
    'analysis_rejected' => ['alert--danger', 'Analiz sonucu ret olarak kaydedildi. Çıkış süreci kapatıldı.'],
];
$outboundStatusLabel = static fn (string $status): string => [
    'OUTBOUND_PRE_NOTIFIED' => 'Çıkış ön bildirimi',
    'OUTBOUND_ARRIVED' => 'Araç geldi',
    'OUTBOUND_FIRST_WEIGHED' => 'Barkod basıldı',
    'OUTBOUND_LOADING_ASSIGNED_TO_SILO' => 'Doluma gönderildi',
    'OUTBOUND_ANALYSIS_PENDING' => 'Dolum tamamlandı / analiz bekliyor',
    'OUTBOUND_ANALYSIS_DONE' => 'Analiz tamamlandı',
    'OUTBOUND_SECOND_WEIGHING_WAITING' => '2. tartım bekliyor',
    'OUTBOUND_SECOND_WEIGHED' => '2. tartım alındı',
    'OUTBOUND_COMPLETED' => 'Tamamlandı',
    'OUTBOUND_REJECTED' => 'İptal / ret',
][$status] ?? $status;
$outboundOperationNumber = static fn (mixed $number): string => str_replace('OUT-', 'ÇIK-', (string) $number);
$operationModeBadge = static function (string $mode, bool $outbound): string {
    if ($outbound) {
        return $mode === 'outbound_pre' ? 'Ürün Çıkışı / Ön Bildirim' : 'Ürün Çıkışı / Doğrudan İşlem';
    }

    return $mode === 'inbound_pre' ? 'Ürün Girişi / Ön Bildirim' : 'Ürün Girişi / Doğrudan İşlem';
};
$companyJson = json_encode($companies, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$personJson = json_encode($personSenders ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$recordsJson = json_encode(array_values(array_column([...($waitingNotifications ?? []), ...($notifications ?? []), ...($incomingEntries ?? [])], null, 'id')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$historiesJson = json_encode($histories ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$validation = $validation ?? ['errors' => [], 'old' => [], 'general' => null];
$validationJson = json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$validationTargetModal = $validation['errors'] === []
    ? ''
    : (array_key_exists('planned_quantity_kg', $validation['old'] ?? []) || array_key_exists('quantity_ton', $validation['old'] ?? []) ? '' : 'notification-modal');
$oldInput = is_array($validation['old'] ?? null) ? $validation['old'] : [];
$oldValue = static fn (string $field, mixed $default = ''): string => htmlspecialchars((string) ($oldInput[$field] ?? $default));
$fieldError = static fn (string $field): string => isset($validation['errors'][$field]) ? '<small class="field-error">' . htmlspecialchars((string) $validation['errors'][$field]) . '</small>' : '';
$fieldClass = static fn (string $field): string => isset($validation['errors'][$field]) ? ' field--error' : '';
$messageData = $messages[$message ?? ''] ?? null;
$focusTarget = (string) ($_GET['focus'] ?? '');
$nextTarget = (string) ($_GET['next'] ?? '');
$nextWeighbridgeUrl = $focusTarget !== '' && str_starts_with($focusTarget, 'outbound-')
    ? '/weighbridge-entry?outbound_id=' . (int) substr($focusTarget, 9) . '&focus=' . urlencode($focusTarget) . '#outbound-first-weighing'
    : ($focusTarget !== '' && str_starts_with($focusTarget, 'entry-')
        ? '/weighbridge-entry?entry_id=' . (int) substr($focusTarget, 6) . '&focus=' . urlencode($focusTarget)
        : '/weighbridge-entry');
$returnTarget = $isEntryScreen ? 'product_operations_entry' : 'product_operations_pre_notifications';
$flowNavigationPermissionsJson = json_encode([
    'weighbridge' => \App\Core\Auth::canAccessPath('/weighbridge-entry'),
    'analysis' => \App\Core\Auth::canAccessPath('/sample-analysis'),
    'barcode' => \App\Core\Auth::canAccessPath('/barcode-tickets'),
    'unloading' => \App\Core\Auth::canAccessPath('/unloading-operations'),
    'second_weighing' => \App\Core\Auth::canAccessPath('/second-weighing'),
], JSON_UNESCAPED_UNICODE);
?>
<header class="page-header product-operation-header <?= $isOutboundMode ? 'operation-outbound' : 'operation-inbound' ?>">
    <div>
        <h1 class="page-title">Ürün İşlemleri</h1>
        <p class="page-subtitle">Ön bildirim, giriş, çıkış ve araç izleme süreçlerini tek ekrandan yönetin.</p>
    </div>
    <?php if (! $isEntryScreen): ?>
        <button class="button button--primary button--hero-action" type="button" data-open-modal="notification-modal" data-mode="create">
            <span class="button__icon" aria-hidden="true">+</span>
            Yeni Ön Bildirim
        </button>
    <?php endif; ?>
</header>

<?php if ($messageData !== null): ?>
    <div class="alert <?= htmlspecialchars($messageData[0]) ?> operation-alert">
        <span><?= htmlspecialchars($messageData[1]) ?></span>
        <?php if ($nextTarget === 'weighbridge'): ?>
            <a class="button button--small button--primary" href="<?= htmlspecialchars($nextWeighbridgeUrl) ?>">Kantar Ekranına Git</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="operation-type-switch <?= $isOutboundMode ? 'operation-outbound' : 'operation-inbound' ?>" aria-label="İşlem tipi">
    <a class="operation-type-card <?= in_array($operationMode, ['inbound_pre', 'outbound_pre'], true) ? 'is-active' : '' ?>" href="/product-operations/entry?mode=inbound_pre">
        <span class="operation-type-card__icon" aria-hidden="true">↓</span>
        <strong>Ön Bildirim</strong>
        <small>Tesise gelecek araç planı.</small>
    </a>
    <a class="operation-type-card <?= $operationMode === 'inbound' ? 'is-active' : '' ?>" href="/product-operations/entry?mode=inbound">
        <span class="operation-type-card__icon" aria-hidden="true">↓</span>
        <strong>Ürün Girişi</strong>
        <small>Dolu araç girer, boş araç çıkar.</small>
    </a>
    <a class="operation-type-card operation-type-card--out <?= $operationMode === 'outbound' ? 'is-active' : '' ?>" href="/product-operations/entry?mode=outbound">
        <span class="operation-type-card__icon" aria-hidden="true">↑</span>
        <strong>Ürün Çıkışı</strong>
        <small>Boş araç girer, dolu araç çıkar.</small>
    </a>
    <a class="operation-type-card operation-type-card--track <?= in_array($operationMode, ['inbound', 'outbound'], true) ? 'is-monitor' : '' ?>" href="#vehicle-monitor">
        <span class="operation-type-card__icon" aria-hidden="true">◎</span>
        <strong>Araç İzleme</strong>
        <small>Aktif giriş ve çıkış araçları.</small>
    </a>
</section>

<?php if ($isPreNotificationMode): ?>
    <section class="pre-mode-switch <?= $isOutboundMode ? 'pre-mode-switch--outbound' : 'pre-mode-switch--inbound' ?>" aria-label="Ön bildirim yönü">
        <a class="<?= $operationMode === 'inbound_pre' ? 'is-active' : '' ?>" href="/product-operations/entry?mode=inbound_pre">
            <span aria-hidden="true">↓</span>
            <strong>Gelen Ürün</strong>
            <small>Tesise ürün getirecek araç</small>
        </a>
        <a class="<?= $operationMode === 'outbound_pre' ? 'is-active' : '' ?>" href="/product-operations/entry?mode=outbound_pre">
            <span aria-hidden="true">↑</span>
            <strong>Giden Ürün</strong>
            <small>Bizden ürün alacak araç</small>
        </a>
    </section>
<?php endif; ?>

<div class="product-workspace product-workspace--operations <?= $isOutboundMode ? 'operation-outbound' : 'operation-inbound' ?>">
    <?php if ($isEntryScreen): ?>
        <?php if ($isOutboundMode): ?>
            <section class="panel operation-panel operation-form-card operation-form-card--outbound" id="outbound-form">
                <div class="operation-form-card__header">
                    <span class="operation-form-card__icon" aria-hidden="true">↑</span>
                    <div>
                        <h2>Ürün İşlem Formu</h2>
                        <p>Firma/şahıs, araç, ürün ve işlem bilgilerini aynı form düzeninde yönetin.</p>
                    </div>
                    <span class="badge badge--danger"><?= htmlspecialchars($operationModeBadge($operationMode, true)) ?></span>
                </div>
                <form action="/outbound-loadings/store" method="post" class="form-grid operation-form outbound-operation-form" data-driver-vehicle-form>
                    <input type="hidden" name="return_to" value="product_operations_entry">
                    <input type="hidden" name="operation_type" value="PRODUCT_OUT">
                    <input type="hidden" name="form_mode" value="<?= $operationMode === 'outbound_pre' ? 'PRE_NOTIFICATION' : 'DIRECT_ENTRY' ?>">
                    <input type="hidden" name="outbound_status" value="<?= $operationMode === 'outbound_pre' ? 'OUTBOUND_PRE_NOTIFIED' : 'OUTBOUND_ARRIVED' ?>">
                    <input type="hidden" name="vehicle_match_action" value="update">
                    <input type="hidden" name="driver_match_action" value="update">
                    <section class="form-section-card">
                        <h3>Firma / Şahıs</h3>
                        <div class="form-grid form-grid--section">
                            <div class="field field--sender-type"><span>Seçim</span><div class="sender-type-cards sender-type-cards--compact"><label><input type="radio" name="sender_type" value="company" <?= ($oldInput['sender_type'] ?? 'company') !== 'person' ? 'checked' : '' ?>><span class="sender-card-icon">F</span><strong>Firma</strong></label><label><input type="radio" name="sender_type" value="person" <?= ($oldInput['sender_type'] ?? 'company') === 'person' ? 'checked' : '' ?>><span class="sender-card-icon">S</span><strong>Şahıs</strong></label></div></div>
                            <label class="field sender-company<?= $fieldClass('company_name') ?>"><span>Firma</span><input type="search" name="company_name" list="op-company-list" value="<?= $oldValue('company_name') ?>"><input type="hidden" name="company_id" value="<?= $oldValue('company_id') ?>"><small class="field-help js-company-help">Mevcut firmalar önerilir; yoksa yeni firma bilgisiyle kaydedilir.</small><?= $fieldError('company_name') ?></label>
                            <label class="field sender-company<?= $fieldClass('dispatch_number') ?>"><span>Çıkış irsaliye no</span><input type="text" name="dispatch_number" value="<?= $oldValue('dispatch_number') ?>" placeholder="Otomatik ya da manuel"><?= $fieldError('dispatch_number') ?></label>
                            <label class="field sender-company"><span>Vergi no</span><input type="text" name="sender_tax_number" value="<?= $oldValue('sender_tax_number') ?>"></label>
                            <label class="field sender-person<?= $fieldClass('sender_name') ?>" hidden><span>Ad soyad</span><input type="search" name="sender_name" list="op-person-list" value="<?= $oldValue('sender_name') ?>" disabled><small class="field-help js-person-help">Mevcut şahıslar önerilir; yoksa yeni şahıs bilgisiyle kaydedilir.</small><?= $fieldError('sender_name') ?></label>
                            <label class="field sender-person<?= $fieldClass('identity_number') ?>" hidden><span>TC kimlik no</span><input type="text" name="identity_number" maxlength="11" inputmode="numeric" value="<?= $oldValue('identity_number') ?>" disabled><?= $fieldError('identity_number') ?></label>
                            <label class="field sender-contact"><span>Telefon</span><input type="text" name="sender_phone" value="<?= $oldValue('sender_phone') ?>"></label>
                            <label class="field field--wide sender-contact"><span>Adres</span><textarea name="sender_address" rows="2"><?= $oldValue('sender_address') ?></textarea></label>
                        </div>
                    </section>

                    <section class="form-section-card">
                        <h3>Araç ve Ürün</h3>
                        <div class="form-grid form-grid--section">
                            <label class="field<?= $fieldClass('plate_number') ?>"><span>Plaka</span><input type="text" name="plate_number" value="<?= $oldValue('plate_number') ?>" required><?= $fieldError('plate_number') ?></label>
                            <label class="field"><span>Şoför</span><input type="text" name="driver_name" value="<?= $oldValue('driver_name') ?>"></label>
                            <label class="field<?= $fieldClass('product_id') ?>">
                                <span>Ürün türü</span>
                                <select name="product_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= (int) $product['id'] ?>" <?= (string) ($oldInput['product_id'] ?? '') === (string) $product['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $product['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $fieldError('product_id') ?>
                            </label>
                            <label class="field<?= $fieldClass('source_silo_id') ?>">
                                <span>Kaynak silo</span>
                                <select name="source_silo_id" required data-product-silo-select>
                                    <option value="">Seçiniz</option>
                                    <?php foreach (($silos ?? []) as $silo): ?>
                                        <option value="<?= (int) $silo['id'] ?>" data-product-id="<?= (int) ($silo['product_id'] ?? 0) ?>" <?= (string) ($oldInput['source_silo_id'] ?? '') === (string) $silo['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) ($silo['code'] . ' - ' . $silo['name'] . ' / ' . ($silo['product_name'] ?? '-') . ' / ' . $formatTon($silo['current_stock_kg']))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="field-help">Sadece seçilen ürünle eşleşen silolar seçilebilir.</small>
                                <?= $fieldError('source_silo_id') ?>
                            </label>
                            <label class="field<?= $fieldClass('planned_quantity_kg') ?>"><span>Tahmini miktar kg</span><input type="number" name="planned_quantity_kg" min="1" step="1" value="<?= $oldValue('planned_quantity_kg') ?>" required><?= $fieldError('planned_quantity_kg') ?></label>
                            <label class="field field--wide"><span>Açıklama</span><textarea name="note" rows="3"><?= $oldValue('note') ?></textarea></label>
                            <div class="field--wide driver-vehicle-card" data-driver-vehicle-card hidden></div>
                        </div>
                    </section>
                    <div class="operation-stepper operation-stepper--outbound field--wide">
                        <span class="is-current">1. Araç Bilgileri</span><span>2. 1. Tartım</span><span>3. Silo / Yükleme</span><span>4. 2. Tartım</span><span>5. Tamamlandı</span>
                    </div>
                    <div class="net-formula net-formula--outbound field--wide">Net miktar = 2. tartım - 1. tartım. Silo stoğu azalır.</div>
                    <div class="form-actions field--wide"><button class="button button--primary button--outbound" type="submit"><?= $operationMode === 'outbound_pre' ? 'Çıkış Ön Bildirimi Oluştur' : 'Ürün Çıkışı Oluştur' ?></button></div>
                </form>
            </section>

            <?php if (($selectedOutboundRecord ?? null) !== null): ?>
                <section class="panel detail-panel operation-panel--outbound" data-focus-id="outbound-<?= (int) $selectedOutboundRecord['id'] ?>">
                    <div class="detail-header">
                        <div>
                            <div class="detail-kicker">Ürün Çıkışı / <?= htmlspecialchars($outboundStatusLabel((string) $selectedOutboundRecord['status'])) ?></div>
                            <h2><?= htmlspecialchars((string) $selectedOutboundRecord['plate_number']) ?></h2>
                        </div>
                        <span class="badge badge--danger">Ürün Çıkışı</span>
                    </div>
                    <div class="detail-grid">
                        <div><span>Alıcı</span><strong><?= htmlspecialchars((string) $selectedOutboundRecord['sender_display']) ?></strong></div>
                        <div><span>Ürün</span><strong><?= htmlspecialchars((string) $selectedOutboundRecord['product_name']) ?></strong></div>
                        <div><span>Kaynak silo</span><strong><?= htmlspecialchars((string) ($selectedOutboundRecord['silo_code'] . ' - ' . $selectedOutboundRecord['silo_name'])) ?></strong></div>
                        <div><span>Çıkış irsaliye no</span><strong><?= htmlspecialchars((string) ($selectedOutboundRecord['dispatch_number'] ?? '-')) ?></strong></div>
                        <div><span>Silo stok</span><strong><?= htmlspecialchars($formatKg($selectedOutboundRecord['current_stock_kg'])) ?></strong></div>
                        <div><span>Planlanan</span><strong><?= htmlspecialchars($formatKg($selectedOutboundRecord['planned_quantity_kg'])) ?></strong></div>
                        <div><span>1. tartım</span><strong><?= htmlspecialchars($selectedOutboundRecord['first_weight_kg'] === null ? '-' : $formatKg($selectedOutboundRecord['first_weight_kg'])) ?></strong></div>
                        <div><span>2. tartım</span><strong><?= htmlspecialchars($selectedOutboundRecord['second_weight_kg'] === null ? '-' : $formatKg($selectedOutboundRecord['second_weight_kg'])) ?></strong></div>
                        <div><span>Net çıkan ürün</span><strong><?= htmlspecialchars($selectedOutboundRecord['net_quantity_kg'] === null ? '-' : $formatKg($selectedOutboundRecord['net_quantity_kg'])) ?></strong></div>
                        <div><span>Çıkış barkodu</span><strong><?= htmlspecialchars((string) ($selectedOutboundRecord['outbound_barcode'] ?? '-')) ?></strong></div>
                    </div>
                    <div class="operation-row">
                        <?php $selectedOutboundStatus = (string) ($selectedOutboundRecord['status'] ?? ''); ?>
                        <?php if ($selectedOutboundStatus === 'OUTBOUND_PRE_NOTIFIED'): ?>
                            <form action="/outbound-loadings/start-arrived" method="post">
                                <input type="hidden" name="return_to" value="product_operations_entry">
                                <input type="hidden" name="id" value="<?= (int) $selectedOutboundRecord['id'] ?>">
                                <button class="button button--primary button--outbound" type="submit">Çıkış Akışını Başlat</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($selectedOutboundStatus === 'OUTBOUND_ARRIVED'): ?>
                            <a class="button button--primary button--outbound" href="/weighbridge-entry?outbound_id=<?= (int) $selectedOutboundRecord['id'] ?>&focus=outbound-<?= (int) $selectedOutboundRecord['id'] ?>#outbound-first-weighing">Kantar Ekranına Git</a>
                        <?php endif; ?>
                        <?php if ($selectedOutboundStatus === 'OUTBOUND_FIRST_WEIGHED'): ?>
                            <a class="button button--small button--outbound" target="_blank" href="/outbound-loadings/barcode-print?id=<?= (int) $selectedOutboundRecord['id'] ?>">Barkodu Yazdır</a>
                            <form action="/outbound-loadings/assign-silo" method="post">
                                <input type="hidden" name="return_to" value="product_operations_entry">
                                <input type="hidden" name="id" value="<?= (int) $selectedOutboundRecord['id'] ?>">
                                <button class="button button--primary button--outbound" type="submit">Barkodla Doluma Gönder</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($selectedOutboundStatus === 'OUTBOUND_LOADING_ASSIGNED_TO_SILO'): ?>
                            <form action="/outbound-loadings/filling-done" method="post">
                                <input type="hidden" name="return_to" value="product_operations_entry">
                                <input type="hidden" name="id" value="<?= (int) $selectedOutboundRecord['id'] ?>">
                                <button class="button button--primary button--outbound" type="submit">Dolum Tamamlandı</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($selectedOutboundStatus === 'OUTBOUND_ANALYSIS_PENDING'): ?>
                            <form action="/outbound-loadings/save-analysis" method="post" class="form-grid form-grid--section">
                                <input type="hidden" name="return_to" value="product_operations_entry">
                                <input type="hidden" name="id" value="<?= (int) $selectedOutboundRecord['id'] ?>">
                                <label class="field"><span>Analiz sonucu</span><select name="analysis_result"><option value="accepted">Uygun</option><option value="conditional">Şartlı uygun</option><option value="rejected">Ret</option></select></label>
                                <label class="field"><span>Analiz notu</span><input type="text" name="analysis_note" placeholder="Dolum sonrası analiz notu"></label>
                                <button class="button button--primary button--outbound" type="submit">Analizi Kaydet</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($selectedOutboundStatus === 'OUTBOUND_ANALYSIS_DONE'): ?>
                            <form action="/outbound-loadings/send-to-second-weighing" method="post">
                                <input type="hidden" name="return_to" value="product_operations_entry">
                                <input type="hidden" name="id" value="<?= (int) $selectedOutboundRecord['id'] ?>">
                                <button class="button button--primary button--outbound" type="submit">2. Tartıma Gönder</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($selectedOutboundStatus === 'OUTBOUND_SECOND_WEIGHING_WAITING'): ?>
                            <a class="button button--primary button--outbound" href="/second-weighing?outbound_id=<?= (int) $selectedOutboundRecord['id'] ?>">2. Tartım Ekranını Aç</a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($isPreNotificationMode): ?>
                <?php require __DIR__ . '/partials/pre_notification_board.php'; ?>
            <?php else: ?>
            <section class="panel operation-panel entry-board operation-panel--outbound" id="vehicle-monitor">
                <div class="section-heading section-heading--actions">
                    <div>
                        <h2>Aktif Çıkış Ön Bildirimleri</h2>
                        <span class="table-muted">Çıkışı planlanan ve henüz sürece alınmamış araçlar.</span>
                    </div>
                </div>
                <div class="entry-status-strip entry-status-strip--outbound" aria-label="Çıkış ön bildirim durum özeti">
                    <div><span>Ön bildirim</span><strong><?= (int) ($outboundProcessCounts['waiting'] ?? count($outboundPreEntries)) ?></strong></div>
                    <div><span>1. tartım bekleyen</span><strong><?= (int) ($outboundProcessCounts['first_weigh_waiting'] ?? 0) ?></strong></div>
                    <div><span>Yükleme alanı</span><strong><?= (int) (($outboundProcessCounts['loading_waiting'] ?? 0) + ($outboundProcessCounts['loading'] ?? 0)) ?></strong></div>
                    <div><span>Aktif süreç</span><strong><?= count($activeOutboundEntries) ?></strong></div>
                </div>
                <div class="entry-notification-list">
                    <?php if ($outboundPreEntries === []): ?>
                        <div class="empty-state entry-empty">Aktif çıkış ön bildirimi yok.</div>
                    <?php endif; ?>
                    <?php foreach ($outboundPreEntries as $notification): ?>
                        <article class="entry-notification-card <?= htmlspecialchars($outboundStatusClass($notification)) ?>" data-outbound-id="<?= (int) $notification['id'] ?>" data-focus-id="outbound-<?= (int) $notification['id'] ?>">
                            <div class="entry-card-main">
                                <strong class="entry-plate"><?= htmlspecialchars((string) ($notification['plate_number'] ?? '-')) ?></strong>
                                <span><?= htmlspecialchars((string) $notification['product_name']) ?></span>
                                <small><?= htmlspecialchars((string) $notification['sender_display']) ?></small>
                            </div>
                            <div class="entry-card-meta">
                                <span>Planlanan</span>
                                <strong><?= htmlspecialchars($formatKg($notification['planned_quantity_kg'])) ?></strong>
                                <small><?= htmlspecialchars($outboundLastAction($notification)) ?></small>
                            </div>
                            <div class="entry-card-state">
                                <span class="badge <?= htmlspecialchars($outboundStatusBadgeClass($notification)) ?>"><?= htmlspecialchars($outboundStatusLabel((string) $notification['status'])) ?></span>
                            </div>
                            <div class="entry-card-actions">
                                <a class="button button--small button--outbound" href="/product-operations/entry?mode=outbound&outbound_id=<?= (int) $notification['id'] ?>">Akış Başlat</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel operation-panel entry-board operation-panel--outbound">
                <div class="section-heading section-heading--actions">
                    <div>
                        <h2>Aktif Süreçteki Çıkış Araçları</h2>
                        <span class="table-muted">1. tartım, yükleme alanı ve 2. tartım adımlarındaki araçlar.</span>
                    </div>
                </div>
                <div class="active-process-grid">
                    <?php if ($activeOutboundEntries === []): ?>
                        <div class="empty-state entry-empty">Aktif süreçte çıkış aracı yok.</div>
                    <?php endif; ?>
                    <?php foreach ($activeOutboundEntries as $entry): ?>
                        <?php $outboundStage = $outboundStageLabel((string) $entry['status']); ?>
                        <article class="process-card <?= htmlspecialchars($outboundStatusClass($entry)) ?>" data-outbound-id="<?= (int) $entry['id'] ?>" data-focus-id="outbound-<?= (int) $entry['id'] ?>">
                            <div class="process-card__top">
                                <strong><?= htmlspecialchars((string) ($entry['plate_number'] ?? '-')) ?></strong>
                                <span><?= htmlspecialchars($outboundStage) ?></span>
                            </div>
                            <div class="process-card__body">
                                <span><?= htmlspecialchars((string) $entry['product_name']) ?></span>
                                <small><?= htmlspecialchars((string) $entry['sender_display']) ?></small>
                            </div>
                            <div class="process-card__status">
                                <span class="badge <?= htmlspecialchars($outboundStatusBadgeClass($entry)) ?>"><?= htmlspecialchars($outboundStatusLabel((string) $entry['status'])) ?></span>
                                <small><?= htmlspecialchars((string) ($entry['updated_at'] ?? $entry['created_at'] ?? '-')) ?></small>
                            </div>
                            <div class="process-card__flow process-card__flow--outbound">
                                <span class="<?= $outboundStage === '1. Tartım' ? 'is-current' : '' ?>">1. Tartım</span>
                                <span class="<?= in_array($outboundStage, ['Barkod', 'Dolum'], true) ? 'is-current' : '' ?>">Dolum</span>
                                <span class="<?= $outboundStage === 'Analiz' ? 'is-current' : '' ?>">Analiz</span>
                                <span class="<?= $outboundStage === '2. Tartım' ? 'is-current' : '' ?>">2. Tartım</span>
                            </div>
                            <div class="process-card__actions">
                                <?php if ((string) $entry['status'] === 'OUTBOUND_ARRIVED'): ?>
                                    <a class="button button--small button--outbound" href="/weighbridge-entry?outbound_id=<?= (int) $entry['id'] ?>&focus=outbound-<?= (int) $entry['id'] ?>#outbound-first-weighing">Kantar Ekranı</a>
                                <?php else: ?>
                                    <a class="button button--small button--outbound" href="/product-operations/entry?mode=outbound&outbound_id=<?= (int) $entry['id'] ?>">Süreci Aç</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php endif; ?>
        <?php else: ?>
            <?php if ($operationMode === 'inbound_pre'): ?>
                <section class="panel operation-panel operation-form-card operation-form-card--inbound" id="pre-form">
                    <div class="operation-form-card__header">
                        <span class="operation-form-card__icon" aria-hidden="true">↓</span>
                        <div>
                            <h2>Ürün İşlem Formu</h2>
                            <p>Firma/şahıs, araç, ürün ve işlem bilgilerini aynı form düzeninde yönetin.</p>
                        </div>
                        <span class="badge badge--success"><?= htmlspecialchars($operationModeBadge($operationMode, false)) ?></span>
                    </div>
                    <form action="/delivery-notifications/store" method="post" class="form-grid operation-form" data-driver-vehicle-form>
                        <input type="hidden" name="return_to" value="product_operations_entry">
                        <input type="hidden" name="operation_type" value="PRODUCT_IN">
                        <input type="hidden" name="form_mode" value="PRE_NOTIFICATION">
                        <input type="hidden" name="status" value="ürün_bildirimi">
                        <section class="form-section-card">
                            <h3>Firma / Şahıs</h3>
                            <div class="form-grid form-grid--section">
                                <div class="field field--sender-type"><span>Firma / şahıs</span><div class="sender-type-cards sender-type-cards--compact"><label><input type="radio" name="sender_type" value="company" checked><span class="sender-card-icon">F</span><strong>Firma</strong></label><label><input type="radio" name="sender_type" value="person"><span class="sender-card-icon">S</span><strong>Şahıs</strong></label></div></div>
                                <label class="field sender-company<?= $fieldClass('company_name') ?>"><span>Firma</span><input type="search" name="company_name" list="op-company-list"><input type="hidden" name="company_id"><small class="field-help js-company-help">Mevcut firmalar önerilir; yoksa yeni firma bilgisiyle kaydedilir.</small><?= $fieldError('company_name') ?></label>
                                <label class="field sender-company<?= $fieldClass('dispatch_number') ?>"><span>İrsaliye no (varsa)</span><input type="text" name="dispatch_number"><?= $fieldError('dispatch_number') ?></label>
                                <label class="field sender-company"><span>Vergi no</span><input type="text" name="sender_tax_number"></label>
                                <label class="field sender-person<?= $fieldClass('sender_name') ?>" hidden><span>Ad soyad</span><input type="search" name="sender_name" list="op-person-list" disabled><small class="field-help js-person-help">Daha önce gelen şahıslar önerilir.</small><?= $fieldError('sender_name') ?></label>
                                <label class="field sender-person<?= $fieldClass('identity_number') ?>" hidden><span>TC kimlik no</span><input type="text" name="identity_number" maxlength="11" inputmode="numeric" disabled><?= $fieldError('identity_number') ?></label>
                                <label class="field sender-contact"><span>Telefon</span><input type="text" name="sender_phone"></label>
                                <label class="field field--wide sender-contact"><span>Adres</span><textarea name="sender_address" rows="2"></textarea></label>
                            </div>
                        </section>
                        <section class="form-section-card">
                            <h3>Araç ve Ürün</h3>
                            <div class="form-grid form-grid--section">
                                <input type="hidden" name="vehicle_match_action" value="update">
                                <input type="hidden" name="driver_match_action" value="update">
                                <label class="field<?= $fieldClass('plate_number') ?>"><span>Plaka</span><input type="text" name="plate_number" required><?= $fieldError('plate_number') ?></label>
                                <label class="field"><span>Şoför</span><input type="text" name="driver_name"></label>
                                <label class="field"><span>Şoför telefon</span><input type="text" name="driver_phone"></label>
                                <label class="field<?= $fieldClass('product_id') ?>"><span>Ürün türü</span><select name="product_id" required><option value="">Seçiniz</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= htmlspecialchars((string) $product['name']) ?></option><?php endforeach; ?></select><?= $fieldError('product_id') ?></label>
                                <label class="field<?= $fieldClass('expected_quantity_ton') ?>"><span>Tahmini miktar ton</span><input type="number" name="expected_quantity_ton" min="0" step="0.001"><?= $fieldError('expected_quantity_ton') ?></label>
                                <label class="field"><span>Geliş tarihi</span><input type="date" name="expected_arrival_date" value="<?= date('Y-m-d') ?>"></label>
                                <label class="field field--wide"><span>Not</span><textarea name="notes" rows="3"></textarea></label>
                                <div class="field--wide driver-vehicle-card" data-driver-vehicle-card hidden></div>
                            </div>
                        </section>
                        <div class="operation-stepper operation-stepper--inbound">
                            <span class="is-current">1. Ön Bildirim</span><span>2. Girişe Aktar</span><span>3. Kantar</span><span>4. 2. Tartım</span><span>5. Tamamlandı</span>
                        </div>
                        <div class="form-actions"><button class="button button--primary" type="submit">Ön Bildirimi Kaydet</button></div>
                    </form>
                </section>
            <?php else: ?>
                <section class="panel operation-panel operation-form-card operation-form-card--inbound">
                    <div class="operation-form-card__header">
                        <span class="operation-form-card__icon" aria-hidden="true">↓</span>
                        <div>
                            <h2>Ürün İşlem Formu</h2>
                            <p>Firma/şahıs, araç, ürün ve işlem bilgilerini aynı form düzeninde yönetin.</p>
                        </div>
                        <span class="badge badge--success"><?= htmlspecialchars($operationModeBadge($operationMode, false)) ?></span>
                    </div>
                    <form action="<?= $transferNotification !== null ? '/incoming-products/start-pre-notified' : '/incoming-products/direct' ?>" method="post" class="form-grid operation-form js-direct-form" data-driver-vehicle-form>
                        <input type="hidden" name="return_to" value="product_operations_entry">
                        <input type="hidden" name="operation_type" value="PRODUCT_IN">
                        <input type="hidden" name="form_mode" value="DIRECT_ENTRY">
                        <?php if ($transferNotification !== null): ?>
                            <input type="hidden" name="notification_id" value="<?= (int) $transferNotification['id'] ?>">
                            <input type="hidden" name="arrival_date" value="<?= date('Y-m-d') ?>">
                        <?php endif; ?>
                        <section class="form-section-card">
                            <h3>Firma / Şahıs</h3>
                            <div class="form-grid form-grid--section">
                                <div class="field field--sender-type"><span>Seçim</span><div class="sender-type-cards sender-type-cards--compact"><label><input type="radio" name="sender_type" value="company" <?= ($transferNotification['sender_type'] ?? 'company') !== 'person' ? 'checked' : '' ?>><span class="sender-card-icon">F</span><strong>Firma</strong></label><label><input type="radio" name="sender_type" value="person" <?= ($transferNotification['sender_type'] ?? 'company') === 'person' ? 'checked' : '' ?>><span class="sender-card-icon">S</span><strong>Şahıs</strong></label></div></div>
                                <label class="field sender-company<?= $fieldClass('company_name') ?>"><span>Firma</span><input type="search" name="company_name" list="op-company-list" value="<?= htmlspecialchars((string) ($transferNotification['company_name'] ?? '')) ?>"><input type="hidden" name="company_id" value="<?= htmlspecialchars((string) ($transferNotification['company_id'] ?? '')) ?>"><small class="field-help js-company-help">Mevcut firmalar önerilir; yoksa yeni firma bilgisiyle kaydedilir.</small><?= $fieldError('company_name') ?></label>
                                <label class="field sender-company<?= $fieldClass('dispatch_number') ?>"><span>İrsaliye no</span><input type="text" name="dispatch_number" value="<?= htmlspecialchars((string) ($oldInput['dispatch_number'] ?? $transferNotification['dispatch_number'] ?? '')) ?>"><?= $fieldError('dispatch_number') ?></label>
                                <label class="field sender-company"><span>Vergi no</span><input type="text" name="sender_tax_number" value="<?= htmlspecialchars((string) ($transferNotification['sender_tax_number'] ?? '')) ?>"></label>
                                <label class="field sender-person<?= $fieldClass('sender_name') ?>" hidden><span>Ad soyad</span><input type="search" name="sender_name" list="op-person-list" value="<?= htmlspecialchars((string) ($transferNotification['sender_name'] ?? '')) ?>" disabled><small class="field-help js-person-help">Mevcut şahıslar önerilir.</small><?= $fieldError('sender_name') ?></label>
                                <label class="field sender-person<?= $fieldClass('identity_number') ?>" hidden><span>TC kimlik no</span><input type="text" name="identity_number" maxlength="11" inputmode="numeric" value="<?= htmlspecialchars((string) ($transferNotification['identity_number'] ?? '')) ?>" disabled><?= $fieldError('identity_number') ?></label>
                                <label class="field sender-contact"><span>Telefon</span><input type="text" name="sender_phone" value="<?= htmlspecialchars((string) ($transferNotification['sender_phone'] ?? '')) ?>"></label>
                                <label class="field field--wide sender-contact"><span>Adres</span><textarea name="sender_address" rows="2"><?= htmlspecialchars((string) ($transferNotification['sender_address'] ?? '')) ?></textarea></label>
                            </div>
                        </section>
                        <section class="form-section-card">
                            <h3>Araç ve Ürün</h3>
                            <div class="form-grid form-grid--section">
                                <input type="hidden" name="vehicle_match_action" value="update">
                                <input type="hidden" name="driver_match_action" value="update">
                                <label class="field<?= $fieldClass('plate_number') ?>"><span>Plaka</span><input type="text" name="plate_number" value="<?= htmlspecialchars((string) ($transferNotification['plate_number'] ?? '')) ?>" required><?= $fieldError('plate_number') ?></label>
                                <label class="field"><span>Şoför</span><input type="text" name="driver_name" value="<?= htmlspecialchars((string) ($transferNotification['driver_name'] ?? '')) ?>"></label>
                                <label class="field"><span>Şoför telefon</span><input type="text" name="driver_phone" value="<?= htmlspecialchars((string) ($transferNotification['driver_phone'] ?? '')) ?>"></label>
                                <label class="field<?= $fieldClass('product_id') ?>"><span>Ürün türü</span><select name="product_id" required><option value="">Seçiniz</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= (int) ($transferNotification['product_id'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $product['name']) ?></option><?php endforeach; ?></select><?= $fieldError('product_id') ?></label>
                                <label class="field<?= $fieldClass('quantity_ton') ?>"><span>Tahmini miktar ton</span><input type="number" name="quantity_ton" min="0" step="0.001" value="<?= htmlspecialchars($transferNotification !== null ? $toTonValue($transferNotification['expected_quantity_kg'] ?? '') : '') ?>" required><?= $fieldError('quantity_ton') ?></label>
                                <label class="field field--wide<?= $fieldClass('notes') ?>"><span>Açıklama</span><textarea name="<?= $transferNotification !== null ? 'entry_notes' : 'notes' ?>" rows="3" required><?= htmlspecialchars((string) ($transferNotification['notes'] ?? '')) ?></textarea><?= $fieldError('notes') ?></label>
                                <div class="field--wide driver-vehicle-card" data-driver-vehicle-card hidden></div>
                            </div>
                        </section>
                        <div class="operation-stepper operation-stepper--inbound">
                            <span class="is-current">1. Araç Bilgileri</span><span>2. 1. Tartım</span><span>3. Silo / Barkod</span><span>4. 2. Tartım</span><span>5. Tamamlandı</span>
                        </div>
                        <div class="net-formula net-formula--inbound">Net miktar = 1. tartım - 2. tartım. Silo stoğu artar.</div>
                        <div class="form-actions"><button class="button button--primary" type="submit"><?= $transferNotification !== null ? 'Akış Başlat' : 'Giriş Oluştur' ?></button></div>
                    </form>
                </section>
            <?php endif; ?>
        <?php if ($isPreNotificationMode): ?>
            <?php require __DIR__ . '/partials/pre_notification_board.php'; ?>
        <?php else: ?>
        <section class="panel operation-panel entry-board" id="vehicle-monitor">
            <div class="section-heading section-heading--actions">
                <div>
                    <h2>Aktif Ön Bildirimler</h2>
                    <span class="table-muted">Gelmesi beklenen ve akışı başlamış ön bildirimli araçlar.</span>
                </div>
            </div>

            <div class="entry-status-strip" aria-label="Ön bildirim durum özeti">
                <div><span>Gelmedi</span><strong><?= count(array_filter($entryPreNotifications, static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), $waitingStatuses, true))) ?></strong></div>
                <div><span>Geciken</span><strong><?= count(array_filter($entryPreNotifications, $isDelayedNotification)) ?></strong></div>
                <div><span>İşlem Başladı</span><strong><?= count(array_filter($entryPreNotifications, static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), $activeProcessStatuses, true))) ?></strong></div>
                <div><span>Aktif Süreç</span><strong><?= count($activeProcessEntries) ?></strong></div>
            </div>

            <div class="entry-notification-list">
                <?php if ($entryPreNotifications === []): ?>
                    <div class="empty-state entry-empty">Aktif ön bildirim yok.</div>
                <?php endif; ?>
                <?php foreach ($entryPreNotifications as $notification): ?>
                    <?php $isWaiting = in_array((string) $notification['status'], $waitingStatuses, true); ?>
                    <?php $isDelayed = $isDelayedNotification($notification); ?>
                    <article class="entry-notification-card <?= htmlspecialchars($statusClass($notification)) ?>" data-row-detail="<?= (int) $notification['id'] ?>" data-focus-id="entry-<?= (int) $notification['id'] ?>">
                        <div class="entry-card-main">
                            <strong class="entry-plate"><?= htmlspecialchars((string) ($notification['plate_number'] ?? '-')) ?></strong>
                            <span><?= htmlspecialchars((string) $notification['product_name']) ?></span>
                            <small><?= htmlspecialchars($senderName($notification)) ?></small>
                            <?php if ($isDelayed): ?>
                                <em class="delay-chip"><?= htmlspecialchars($delayText($notification)) ?></em>
                            <?php endif; ?>
                        </div>
                        <div class="entry-card-meta">
                            <span>Tahmini geliş</span>
                            <strong><?= htmlspecialchars((string) ($notification['expected_arrival_date'] ?: '-')) ?></strong>
                            <?php if ($isDelayed): ?>
                                <small>Belirlenen tarih geçti</small>
                            <?php endif; ?>
                            <small><?= htmlspecialchars($lastAction($notification)) ?></small>
                        </div>
                        <div class="entry-card-state">
                            <span class="badge <?= htmlspecialchars($statusBadgeClass($notification)) ?>"><?= htmlspecialchars($isDelayed ? 'Gecikmiş' : ($statusLabels[$notification['status']] ?? $notification['status'])) ?></span>
                        </div>
                        <div class="entry-card-actions">
                            <?php if ($isWaiting): ?>
                                <a class="button button--small button--primary" href="/product-operations/entry?mode=inbound&notification_id=<?= (int) $notification['id'] ?>">Akış Başlat</a>
                            <?php endif; ?>
                            <button class="button button--small button--ghost" type="button" data-open-detail="<?= (int) $notification['id'] ?>">Detay</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel operation-panel entry-board">
            <div class="section-heading section-heading--actions">
                <div>
                    <h2>Aktif Süreçteki Araçlar</h2>
                    <span class="table-muted">Kantar, analiz, silo ve ikinci tartım adımlarındaki araçlar.</span>
                </div>
            </div>
            <div class="active-process-grid">
                <?php if ($activeProcessEntries === []): ?>
                    <div class="empty-state entry-empty">Aktif süreçte araç yok.</div>
                <?php endif; ?>
                <?php foreach ($activeProcessEntries as $entry): ?>
                    <article class="process-card <?= htmlspecialchars($statusClass($entry)) ?>" data-row-detail="<?= (int) $entry['id'] ?>" data-focus-id="entry-<?= (int) $entry['id'] ?>">
                        <div class="process-card__top">
                            <strong><?= htmlspecialchars((string) ($entry['plate_number'] ?? '-')) ?></strong>
                            <span><?= htmlspecialchars($stageLabel((string) $entry['status'])) ?></span>
                        </div>
                        <div class="process-card__body">
                            <span><?= htmlspecialchars((string) $entry['product_name']) ?></span>
                            <small><?= htmlspecialchars($senderName($entry)) ?></small>
                        </div>
                        <div class="process-card__status">
                            <span class="badge <?= htmlspecialchars($statusBadgeClass($entry)) ?>"><?= htmlspecialchars($statusLabels[$entry['status']] ?? $entry['status']) ?></span>
                            <small><?= htmlspecialchars((string) ($entry['updated_at'] ?? $entry['created_at'] ?? '-')) ?></small>
                        </div>
                        <div class="process-card__flow">
                            <span class="<?= $stageLabel((string) $entry['status']) === 'Kantar' ? 'is-current' : '' ?>">Kantar</span>
                            <span class="<?= $stageLabel((string) $entry['status']) === 'Analiz' ? 'is-current' : '' ?>">Analiz</span>
                            <span class="<?= $stageLabel((string) $entry['status']) === 'Silo' ? 'is-current' : '' ?>">Silo</span>
                            <span class="<?= $stageLabel((string) $entry['status']) === '2. Tartım' ? 'is-current' : '' ?>">2. Tartım</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (! $isPreNotificationMode): ?>
        <section class="panel operation-panel second-waiting-panel">
            <div class="section-heading section-heading--actions">
                <div>
                    <h2>2. Tartım Bekleyen İşlemler</h2>
                    <span class="table-muted">Ürün girişleri yeşil, ürün çıkışları kırmızı etiketle gösterilir.</span>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table data-table--compact">
                    <thead><tr><th>Tip</th><th>Plaka</th><th>Firma / şahıs</th><th>Ürün</th><th>Silo</th><th>1. Tartım</th><th>Net</th><th>Durum</th><th class="table-actions">İşlem</th></tr></thead>
                    <tbody>
                        <?php if (($secondWeighingWaiting ?? []) === []): ?>
                            <tr><td colspan="9" class="empty-state">2. tartım bekleyen işlem yok.</td></tr>
                        <?php endif; ?>
                        <?php foreach (($secondWeighingWaiting ?? []) as $waiting): ?>
                            <?php $isOut = ($waiting['operation_type'] ?? 'PRODUCT_IN') === 'PRODUCT_OUT'; ?>
                            <tr class="<?= $isOut ? 'operation-row-state--outbound' : 'operation-row-state--inbound' ?>">
                                <td><span class="badge <?= $isOut ? 'badge--danger' : 'badge--success' ?>"><?= $isOut ? 'Çıkış' : 'Giriş' ?></span></td>
                                <td><strong><?= htmlspecialchars((string) $waiting['plate_number']) ?></strong><span class="table-muted"><?= htmlspecialchars($isOut ? $outboundOperationNumber($waiting['operation_number']) : (string) $waiting['operation_number']) ?></span></td>
                                <td><?= htmlspecialchars((string) $waiting['sender_display']) ?></td>
                                <td><?= htmlspecialchars((string) $waiting['product_name']) ?></td>
                                <td><?= htmlspecialchars((string) ($waiting['silo_code'] . ' - ' . $waiting['silo_name'])) ?></td>
                                <td><?= htmlspecialchars($formatKg($waiting['first_weight_kg'])) ?></td>
                                <td><?= $isOut ? '2. tartım - 1. tartım' : '1. tartım - 2. tartım' ?></td>
                                <td><span class="badge <?= $isOut ? 'badge--danger' : 'badge--success' ?>"><?= htmlspecialchars($isOut ? $outboundStatusLabel((string) $waiting['status']) : ($statusLabels[$waiting['status']] ?? (string) $waiting['status'])) ?></span></td>
                                <td class="table-actions">
                                    <a class="button button--small <?= $isOut ? 'button--outbound' : 'button--primary' ?>" href="<?= $isOut ? '/second-weighing?outbound_id=' . (int) $waiting['outbound_id'] : '/second-weighing?record_id=' . (int) $waiting['record_id'] ?>">2. Tartımı Aç</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    <?php else: ?>
        <section class="panel operation-panel">
            <div class="section-heading section-heading--actions">
                <div>
                    <h2>Ön Bildirim Listesi</h2>
                    <span class="table-muted">Duruma göre renklendirilmiş araç kayıtları ve hızlı işlemler.</span>
                </div>
            </div>
            <?php $notifications = $notifications ?? []; require __DIR__ . '/partials/notification_table.php'; ?>
        </section>
        <section class="panel operation-panel operation-panel--outbound">
            <div class="section-heading section-heading--actions">
                <div>
                    <h2>Ürün Çıkış Ön Bildirimleri</h2>
                    <span class="table-muted">Tesisten çıkacak ürün planları kırmızı etiketle listelenir.</span>
                </div>
                <a class="button button--small button--outbound" href="/product-operations/entry?mode=outbound_pre">Yeni Çıkış Ön Bildirimi</a>
            </div>
            <div class="table-wrap">
                <table class="data-table data-table--compact">
                    <thead><tr><th>İşlem No</th><th>Plaka</th><th>Alıcı</th><th>Ürün</th><th>Kaynak Silo</th><th>İrsaliye</th><th>Planlanan</th><th>Durum</th><th class="table-actions">İşlem</th></tr></thead>
                    <tbody>
                        <?php $outboundPreNotifications = array_values(array_filter($outboundRecords ?? [], static fn (array $row): bool => (string) ($row['status'] ?? '') === 'OUTBOUND_PRE_NOTIFIED')); ?>
                        <?php if ($outboundPreNotifications === []): ?>
                            <tr><td colspan="9" class="empty-state">Çıkış ön bildirimi yok.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($outboundPreNotifications as $row): ?>
                            <tr class="operation-row-state--outbound">
                                <td><strong><?= htmlspecialchars($outboundOperationNumber($row['operation_number'])) ?></strong><span class="badge badge--danger">Ürün Çıkışı</span></td>
                                <td><?= htmlspecialchars((string) $row['plate_number']) ?></td>
                                <td><?= htmlspecialchars((string) $row['sender_display']) ?></td>
                                <td><?= htmlspecialchars((string) $row['product_name']) ?></td>
                                <td><?= htmlspecialchars((string) ($row['silo_code'] . ' - ' . $row['silo_name'])) ?></td>
                                <td><?= htmlspecialchars((string) ($row['dispatch_number'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars($formatKg($row['planned_quantity_kg'])) ?></td>
                                <td><span class="badge badge--danger">Çıkış ön bildirimi</span></td>
                                <td class="table-actions">
                                    <a class="button button--small button--outbound" href="/product-operations/entry?mode=outbound&outbound_id=<?= (int) $row['id'] ?>">Aktar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<div class="modal-backdrop" data-modal-backdrop <?= $validationTargetModal === '' ? 'hidden' : '' ?>></div>

<dialog class="app-modal app-modal--wide" id="notification-modal" <?= $validationTargetModal === 'notification-modal' ? 'open' : '' ?>>
    <form action="/delivery-notifications/store" method="post" class="form-grid operation-form" id="notification-form" data-driver-vehicle-form>
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTarget) ?>">
        <input type="hidden" name="operation_type" value="PRODUCT_IN">
        <input type="hidden" name="form_mode" value="PRE_NOTIFICATION">
        <input type="hidden" name="id">
        <div class="modal-header">
            <h2 id="notification-modal-title">Yeni Ön Bildirim</h2>
            <button class="modal-close" type="button" data-close-modal>×</button>
        </div>
        <div class="alert alert--danger form-error-summary" hidden></div>

        <section class="form-section-card">
            <h3>Gönderici Bilgileri</h3>
            <div class="form-grid form-grid--section">
                <div class="field field--sender-type"><span>Gönderici tipi</span><div class="sender-type-cards sender-type-cards--compact"><label><input type="radio" name="sender_type" value="company" <?= ($oldInput['sender_type'] ?? 'company') !== 'person' ? 'checked' : '' ?>><span class="sender-card-icon">F</span><strong>Firma</strong></label><label><input type="radio" name="sender_type" value="person" <?= ($oldInput['sender_type'] ?? 'company') === 'person' ? 'checked' : '' ?>><span class="sender-card-icon">S</span><strong>Şahıs</strong></label></div></div>
                <label class="field sender-company<?= $fieldClass('company_name') ?>"><span>Firma seçimi</span><input type="search" name="company_name" value="<?= $oldValue('company_name') ?>" list="op-company-list"><input type="hidden" name="company_id" value="<?= $oldValue('company_id') ?>"><small class="field-help js-company-help">Firma adı yazıldığında mevcut kayıtlar önerilir.</small><?= $fieldError('company_name') ?></label>
                <label class="field sender-company"><span>Vergi no</span><input type="text" name="sender_tax_number" value="<?= $oldValue('sender_tax_number') ?>"></label>
                <label class="field sender-person<?= $fieldClass('sender_name') ?>" hidden><span>Ad soyad</span><input type="search" name="sender_name" value="<?= $oldValue('sender_name') ?>" list="op-person-list" disabled><small class="field-help js-person-help">Daha önce gelen şahıslar yazarken önerilir.</small><?= $fieldError('sender_name') ?></label>
                <label class="field sender-person<?= $fieldClass('identity_number') ?>" hidden><span>TC kimlik no</span><input type="text" name="identity_number" value="<?= $oldValue('identity_number') ?>" maxlength="11" inputmode="numeric" disabled><?= $fieldError('identity_number') ?></label>
                <label class="field sender-contact"><span>Telefon</span><input type="text" name="sender_phone" value="<?= $oldValue('sender_phone') ?>"></label>
                <label class="field field--wide sender-contact"><span>Adres</span><textarea name="sender_address" rows="2"><?= $oldValue('sender_address') ?></textarea></label>
            </div>
        </section>

        <section class="form-section-card">
            <h3>Ürün Bilgileri</h3>
            <div class="form-grid form-grid--section">
                <label class="field<?= $fieldClass('product_id') ?>"><span>Ürün tipi</span><select name="product_id" required><option value="">Seçiniz</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= (string) ($oldInput['product_id'] ?? '') === (string) $product['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $product['name']) ?></option><?php endforeach; ?></select><?= $fieldError('product_id') ?></label>
                <label class="field<?= $fieldClass('expected_quantity_ton') ?>"><span>Miktar ton</span><input type="number" name="expected_quantity_ton" value="<?= $oldValue('expected_quantity_ton') ?>" min="0" step="0.001"><?= $fieldError('expected_quantity_ton') ?></label>
                <label class="field sender-company<?= $fieldClass('dispatch_number') ?>"><span>İrsaliye no (varsa)</span><input type="text" name="dispatch_number" value="<?= $oldValue('dispatch_number') ?>"><?= $fieldError('dispatch_number') ?></label>
                <label class="field field--wide"><span>Açıklama</span><textarea name="notes" rows="3"><?= $oldValue('notes') ?></textarea></label>
            </div>
        </section>

        <section class="form-section-card">
            <h3>Araç Bilgileri</h3>
            <div class="form-grid form-grid--section">
                <input type="hidden" name="vehicle_match_action" value="update">
                <input type="hidden" name="driver_match_action" value="update">
                <label class="field<?= $fieldClass('plate_number') ?>"><span>Plaka</span><input type="text" name="plate_number" value="<?= $oldValue('plate_number') ?>" placeholder="42 ABC 123" required><?= $fieldError('plate_number') ?></label>
                <label class="field"><span>Araç markası</span><input type="text" name="vehicle_brand" value="<?= $oldValue('vehicle_brand') ?>"></label>
                <label class="field"><span>Araç modeli</span><input type="text" name="vehicle_model" value="<?= $oldValue('vehicle_model') ?>"></label>
                <label class="field"><span>Şoför adı</span><input type="text" name="driver_name" value="<?= $oldValue('driver_name') ?>"></label>
                <label class="field"><span>Şoför telefon</span><input type="text" name="driver_phone" value="<?= $oldValue('driver_phone') ?>"></label>
                <label class="field"><span>Şoför TC kimlik no</span><input type="text" name="driver_identity_number" value="<?= $oldValue('driver_identity_number') ?>" maxlength="11" inputmode="numeric"></label>
                <div class="field--wide driver-vehicle-card" data-driver-vehicle-card hidden></div>
            </div>
        </section>

        <section class="form-section-card">
            <h3>Tarih Bilgileri</h3>
            <div class="form-grid form-grid--section">
                <label class="field"><span>Yükleme tarihi</span><input type="date" name="loading_date" value="<?= $oldValue('loading_date') ?>"></label>
                <label class="field"><span>Tahmini geliş tarihi</span><input type="date" name="expected_arrival_date" value="<?= $oldValue('expected_arrival_date') ?>"></label>
            </div>
        </section>

        <input type="hidden" name="status" value="ürün_bildirimi">
        <div class="form-actions"><button class="button button--primary" type="submit">Kaydet</button><button class="button button--ghost" type="button" data-close-modal>Vazgeç</button></div>
    </form>
</dialog>

<dialog class="app-modal" id="cancel-modal">
    <form action="/delivery-notifications/cancel" method="post" class="form-grid">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTarget) ?>">
        <input type="hidden" name="id">
        <div class="modal-header"><h2>Ön Bildirimi İptal Et</h2><button class="modal-close" type="button" data-close-modal>×</button></div>
        <label class="field field--wide"><span>İptal gerekçesi</span><input type="text" name="cancel_reason" required></label>
        <label class="field field--wide"><span>İptal notu</span><textarea name="cancel_note" rows="4"></textarea></label>
        <div class="form-actions"><button class="button button--danger" type="submit">İptal Et</button><button class="button button--ghost" type="button" data-close-modal>Vazgeç</button></div>
    </form>
</dialog>

<dialog class="app-modal" id="followup-modal">
    <form action="/delivery-notifications/notify-company" method="post" class="form-grid">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTarget) ?>">
        <input type="hidden" name="id">
        <div class="modal-header"><h2>Firma Haberdar Edildi</h2><button class="modal-close" type="button" data-close-modal>×</button></div>
        <label class="field field--wide"><span>Görüşme notu</span><textarea name="note" rows="4" placeholder="Firma ile görüşüldü, araç yarın gelecek." required></textarea></label>
        <div class="form-actions"><button class="button button--primary" type="submit">Kaydet</button><button class="button button--ghost" type="button" data-close-modal>Vazgeç</button></div>
    </form>
</dialog>

<dialog class="app-modal" id="transfer-modal">
    <form action="/incoming-products/start-pre-notified" method="post" class="form-grid">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTarget) ?>">
        <input type="hidden" name="notification_id">
        <div class="modal-header"><h2>Akış Başlat</h2><button class="modal-close" type="button" data-close-modal>×</button></div>
        <div class="detail-grid field--wide js-transfer-summary"></div>
        <label class="field"><span>Gerçek geliş tarihi</span><input type="date" name="arrival_date" value="<?= date('Y-m-d') ?>"></label>
        <label class="field field--wide"><span>Giriş açıklaması</span><textarea name="entry_notes" rows="3"></textarea></label>
        <div class="form-actions"><button class="button button--primary" type="submit">Akış Başlat</button><button class="button button--ghost" type="button" data-close-modal>Vazgeç</button></div>
    </form>
</dialog>

<dialog class="app-modal" id="detail-modal">
    <div class="modal-header"><h2>Kayıt Detayı</h2><button class="modal-close" type="button" data-close-modal>×</button></div>
    <div class="detail-grid js-detail-body"></div>
</dialog>

<datalist id="op-company-list"><?php foreach ($companies as $company): ?><option value="<?= htmlspecialchars((string) $company['name']) ?>"></option><?php endforeach; ?></datalist>
<datalist id="op-person-list"><?php foreach (($personSenders ?? []) as $person): ?><option value="<?= htmlspecialchars((string) $person['sender_name']) ?>"></option><?php endforeach; ?></datalist>

<script>
const opCompanies = <?= $companyJson ?: '[]' ?>;
const opPeople = <?= $personJson ?: '[]' ?>;
const opNotifications = <?= $recordsJson ?: '[]' ?>;
const opHistories = <?= $historiesJson ?: '{}' ?>;
const opValidation = <?= $validationJson ?: '{"errors":[],"old":[],"general":null}' ?>;
const statusLabels = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>;
const flowNavigationPermissions = <?= $flowNavigationPermissionsJson ?: '{}' ?>;
const flowSteps = [
  ['Bildirim', ['pending', 'ürün_bildirimi']],
  ['Kantar Giriş', ['kantara_geldi', 'giriş_bariyeri_açıldı', 'kantarda']],
  ['1. Tartım', ['ilk_tartım_alındı', 'analiz_bekliyor']],
  ['Analiz', ['analizde', 'analiz_yapıldı', 'analiz_tamamlandı']],
  ['Silo Seçimi', ['silo_belirlendi']],
  ['Barkod', ['barkod_bekliyor', 'barkod_basıldı']],
  ['Silo Boşaltım', ['siloya_yönlendirildi', 'boşaltımda', 'boşaltıldı']],
  ['2. Tartım', ['ikinci_tartım_bekliyor', 'ikinci_tartım_alındı']],
  ['Tamamlandı', ['tamamlandı', 'completed']],
];
const tonValue = (kg) => ((Number(kg || 0) / 1000).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ton');
const normalizeOp = (value) => (value || '').toLocaleLowerCase('tr-TR').trim();
const findRecord = (id) => opNotifications.find((item) => Number(item.id) === Number(id));
const delayedSeconds = (record) => {
    if (!['pending', 'ürün_bildirimi'].includes(record?.status || '') || !record?.expected_arrival_date) return 0;
    let expected = new Date(record.expected_arrival_date);
    if (/^\d{4}-\d{2}-\d{2}($|\\s+00:00:00$)/.test(record.expected_arrival_date)) {
        expected = new Date(`${record.expected_arrival_date.slice(0, 10)}T23:59:59`);
    }
    const seconds = Math.floor((Date.now() - expected.getTime()) / 1000);
    return Number.isFinite(seconds) ? Math.max(0, seconds) : 0;
};
const delayedText = (record) => {
    const seconds = delayedSeconds(record);
    if (seconds <= 0) return '';
    const hours = Math.floor(seconds / 3600);
    if (hours < 1) return 'Belirlenen tarih geçti';
    if (hours < 24) return `${hours} saat gecikti`;
    return `${Math.floor(hours / 24)} gün gecikti`;
};
const isDelayedRecord = (record) => delayedSeconds(record) > 0;
const flowNavigationFor = (record, index) => {
    const entryId = Number(record.id || record.entry_id || 0);
    const recordId = Number(record.weighbridge_record_id || 0);
    const plate = encodeURIComponent(record.plate_number || '');
    const entryQuery = entryId ? `entry_id=${entryId}&entryId=${entryId}` : '';
    const plateQuery = plate ? `plate=${plate}` : '';
    const parts = (...items) => items.filter(Boolean).join('&');
    const withQuery = (path, query) => query ? `${path}?${query}` : path;
    const actions = {
        1: flowNavigationPermissions.weighbridge ? ['Kantar Ekranına Git', withQuery('/weighbridge-entry', parts(plateQuery, entryQuery))] : null,
        3: flowNavigationPermissions.analysis ? ['Analiz Ekranına Git', recordId ? `/sample-analysis/edit?record_id=${recordId}&${entryQuery}` : withQuery('/sample-analysis', parts(plateQuery, entryQuery))] : null,
        5: flowNavigationPermissions.barcode ? ['Barkod Ekranına Git', recordId ? `/barcode-tickets?record_id=${recordId}&${entryQuery}` : withQuery('/barcode-tickets', parts(plateQuery, entryQuery))] : null,
        6: flowNavigationPermissions.unloading ? ['Yönlendirme Bilgisi', withQuery('/unloading-operations', parts(plateQuery, entryQuery, recordId ? `record_id=${recordId}` : ''))] : null,
        7: flowNavigationPermissions.second_weighing ? ['2. Tartıma Git', withQuery('/second-weighing', parts(plateQuery, entryQuery, recordId ? `record_id=${recordId}` : ''))] : null,
    };

    return actions[index] || null;
};

function openDialog(id) {
    const dialog = document.getElementById(id);
    document.querySelector('[data-modal-backdrop]').hidden = false;
    if (!dialog.open) dialog.showModal();
}

function clearValidation(form) {
    form.querySelectorAll('.field--error').forEach((field) => field.classList.remove('field--error'));
    form.querySelectorAll('.field-error').forEach((error) => error.remove());
    const summary = form.querySelector('.form-error-summary');
    if (summary) {
        summary.hidden = true;
        summary.textContent = '';
    }
}

function resetFormState(form) {
    form.reset();
    clearValidation(form);
    syncSenderForm(form);
}

function closeDialogs() {
    document.querySelectorAll('dialog[open]').forEach((dialog) => {
        dialog.querySelectorAll('form').forEach(resetFormState);
        dialog.close();
    });
    document.querySelector('[data-modal-backdrop]').hidden = true;
}

function setFormValues(form, values) {
    Object.entries(values || {}).forEach(([name, value]) => {
        form.querySelectorAll(`[name="${CSS.escape(name)}"]`).forEach((field) => {
            if (field.type === 'radio' || field.type === 'checkbox') {
                field.checked = String(field.value) === String(value) || value === '1';
                return;
            }
            field.value = value ?? '';
        });
    });
}

function applyValidation(form, validation) {
    clearValidation(form);
    const errors = validation?.errors || {};
    const summary = form.querySelector('.form-error-summary');
    if (summary && (validation?.general || Object.keys(errors).length > 0)) {
        summary.textContent = validation?.general || 'Formdaki hatalı alanları kontrol edin.';
        summary.hidden = false;
    }
    Object.entries(errors).forEach(([fieldName, message]) => {
        const input = form.querySelector(`[name="${CSS.escape(fieldName)}"]`);
        if (!input) return;
        const wrapper = input.closest('.field') || input.parentElement;
        wrapper.classList.add('field--error');
        const error = document.createElement('small');
        error.className = 'field-error';
        error.textContent = message;
        wrapper.appendChild(error);
    });
}

function syncProductSilos(form) {
    const product = form.querySelector('select[name="product_id"]');
    const silo = form.querySelector('[data-product-silo-select]');
    if (!product || !silo) return;

    const productId = product.value;
    [...silo.options].forEach((option) => {
        if (option.value === '') return;
        const matches = option.dataset.productId === productId;
        option.disabled = productId !== '' && !matches;
        const base = option.textContent.replace(/\s+- bu ürüne uygun değil$/, '');
        option.textContent = option.disabled ? `${base} - bu ürüne uygun değil` : base;
    });
    if (silo.selectedOptions[0]?.disabled) {
        silo.value = '';
    }
}

function syncSenderForm(form) {
    const type = form.querySelector('input[name="sender_type"]:checked')?.value || 'company';
    const requiresDispatch = form.matches('[action*="incoming-products"]');
    form.querySelectorAll('.sender-company').forEach((el) => {
        el.hidden = type !== 'company';
        el.querySelectorAll('input, textarea, select').forEach((field) => {
            field.disabled = type !== 'company';
            field.required = type === 'company' && (
                field.name === 'company_name' || (requiresDispatch && field.name === 'dispatch_number')
            );
        });
    });
    form.querySelectorAll('.sender-person').forEach((el) => {
        el.hidden = type !== 'person';
        el.querySelectorAll('input, textarea, select').forEach((field) => {
            field.disabled = type !== 'person';
            field.required = type === 'person' && (
                field.name === 'sender_name' || (requiresDispatch && field.name === 'identity_number')
            );
        });
    });
}

function bindSenderLookup(form) {
    form.querySelectorAll('input[name="sender_type"]').forEach((radio) => radio.addEventListener('change', () => syncSenderForm(form)));
    const companyInput = form.querySelector('input[name="company_name"]');
    const companyId = form.querySelector('input[name="company_id"]');
    const tax = form.querySelector('input[name="sender_tax_number"]');
    const phone = form.querySelector('input[name="sender_phone"]');
    const address = form.querySelector('textarea[name="sender_address"]');
    const companyHelp = form.querySelector('.js-company-help');
    const personInput = form.querySelector('input[name="sender_name"]');
    const identity = form.querySelector('input[name="identity_number"]');
    const personHelp = form.querySelector('.js-person-help');
    const safeSet = (field, value, force = false) => {
        if (!field) return;
        const next = value || '';
        const previousAuto = field.dataset.autoFillValue || '';
        if (force || field.value.trim() === '' || field.value === previousAuto) {
            field.value = next;
            field.dataset.autoFillValue = next;
        }
    };
    const addCompanyOption = (record) => {
        if (!record?.name) return;
        if (!opCompanies.some((company) => String(company.id || '') === String(record.id || '') || normalizeOp(company.name) === normalizeOp(record.name))) {
            opCompanies.push(record);
        }
        const list = document.getElementById('op-company-list');
        if (list && !Array.from(list.options).some((option) => normalizeOp(option.value) === normalizeOp(record.name))) {
            const option = document.createElement('option');
            option.value = record.name;
            list.appendChild(option);
        }
    };
    const addPersonOption = (record) => {
        if (!record?.sender_name) return;
        if (!opPeople.some((person) => normalizeOp(person.sender_name) === normalizeOp(record.sender_name))) {
            opPeople.push(record);
        }
        const list = document.getElementById('op-person-list');
        if (list && !Array.from(list.options).some((option) => normalizeOp(option.value) === normalizeOp(record.sender_name))) {
            const option = document.createElement('option');
            option.value = record.sender_name;
            list.appendChild(option);
        }
    };
    const openCompanyRecordModal = () => {
        if (!companyInput || form.dataset.senderRecordModal === '1') return;
        const typed = companyInput.value.trim();
        if (typed === '' || opCompanies.some((company) => normalizeOp(company.name) === normalizeOp(typed))) return;
        form.dataset.senderRecordModal = '1';
        window.openSenderRecordModal?.({
            type: 'company',
            initial: {
                company_name: typed,
                sender_tax_number: tax?.value || '',
                sender_phone: phone?.value || '',
                sender_address: address?.value || '',
            },
            onSaved: (record) => {
                addCompanyOption(record);
                safeSet(companyInput, record.name, true);
                safeSet(companyId, record.id, true);
                safeSet(tax, record.tax_number || '', true);
                safeSet(phone, record.phone || '', true);
                safeSet(address, record.address || '', true);
                if (companyHelp) companyHelp.textContent = 'Yeni firma kaydedildi ve forma aktarıldı.';
                delete form.dataset.senderRecordModal;
            },
        });
        window.setTimeout(() => delete form.dataset.senderRecordModal, 400);
    };
    const openPersonRecordModal = () => {
        if (!personInput || form.dataset.senderRecordModal === '1') return;
        const typed = personInput.value.trim();
        if (typed === '' || opPeople.some((person) => normalizeOp(person.sender_name) === normalizeOp(typed))) return;
        form.dataset.senderRecordModal = '1';
        window.openSenderRecordModal?.({
            type: 'person',
            initial: {
                sender_name: typed,
                identity_number: identity?.value || '',
                sender_phone: phone?.value || '',
                sender_address: address?.value || '',
            },
            onSaved: (record) => {
                addPersonOption(record);
                safeSet(personInput, record.sender_name, true);
                safeSet(identity, record.identity_number || '', true);
                safeSet(phone, record.sender_phone || '', true);
                safeSet(address, record.sender_address || '', true);
                if (personHelp) personHelp.textContent = 'Yeni şahıs kaydedildi ve forma aktarıldı.';
                delete form.dataset.senderRecordModal;
            },
        });
        window.setTimeout(() => delete form.dataset.senderRecordModal, 400);
    };

    const syncCompanyMatchState = () => {
        if (!companyInput || !companyId) return;
        const match = opCompanies.find((company) => normalizeOp(company.name) === normalizeOp(companyInput.value));
        const isNewCompany = companyInput.value.trim() !== '' && !match;
        companyId.value = match?.id || '';
        form.querySelectorAll('.sender-company').forEach((el) => el.classList.toggle('is-new-company', isNewCompany));
        if (match) {
            safeSet(tax, match.tax_number || '');
            safeSet(phone, match.phone || '');
            safeSet(address, match.address || '');
            if (companyHelp) companyHelp.textContent = 'Mevcut firma seçildi.';
        } else if (companyHelp) {
            companyHelp.textContent = companyInput.value.trim()
                ? 'Firma bulunamadı. Alandan çıkınca yeni firma kaydı penceresi açılır.'
                : 'Firma adı yazıldığında mevcut kayıtlar önerilir; listede yoksa yeni firma olarak kaydedilir.';
        }
    };
    companyInput?.addEventListener('input', syncCompanyMatchState);
    companyInput?.addEventListener('blur', openCompanyRecordModal);
    syncCompanyMatchState();
    personInput?.addEventListener('input', () => {
        const match = opPeople.find((person) => normalizeOp(person.sender_name) === normalizeOp(personInput.value));
        if (match) {
            safeSet(identity, match.identity_number || '');
            safeSet(phone, match.sender_phone || '');
            safeSet(address, match.sender_address || '');
            if (personHelp) personHelp.textContent = 'Mevcut şahıs bilgileri getirildi.';
        } else {
            if (personHelp) personHelp.textContent = personInput.value.trim() ? 'Şahıs bulunamadı. Alandan çıkınca yeni şahıs kaydı penceresi açılır.' : 'Daha önce gelen şahıslar yazarken önerilir.';
        }
    });
    personInput?.addEventListener('blur', openPersonRecordModal);
    identity?.addEventListener('input', () => identity.value = identity.value.replace(/\D+/g, '').slice(0, 11));
    syncSenderForm(form);
    syncProductSilos(form);
    form.querySelector('select[name="product_id"]')?.addEventListener('change', () => syncProductSilos(form));
}

function fillNotificationForm(record = null) {
    const form = document.getElementById('notification-form');
    form.reset();
    clearValidation(form);
    form.action = record ? '/delivery-notifications/update' : '/delivery-notifications/store';
    document.getElementById('notification-modal-title').textContent = record ? 'Ön Bildirim Düzenle' : 'Yeni Ön Bildirim';
    form.elements.id.value = record?.id || '';
    if (!record) {
        syncSenderForm(form);
        return;
    }
    form.querySelector(`input[name="sender_type"][value="${record.sender_type || 'company'}"]`).checked = true;
    form.elements.company_id.value = record.company_id || '';
    form.elements.company_name.value = record.company_name || '';
    form.elements.dispatch_number.value = record.dispatch_number || '';
    form.elements.sender_tax_number.value = record.sender_tax_number || '';
    form.elements.sender_name.value = record.sender_name || '';
    form.elements.identity_number.value = record.identity_number || '';
    form.elements.sender_phone.value = record.sender_phone || '';
    form.elements.sender_address.value = record.sender_address || '';
    form.elements.product_id.value = record.product_id || '';
    form.elements.expected_quantity_ton.value = record.expected_quantity_kg ? (Number(record.expected_quantity_kg) / 1000) : '';
    form.elements.plate_number.value = record.plate_number || '';
    form.elements.vehicle_brand.value = record.vehicle_brand || '';
    if (form.elements.vehicle_model) form.elements.vehicle_model.value = record.vehicle_model || '';
    form.elements.driver_name.value = record.driver_name || '';
    form.elements.driver_phone.value = record.driver_phone || '';
    if (form.elements.driver_identity_number) form.elements.driver_identity_number.value = record.driver_identity_number || '';
    form.elements.loading_date.value = record.loading_date || '';
    form.elements.expected_arrival_date.value = record.expected_arrival_date || '';
    form.elements.notes.value = record.notes || '';
    syncSenderForm(form);
}

function summaryHtml(record) {
    const currentIndex = Math.max(0, flowSteps.findIndex(([, statuses]) => statuses.includes(record.status)));
    const delayed = isDelayedRecord(record);
    const cancelled = ['iptal', 'cancelled'].includes(record?.status || '');
    const cancelledWarning = cancelled ? `<div class="cancel-detail-banner field--wide">
        <strong>Bu ön bildirim iptal edildi.</strong>
        <span>İptal nedeni: ${record.cancel_reason || 'İptal nedeni kaydedilmemiş'}</span>
        <span>İptal açıklaması / notu: ${record.cancel_note || 'İptal açıklaması kaydedilmemiş'}</span>
        <span>İptal eden kullanıcı: ${record.cancelled_by_name || 'Kullanıcı bilgisi yok'}</span>
        <span>İptal tarihi ve saati: ${record.cancelled_at || 'İptal tarihi kaydedilmemiş'}</span>
    </div>` : '';
    const notifyInfo = (record.company_notified_at || record.company_notified_note || record.company_notified_by_name) ? `<div class="notify-detail-banner field--wide">
        <strong>Firma bilgilendirme bilgisi</strong>
        <span>Firma haberdar edildi mi: ${record.company_notified_at ? 'Evet' : 'Hayır'}</span>
        <span>Haberdar edilme notu: ${record.company_notified_note || '-'}</span>
        <span>Haberdar eden kullanıcı: ${record.company_notified_by_name || 'Kullanıcı bilgisi yok'}</span>
        <span>Haberdar edilme tarihi: ${record.company_notified_at || '-'}</span>
    </div>` : '';
    const delayedWarning = delayed ? `<div class="delay-detail-banner field--wide">
        <strong>Bu araç planlanan geliş tarihini geçti.</strong>
        <span>Tahmini geliş tarihi: ${record.expected_arrival_date || '-'}</span>
        <span>Gecikme: ${delayedText(record)}</span>
        <span>Firma haberdar edildi mi: ${record.company_notified_at ? 'Evet' : 'Hayır'}</span>
        <span>Son not: ${record.company_notified_note || record.notification_note || '-'}</span>
        <div class="delay-detail-actions">
            <button class="button button--small button--primary" type="button" data-open-followup="${record.id}">Firma Haberdar Edildi</button>
            <button class="button button--small button--ghost" type="button" data-open-cancel="${record.id}">Ön Bildirimi İptal Et</button>
        </div>
    </div>` : '';
    const flow = `<div class="field--wide flow-diagram">${flowSteps.map(([label], index) => {
        const state = ['iptal', 'cancelled', 'ret', 'alıma_girmedi'].includes(record.status) ? 'error' : (index < currentIndex ? 'done' : (index === currentIndex ? 'current' : 'waiting'));
        const nav = flowNavigationFor(record, index);
        const navButton = nav ? `<a class="flow-step-action" href="${nav[1]}" data-flow-nav>${nav[0]}</a>` : '';
        return `<div class="flow-step-wrap"><div class="flow-step flow-step--${state}"><span>${index + 1}</span><strong>${label}</strong></div>${navButton}</div>`;
    }).join('')}</div>`;
    const rows = [
        ['Bildirim', record.notification_number],
        ['Plaka', record.plate_number || '-'],
        ['Gönderici', record.sender_type === 'person' ? record.sender_name : record.company_name],
        ['Ürün', record.product_name],
        ['Miktar', tonValue(record.expected_quantity_kg)],
        ['Şoför', record.driver_name || '-'],
        ['Durum', statusLabels[record.status] || record.status],
        ['Açıklama', record.notes || '-'],
    ].map(([label, value]) => `<div><span>${label}</span><strong>${value || '-'}</strong></div>`).join('');
    const history = (opHistories[record.id] || []).map((item) => `<li><strong>${item.action_name}</strong><span>${item.old_status || '-'} -> ${item.new_status}</span><small>${item.created_at}</small></li>`).join('');
    return cancelledWarning + notifyInfo + delayedWarning + rows + flow + `<div class="field--wide process-history"><span>Durum geçmişi</span><ul>${history || '<li>Geçmiş kaydı yok.</li>'}</ul></div>`;
}

document.querySelectorAll('[data-open-modal="notification-modal"]').forEach((button) => button.addEventListener('click', () => {
    fillNotificationForm();
    openDialog('notification-modal');
}));
document.querySelectorAll('[data-edit-notification]').forEach((button) => button.addEventListener('click', (event) => {
    event.stopPropagation();
    fillNotificationForm(findRecord(button.dataset.editNotification));
    openDialog('notification-modal');
}));
document.querySelectorAll('[data-open-transfer]').forEach((button) => button.addEventListener('click', (event) => {
    event.stopPropagation();
    const record = findRecord(button.dataset.openTransfer);
    if (!record) return;
    document.querySelector('#transfer-modal input[name="notification_id"]').value = record.id;
    document.querySelector('.js-transfer-summary').innerHTML = summaryHtml(record);
    openDialog('transfer-modal');
}));
function openCancelModal(id) {
    const form = document.querySelector('#cancel-modal form');
    form.reset();
    form.elements.id.value = id;
    openDialog('cancel-modal');
}

function openFollowupModal(id) {
    const form = document.querySelector('#followup-modal form');
    form.reset();
    form.elements.id.value = id;
    openDialog('followup-modal');
}

document.querySelectorAll('[data-open-cancel]').forEach((button) => button.addEventListener('click', (event) => {
    event.stopPropagation();
    openCancelModal(button.dataset.openCancel);
}));
document.querySelectorAll('[data-open-followup]').forEach((button) => button.addEventListener('click', (event) => {
    event.stopPropagation();
    openFollowupModal(button.dataset.openFollowup);
}));
document.addEventListener('click', (event) => {
    const flowNav = event.target.closest('[data-flow-nav]');
    if (flowNav) {
        event.stopPropagation();
        return;
    }

    const followup = event.target.closest('[data-open-followup]');
    if (followup) {
        event.stopPropagation();
        openFollowupModal(followup.dataset.openFollowup);
        return;
    }

    const cancel = event.target.closest('[data-open-cancel]');
    if (cancel) {
        event.stopPropagation();
        openCancelModal(cancel.dataset.openCancel);
    }
});
document.querySelectorAll('[data-open-detail], [data-row-detail]').forEach((item) => item.addEventListener('click', (event) => {
    event.stopPropagation();
    const id = item.dataset.openDetail || item.dataset.rowDetail;
    const record = findRecord(id);
    if (!record) return;
    document.querySelector('.js-detail-body').innerHTML = summaryHtml(record);
    openDialog('detail-modal');
}));
document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', closeDialogs));
document.querySelector('[data-modal-backdrop]').addEventListener('click', closeDialogs);
new Set([
    ...document.querySelectorAll('.app-modal form'),
    ...document.querySelectorAll('.operation-form-card form'),
]).forEach(bindSenderLookup);
document.addEventListener('change', (event) => {
    if (event.target.matches('input[name="sender_type"]')) {
        const form = event.target.closest('form');
        if (form) syncSenderForm(form);
    }
});
const operationFocus = new URLSearchParams(window.location.search).get('focus');
if (operationFocus) {
    const focusCard = document.querySelector(`[data-focus-id="${CSS.escape(operationFocus)}"]`);
    if (focusCard) {
        setTimeout(() => {
            focusCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            focusCard.classList.add('operation-focus-highlight');
            setTimeout(() => focusCard.classList.remove('operation-focus-highlight'), 2600);
        }, 140);
    }
}
if (Object.keys(opValidation.errors || {}).length > 0) {
    const oldInput = opValidation.old || {};
    if (oldInput.planned_quantity_kg !== undefined) {
        const outboundForm = document.querySelector('.outbound-operation-form');
        if (outboundForm) {
            setFormValues(outboundForm, oldInput);
            syncSenderForm(outboundForm);
            applyValidation(outboundForm, opValidation);
            document.getElementById('outbound-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } else if (oldInput.quantity_ton !== undefined) {
        const form = document.querySelector('.operation-form-card .js-direct-form');
        if (form) {
            setFormValues(form, oldInput);
            syncSenderForm(form);
            applyValidation(form, opValidation);
            form.closest('.operation-form-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } else {
        const targetModal = 'notification-modal';
        const form = document.querySelector(`#${targetModal} form`);
        if (form) {
            const record = oldInput.id ? findRecord(oldInput.id) : null;
            fillNotificationForm(record);
            setFormValues(form, oldInput);
            syncSenderForm(form);
            applyValidation(form, opValidation);
            openDialog(targetModal);
        }
    }
}
</script>
