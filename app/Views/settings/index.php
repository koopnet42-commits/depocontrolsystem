<?php

declare(strict_types=1);

$tabs = [
    'company' => 'Firma Bilgileri',
    'cameras' => 'Kamera Ayarları',
    'scales' => 'Kantar Haberleşme',
    'barriers' => 'Bariyer Kontrol',
    'system' => 'Sistem Ayarları',
];
$alerts = [
    'saved' => ['alert--success', 'Ayarlar kaydedildi.'],
    'test_ok' => ['alert--success', 'Simülasyon testi tamamlandı ve audit log kaydı oluşturuldu.'],
    'invalid' => ['alert--danger', 'Zorunlu alanları ve değerleri kontrol edin.'],
];
$alert = $alerts[$message] ?? null;
$checked = static fn (mixed $value): string => (int) $value === 1 ? 'checked' : '';
$selected = static fn (mixed $value, mixed $current): string => (string) $value === (string) $current ? 'selected' : '';
$camera = $editCamera ?? ['id' => null, 'name' => '', 'usage_location' => 'giriş kapısı', 'camera_type' => 'IP kamera', 'connection_url' => '', 'port' => '', 'username' => '', 'is_active' => 1];
$scale = $editScale ?? ['id' => null, 'name' => '', 'usage_location' => 'giriş kantarı', 'communication_type' => 'manuel', 'ip_address' => '', 'port' => '', 'com_port' => '', 'baud_rate' => '9600', 'data_bits' => '8', 'stop_bits' => '1', 'parity' => 'none', 'read_format' => '', 'is_active' => 1];
$barrier = $editBarrier ?? ['id' => null, 'name' => '', 'usage_location' => 'giriş bariyeri', 'control_type' => 'manuel', 'ip_address' => '', 'port' => '', 'relay_number' => '', 'open_command' => '', 'close_command' => '', 'is_active' => 1];
$qrFields = json_decode((string) ($system['qr_content_fields'] ?? '[]'), true);
$qrFields = is_array($qrFields) ? $qrFields : [];
$qrOptions = [
    'company_name' => 'Firma adı',
    'company_tax_number' => 'Firma vergi no',
    'plate_number' => 'Plaka',
    'product_name' => 'Ürün adı',
    'first_weight' => 'İlk tartım',
    'analysis_values' => 'Analiz değerleri',
    'silo_code' => 'Silo kodu',
    'silo_name' => 'Silo adı',
    'ticket_code' => 'Ticket kodu',
    'issued_at' => 'Tarih saat',
    'driver_name' => 'Şoför adı',
    'dispatch_number' => 'İrsaliye no',
    'identity_number' => 'TC kimlik no',
];
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Ayarlar</h1>
        <p class="page-subtitle">Firma anteti, cihaz simülasyon ayarları ve sistem güvenlik tercihleri.</p>
    </div>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<nav class="settings-tabs" aria-label="Ayar sekmeleri">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="<?= $tab === $key ? 'settings-tabs__item settings-tabs__item--active' : 'settings-tabs__item' ?>" href="/settings?tab=<?= htmlspecialchars($key) ?>">
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'company'): ?>
    <section class="panel panel--form panel--wide-form">
        <form action="/settings/company" method="post" enctype="multipart/form-data" class="form-grid">
            <label class="field">
                <span>Firma adı</span>
                <input type="text" name="company_name" value="<?= htmlspecialchars((string) $company['company_name']) ?>" required>
            </label>
            <label class="field">
                <span>Logo yükleme</span>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
            </label>
            <?php if (! empty($company['logo_path'])): ?>
                <div class="logo-preview">
                    <span>Logo önizleme</span>
                    <img src="<?= htmlspecialchars($company['logo_path']) ?>" alt="Firma logosu">
                </div>
            <?php endif; ?>
            <label class="field field--wide"><span>Adres</span><textarea name="address" rows="3"><?= htmlspecialchars((string) ($company['address'] ?? '')) ?></textarea></label>
            <label class="field"><span>Vergi dairesi</span><input type="text" name="tax_office" value="<?= htmlspecialchars((string) ($company['tax_office'] ?? '')) ?>"></label>
            <label class="field"><span>Vergi numarası</span><input type="text" name="tax_number" value="<?= htmlspecialchars((string) ($company['tax_number'] ?? '')) ?>"></label>
            <label class="field"><span>Telefon</span><input type="text" name="phone" value="<?= htmlspecialchars((string) ($company['phone'] ?? '')) ?>"></label>
            <label class="field"><span>E-posta</span><input type="email" name="email" value="<?= htmlspecialchars((string) ($company['email'] ?? '')) ?>"></label>
            <label class="field"><span>Web sitesi</span><input type="url" name="website" value="<?= htmlspecialchars((string) ($company['website'] ?? '')) ?>"></label>
            <label class="field"><span>Yetkili kişi</span><input type="text" name="contact_person" value="<?= htmlspecialchars((string) ($company['contact_person'] ?? '')) ?>"></label>
            <label class="field"><span>Lisans / işletme numarası</span><input type="text" name="license_number" value="<?= htmlspecialchars((string) ($company['license_number'] ?? '')) ?>"></label>
            <label class="field field--wide"><span>Antet açıklama metni</span><textarea name="letterhead_text" rows="3"><?= htmlspecialchars((string) ($company['letterhead_text'] ?? '')) ?></textarea></label>
            <div class="form-actions"><button class="button button--primary" type="submit">Firma Bilgilerini Kaydet</button></div>
        </form>
    </section>
<?php endif; ?>

<?php if ($tab === 'cameras'): ?>
    <section class="settings-grid">
        <form class="panel panel--form" action="/settings/camera" method="post">
            <h2 class="settings-form-title"><?= $camera['id'] ? 'Kamera Düzenle' : 'Kamera Ekle' ?></h2>
            <?php if ($camera['id']): ?><input type="hidden" name="id" value="<?= (int) $camera['id'] ?>"><?php endif; ?>
            <div class="form-grid form-grid--single">
                <label class="field"><span>Kamera adı</span><input name="name" value="<?= htmlspecialchars((string) $camera['name']) ?>" required></label>
                <label class="field"><span>Kullanım yeri</span><select name="usage_location"><?php foreach (['giriş kapısı','kantar','çıkış','silo alanı'] as $v): ?><option <?= $selected($v, $camera['usage_location']) ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Kamera tipi</span><select name="camera_type"><?php foreach (['IP kamera','USB kamera','RTSP kamera'] as $v): ?><option <?= $selected($v, $camera['camera_type']) ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>IP adresi / RTSP URL</span><input name="connection_url" value="<?= htmlspecialchars((string) ($camera['connection_url'] ?? '')) ?>"></label>
                <label class="field"><span>Port</span><input type="number" name="port" min="1" max="65535" value="<?= htmlspecialchars((string) ($camera['port'] ?? '')) ?>"></label>
                <label class="field"><span>Kullanıcı adı</span><input name="username" value="<?= htmlspecialchars((string) ($camera['username'] ?? '')) ?>"></label>
                <label class="field"><span>Şifre</span><input type="password" name="password" placeholder="<?= $camera['id'] ? 'Değişmeyecekse boş bırakın' : '' ?>"></label>
                <label class="check-field"><input type="checkbox" name="is_active" <?= $checked($camera['is_active']) ?>><span>Aktif</span></label>
                <div class="form-actions"><button class="button button--primary" type="submit">Kaydet</button></div>
            </div>
        </form>
        <?php $rows = $cameras; $table = 'camera_settings'; $editKey = 'edit_camera'; require BASE_PATH . '/app/Views/settings/partials/device_table.php'; ?>
    </section>
<?php endif; ?>

<?php if ($tab === 'scales'): ?>
    <section class="settings-grid">
        <form class="panel panel--form" action="/settings/scale" method="post">
            <h2 class="settings-form-title"><?= $scale['id'] ? 'Kantar Düzenle' : 'Kantar Ekle' ?></h2>
            <?php if ($scale['id']): ?><input type="hidden" name="id" value="<?= (int) $scale['id'] ?>"><?php endif; ?>
            <div class="form-grid form-grid--single">
                <label class="field"><span>Kantar adı</span><input name="name" value="<?= htmlspecialchars((string) $scale['name']) ?>" required></label>
                <label class="field"><span>Kullanım yeri</span><select name="usage_location"><?php foreach (['giriş kantarı','çıkış kantarı'] as $v): ?><option <?= $selected($v, $scale['usage_location']) ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Haberleşme tipi</span><select name="communication_type"><?php foreach (['Serial','TCP/IP','manuel'] as $v): ?><option <?= $selected($v, $scale['communication_type']) ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>IP adresi</span><input name="ip_address" value="<?= htmlspecialchars((string) ($scale['ip_address'] ?? '')) ?>"></label>
                <label class="field"><span>Port</span><input type="number" name="port" min="1" max="65535" value="<?= htmlspecialchars((string) ($scale['port'] ?? '')) ?>"></label>
                <label class="field"><span>COM port</span><input name="com_port" value="<?= htmlspecialchars((string) ($scale['com_port'] ?? '')) ?>"></label>
                <label class="field"><span>Baud rate</span><input type="number" name="baud_rate" value="<?= htmlspecialchars((string) ($scale['baud_rate'] ?? '9600')) ?>"></label>
                <label class="field"><span>Data bits</span><input type="number" name="data_bits" value="<?= htmlspecialchars((string) ($scale['data_bits'] ?? '8')) ?>"></label>
                <label class="field"><span>Stop bits</span><input name="stop_bits" value="<?= htmlspecialchars((string) ($scale['stop_bits'] ?? '1')) ?>"></label>
                <label class="field"><span>Parity</span><input name="parity" value="<?= htmlspecialchars((string) ($scale['parity'] ?? 'none')) ?>"></label>
                <label class="field"><span>Okuma formatı</span><input name="read_format" value="<?= htmlspecialchars((string) ($scale['read_format'] ?? '')) ?>"></label>
                <label class="check-field"><input type="checkbox" name="is_active" <?= $checked($scale['is_active']) ?>><span>Aktif</span></label>
                <div class="form-actions"><button class="button button--primary" type="submit">Kaydet</button></div>
            </div>
        </form>
        <?php $rows = $scales; $table = 'scale_settings'; $editKey = 'edit_scale'; require BASE_PATH . '/app/Views/settings/partials/device_table.php'; ?>
    </section>
<?php endif; ?>

<?php if ($tab === 'barriers'): ?>
    <section class="settings-grid">
        <form class="panel panel--form" action="/settings/barrier" method="post">
            <h2 class="settings-form-title"><?= $barrier['id'] ? 'Bariyer Düzenle' : 'Bariyer Ekle' ?></h2>
            <?php if ($barrier['id']): ?><input type="hidden" name="id" value="<?= (int) $barrier['id'] ?>"><?php endif; ?>
            <div class="form-grid form-grid--single">
                <label class="field"><span>Bariyer adı</span><input name="name" value="<?= htmlspecialchars((string) $barrier['name']) ?>" required></label>
                <label class="field"><span>Kullanım yeri</span><select name="usage_location"><?php foreach (['giriş bariyeri','kantar çıkış bariyeri','tesis çıkış bariyeri'] as $v): ?><option <?= $selected($v, $barrier['usage_location']) ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Kontrol tipi</span><select name="control_type"><?php foreach (['TCP/IP','Röle','PLC','manuel'] as $v): ?><option <?= $selected($v, $barrier['control_type']) ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>IP adresi</span><input name="ip_address" value="<?= htmlspecialchars((string) ($barrier['ip_address'] ?? '')) ?>"></label>
                <label class="field"><span>Port</span><input type="number" name="port" min="1" max="65535" value="<?= htmlspecialchars((string) ($barrier['port'] ?? '')) ?>"></label>
                <label class="field"><span>Röle numarası</span><input name="relay_number" value="<?= htmlspecialchars((string) ($barrier['relay_number'] ?? '')) ?>"></label>
                <label class="field"><span>Açma komutu</span><input name="open_command" value="<?= htmlspecialchars((string) ($barrier['open_command'] ?? '')) ?>"></label>
                <label class="field"><span>Kapama komutu</span><input name="close_command" value="<?= htmlspecialchars((string) ($barrier['close_command'] ?? '')) ?>"></label>
                <label class="check-field"><input type="checkbox" name="is_active" <?= $checked($barrier['is_active']) ?>><span>Aktif</span></label>
                <div class="form-actions"><button class="button button--primary" type="submit">Kaydet</button></div>
            </div>
        </form>
        <?php $rows = $barriers; $table = 'barrier_settings'; $editKey = 'edit_barrier'; require BASE_PATH . '/app/Views/settings/partials/device_table.php'; ?>
    </section>
<?php endif; ?>

<?php if ($tab === 'system'): ?>
    <section class="panel panel--form panel--wide-form">
        <form action="/settings/system" method="post" class="form-grid" data-confirm-real="Gerçek cihaz modu açılırsa fiziksel cihaz bağlantıları kullanılabilir. Devam edilsin mi?">
            <label class="field"><span>Sistem çalışma modu</span><select name="operation_mode"><option value="simulation" <?= $selected('simulation', $system['operation_mode']) ?>>Simülasyon</option><option value="real" <?= $selected('real', $system['operation_mode']) ?>>Gerçek cihaz</option></select></label>
            <label class="field"><span>Varsayılan yazıcı</span><input name="default_printer" value="<?= htmlspecialchars((string) ($system['default_printer'] ?? '')) ?>"></label>
            <label class="field"><span>Barkod tipi</span><select name="barcode_type"><option value="QR" <?= $selected('QR', $system['barcode_type']) ?>>QR</option><option value="Barcode" <?= $selected('Barcode', $system['barcode_type']) ?>>Barcode</option></select></label>
            <label class="field"><span>Dashboard silo görünümü</span><select name="dashboard_silo_view"><option value="vertical" <?= $selected('vertical', $system['dashboard_silo_view'] ?? 'vertical') ?>>Dikey</option><option value="horizontal" <?= $selected('horizontal', $system['dashboard_silo_view'] ?? 'vertical') ?>>Yatay</option></select></label>
            <?php foreach ([
                'auto_plate_recognition' => 'Otomatik plaka okuma aktif',
                'manual_weight_allowed' => 'Manuel tartım girişine izin ver',
                'manual_weight_reason_required' => 'Manuel tartımda açıklama zorunlu',
                'critical_confirmation_enabled' => 'Kritik işlem onayı aktif',
                'auto_backup_enabled' => 'Otomatik yedekleme aktif',
            ] as $key => $label): ?>
                <label class="check-field"><input type="checkbox" name="<?= htmlspecialchars($key) ?>" <?= $checked($system[$key] ?? 0) ?>><span><?= htmlspecialchars($label) ?></span></label>
            <?php endforeach; ?>
            <div class="field field--wide">
                <span>Karekod / Barkod İçeriği Ayarları</span>
                <div class="checkbox-grid">
                    <?php foreach ($qrOptions as $key => $label): ?>
                        <label class="check-field">
                            <input type="checkbox" name="qr_content_fields[]" value="<?= htmlspecialchars($key) ?>" <?= in_array($key, $qrFields, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-actions"><button class="button button--primary" type="submit">Sistem Ayarlarını Kaydet</button></div>
        </form>
    </section>
<?php endif; ?>
