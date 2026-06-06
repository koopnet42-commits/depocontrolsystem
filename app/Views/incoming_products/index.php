<?php

declare(strict_types=1);

$formatTon = static fn (float|int|string|null $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$companyJson = json_encode($companies, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$personJson = json_encode($personSenders ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$alerts = [
    'saved' => ['alert--success', 'Ön bildirimsiz gelen ürün kaydı oluşturuldu. Kantar girişine alınabilir.'],
    'invalid' => ['alert--danger', 'Gelen ürün girişi alanlarını kontrol edin.'],
    'not_found' => ['alert--danger', 'Beklemede ürün bildirimi bulunamadı.'],
];
$alert = $alerts[$message] ?? null;
$validation = $validation ?? ['errors' => [], 'old' => [], 'general' => null];
$fieldError = static fn (string $field): string => isset($validation['errors'][$field]) ? '<small class="field-error">' . htmlspecialchars((string) $validation['errors'][$field]) . '</small>' : '';
$fieldClass = static fn (string $field): string => isset($validation['errors'][$field]) ? ' field--error' : '';
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Gelen Ürün Girişi</h1>
        <p class="page-subtitle">Tesise gelen aracı ön bildirimli veya ön bildirimsiz olarak işleme alın.</p>
    </div>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel incoming-panel">
    <div class="section-heading">
        <h2>Ön Bildirimli Giriş</h2>
    </div>

    <form action="/incoming-products" method="get" class="plate-search">
        <label class="field field--wide">
            <span>Ön bildirim ara</span>
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($query) ?>"
                placeholder="Plaka, bildirim no, firma, şahıs, ürün veya irsaliye yazın"
                autofocus
            >
        </label>
        <button class="button button--primary" type="submit">Ara</button>
    </form>

    <?php if ($matches !== []): ?>
        <div class="table-wrap table-wrap--spaced">
            <table class="data-table data-table--compact">
                <thead>
                    <tr>
                        <th>Bildirim</th>
                        <th>Plaka</th>
                        <th>Gönderici</th>
                        <th>Ürün</th>
                        <th>Miktar</th>
                        <th class="table-actions">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $match): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($match['notification_number']) ?></strong></td>
                            <td><?= htmlspecialchars((string) ($match['plate_number'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string) (($match['sender_type'] ?? 'company') === 'person' ? ($match['sender_name'] ?? '-') : $match['company_name'])) ?></td>
                            <td><?= htmlspecialchars($match['product_name']) ?></td>
                            <td><?= htmlspecialchars($formatTon($match['expected_quantity_kg'] ?? 0)) ?></td>
                            <td class="table-actions">
                                <a class="button button--small" href="/incoming-products?q=<?= urlencode($query) ?>&notification_id=<?= (int) $match['id'] ?>">Seç</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($query !== ''): ?>
        <div class="alert alert--danger">Arama kriterine uygun bekleyen bildirim bulunamadı.</div>
    <?php endif; ?>

    <?php if ($selectedNotification !== null): ?>
        <div class="detail-panel detail-panel--embedded">
            <div class="detail-header">
                <div>
                    <div class="detail-kicker">Seçilen Bildirim</div>
                    <h2><?= htmlspecialchars((string) ($selectedNotification['plate_number'] ?? '-')) ?></h2>
                </div>
                <span class="badge badge--muted"><?= htmlspecialchars($selectedNotification['notification_number']) ?></span>
            </div>
            <div class="detail-grid">
                <div><span>Gönderici</span><strong><?= htmlspecialchars((string) (($selectedNotification['sender_type'] ?? 'company') === 'person' ? ($selectedNotification['sender_name'] ?? '-') : $selectedNotification['company_name'])) ?></strong></div>
                <div><span>Ürün</span><strong><?= htmlspecialchars($selectedNotification['product_name']) ?></strong></div>
                <div><span>Miktar</span><strong><?= htmlspecialchars($formatTon($selectedNotification['expected_quantity_kg'] ?? 0)) ?></strong></div>
                <div><span>Şoför</span><strong><?= htmlspecialchars((string) ($selectedNotification['driver_name'] ?? '-')) ?></strong></div>
                <div><span>İrsaliye / TC</span><strong><?= htmlspecialchars((string) (($selectedNotification['sender_type'] ?? 'company') === 'person' ? ($selectedNotification['identity_number'] ?? '-') : ($selectedNotification['dispatch_number'] ?? '-'))) ?></strong></div>
                <div><span>Araç</span><strong><?= htmlspecialchars((string) ($selectedNotification['vehicle_brand'] ?? '-')) ?></strong></div>
            </div>
            <div class="incoming-readonly-grid">
                <label class="field"><span>Plaka</span><input type="text" value="<?= htmlspecialchars((string) ($selectedNotification['plate_number'] ?? '')) ?>" readonly></label>
                <label class="field"><span>Gönderici tipi</span><input type="text" value="<?= htmlspecialchars(($selectedNotification['sender_type'] ?? 'company') === 'person' ? 'Şahıs Ürünü' : 'Firma Ürünü') ?>" readonly></label>
                <label class="field"><span>Firma / Şahıs</span><input type="text" value="<?= htmlspecialchars((string) (($selectedNotification['sender_type'] ?? 'company') === 'person' ? ($selectedNotification['sender_name'] ?? '-') : $selectedNotification['company_name'])) ?>" readonly></label>
                <label class="field"><span>Ürün tipi</span><input type="text" value="<?= htmlspecialchars((string) $selectedNotification['product_name']) ?>" readonly></label>
                <label class="field"><span>Bildirilen miktar</span><input type="text" value="<?= htmlspecialchars($formatTon($selectedNotification['expected_quantity_kg'] ?? 0)) ?>" readonly></label>
                <label class="field"><span>Şoför telefon</span><input type="text" value="<?= htmlspecialchars((string) ($selectedNotification['driver_phone'] ?? '')) ?>" readonly></label>
            </div>
            <form action="/incoming-products/start-pre-notified" method="post" class="operation-row">
                <input type="hidden" name="notification_id" value="<?= (int) $selectedNotification['id'] ?>">
                <button class="button button--primary" type="submit">Kantar Girişine Al</button>
            </form>
        </div>
    <?php endif; ?>
</section>

<?php if ($canDirectEntry): ?>
    <section class="panel incoming-panel">
        <div class="section-heading">
            <h2>Ön Bildirimsiz Giriş</h2>
        </div>
        <form action="/incoming-products/direct" method="post" class="incoming-form" data-driver-vehicle-form>
            <div class="segmented segmented--sender">
                <label><input type="radio" name="sender_type" value="company" <?= ($validation['old']['sender_type'] ?? 'company') !== 'person' ? 'checked' : '' ?>> Firma Ürünü</label>
                <label><input type="radio" name="sender_type" value="person" <?= ($validation['old']['sender_type'] ?? 'company') === 'person' ? 'checked' : '' ?>> Şahıs Ürünü</label>
            </div>

            <div class="incoming-grid">
                <label class="field sender-company<?= $fieldClass('company_name') ?>">
                    <span>Firma ara / seç</span>
                    <input type="search" name="company_name" value="<?= htmlspecialchars((string) ($validation['old']['company_name'] ?? '')) ?>" list="incoming-company-suggestions" autocomplete="off" placeholder="Firma adını yazın">
                    <input type="hidden" name="company_id" value="<?= htmlspecialchars((string) ($validation['old']['company_id'] ?? '')) ?>">
                    <datalist id="incoming-company-suggestions">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= htmlspecialchars((string) $company['name']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <small class="field-help" id="incoming-company-help">Firma adı yazıldığında mevcut kayıtlar önerilir.</small>
                    <?= $fieldError('company_name') ?>
                </label>
                <label class="field sender-company<?= $fieldClass('dispatch_number') ?>"><span>İrsaliye numarası</span><input type="text" name="dispatch_number" value="<?= htmlspecialchars((string) ($validation['old']['dispatch_number'] ?? '')) ?>"><?= $fieldError('dispatch_number') ?></label>
                <label class="field sender-company"><span>Vergi no</span><input type="text" name="sender_tax_number" value="<?= htmlspecialchars((string) ($validation['old']['sender_tax_number'] ?? '')) ?>"></label>
                <label class="field sender-person<?= $fieldClass('sender_name') ?>" hidden>
                    <span>Ad soyad ara / yaz</span>
                    <input type="search" name="sender_name" value="<?= htmlspecialchars((string) ($validation['old']['sender_name'] ?? '')) ?>" list="incoming-person-suggestions" autocomplete="off" disabled>
                    <datalist id="incoming-person-suggestions">
                        <?php foreach (($personSenders ?? []) as $person): ?>
                            <option value="<?= htmlspecialchars((string) $person['sender_name']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <small class="field-help" id="incoming-person-help">Daha önce gelen şahıslar yazarken önerilir.</small>
                    <?= $fieldError('sender_name') ?>
                </label>
                <label class="field sender-person<?= $fieldClass('identity_number') ?>" hidden><span>TC kimlik no</span><input type="text" name="identity_number" value="<?= htmlspecialchars((string) ($validation['old']['identity_number'] ?? '')) ?>" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" disabled><?= $fieldError('identity_number') ?></label>
                <label class="field sender-contact"><span>Gönderici telefon</span><input type="text" name="sender_phone" value="<?= htmlspecialchars((string) ($validation['old']['sender_phone'] ?? '')) ?>"></label>
                <label class="field field--wide sender-contact"><span>Gönderici adres</span><textarea name="sender_address" rows="2"><?= htmlspecialchars((string) ($validation['old']['sender_address'] ?? '')) ?></textarea></label>
                <label class="field<?= $fieldClass('product_id') ?>"><span>Ürün tipi</span><select name="product_id"><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= (string) ($validation['old']['product_id'] ?? '') === (string) $product['id'] ? 'selected' : '' ?>><?= htmlspecialchars($product['name']) ?></option><?php endforeach; ?></select><?= $fieldError('product_id') ?></label>
                <label class="field<?= $fieldClass('quantity_ton') ?>"><span>Gelen miktar ton</span><input type="number" name="quantity_ton" value="<?= htmlspecialchars((string) ($validation['old']['quantity_ton'] ?? '')) ?>" min="0" step="0.001"><?= $fieldError('quantity_ton') ?></label>
                <input type="hidden" name="vehicle_match_action" value="update">
                <input type="hidden" name="driver_match_action" value="update">
                <label class="field<?= $fieldClass('plate_number') ?>"><span>Plaka</span><input type="text" name="plate_number" value="<?= htmlspecialchars((string) ($validation['old']['plate_number'] ?? '')) ?>" placeholder="34ABC123"><?= $fieldError('plate_number') ?></label>
                <label class="field"><span>Araç markası</span><input type="text" name="vehicle_brand" value="<?= htmlspecialchars((string) ($validation['old']['vehicle_brand'] ?? '')) ?>"></label>
                <label class="field"><span>Araç modeli</span><input type="text" name="vehicle_model" value="<?= htmlspecialchars((string) ($validation['old']['vehicle_model'] ?? '')) ?>"></label>
                <label class="field"><span>Şoför adı</span><input type="text" name="driver_name" value="<?= htmlspecialchars((string) ($validation['old']['driver_name'] ?? '')) ?>"></label>
                <label class="field"><span>Şoför telefon</span><input type="text" name="driver_phone" value="<?= htmlspecialchars((string) ($validation['old']['driver_phone'] ?? '')) ?>"></label>
                <label class="field"><span>Şoför TC kimlik no</span><input type="text" name="driver_identity_number" value="<?= htmlspecialchars((string) ($validation['old']['driver_identity_number'] ?? '')) ?>" maxlength="11" inputmode="numeric"></label>
                <div class="field--wide driver-vehicle-card" data-driver-vehicle-card hidden></div>
                <label class="field"><span>Giriş tarihi</span><input type="date" name="entry_date" value="<?= htmlspecialchars((string) ($validation['old']['entry_date'] ?? date('Y-m-d'))) ?>"></label>
                <label class="field field--wide<?= $fieldClass('notes') ?>"><span>Açıklama</span><textarea name="notes" rows="2" required><?= htmlspecialchars((string) ($validation['old']['notes'] ?? '')) ?></textarea><?= $fieldError('notes') ?></label>
            </div>
            <button class="button button--primary" type="submit">Ön Bildirimsiz Giriş Oluştur</button>
        </form>
    </section>
<?php endif; ?>

<script>
    const incomingCompanies = <?= $companyJson ?: '[]' ?>;
    const incomingPeople = <?= $personJson ?: '[]' ?>;

    function normalizeIncomingText(value) {
        return (value || '').toLocaleLowerCase('tr-TR').trim();
    }

    function syncSenderType() {
        const type = document.querySelector('input[name="sender_type"]:checked')?.value || 'company';
        document.querySelectorAll('.sender-company').forEach((el) => {
            el.hidden = type !== 'company';
            el.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = type !== 'company';
                field.required = type === 'company' && ['company_name', 'dispatch_number'].includes(field.name);
            });
        });
        document.querySelectorAll('.sender-person').forEach((el) => {
            el.hidden = type !== 'person';
            el.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = type !== 'person';
                field.required = type === 'person' && ['sender_name', 'identity_number'].includes(field.name);
            });
        });
        document.querySelectorAll('.sender-contact').forEach((el) => {
            el.querySelectorAll('input, textarea').forEach((field) => field.disabled = false);
        });
        if (type === 'company') {
            syncIncomingCompany();
        } else {
            syncIncomingPerson();
        }
    }

    document.querySelectorAll('input[name="sender_type"]').forEach((input) => input.addEventListener('change', syncSenderType));

    const incomingCompanyInput = document.querySelector('input[name="company_name"]');
    const incomingCompanyIdInput = document.querySelector('input[name="company_id"]');
    const incomingSenderNameInput = document.querySelector('input[name="sender_name"]');
    const incomingIdentityInput = document.querySelector('input[name="identity_number"]');
    const incomingTaxInput = document.querySelector('input[name="sender_tax_number"]');
    const incomingPhoneInput = document.querySelector('input[name="sender_phone"]');
    const incomingAddressInput = document.querySelector('textarea[name="sender_address"]');
    const incomingCompanyHelp = document.getElementById('incoming-company-help');
    const incomingPersonHelp = document.getElementById('incoming-person-help');

    function syncIncomingCompany() {
        const typed = normalizeIncomingText(incomingCompanyInput?.value);
        const match = incomingCompanies.find((company) => normalizeIncomingText(company.name) === typed);

        if (match) {
            incomingCompanyIdInput.value = match.id || '';
            incomingTaxInput.value = match.tax_number || '';
            incomingPhoneInput.value = match.phone || '';
            incomingAddressInput.value = match.address || '';
            incomingCompanyHelp.textContent = 'Mevcut firma seçildi. Bilgiler firma kartından getirildi.';
            return;
        }

        incomingCompanyIdInput.value = '';
        incomingCompanyHelp.textContent = incomingCompanyInput?.value.trim()
            ? 'Firma bulunamadı. Kaydedince yeni firma kaydı otomatik oluşturulacak.'
            : 'Firma adı yazıldığında mevcut kayıtlar önerilir.';
    }

    function syncIncomingPerson() {
        const typed = normalizeIncomingText(incomingSenderNameInput?.value);
        const match = incomingPeople.find((person) => normalizeIncomingText(person.sender_name) === typed);

        if (match) {
            incomingIdentityInput.value = match.identity_number || '';
            incomingPhoneInput.value = match.sender_phone || '';
            incomingAddressInput.value = match.sender_address || '';
            incomingPersonHelp.textContent = 'Mevcut şahıs bilgileri getirildi.';
            return;
        }

        incomingPersonHelp.textContent = incomingSenderNameInput?.value.trim()
            ? 'Şahıs bulunamadı. Kaydedince bu girişte yeni şahıs bilgisi oluşacak.'
            : 'Daha önce gelen şahıslar yazarken önerilir.';
    }

    incomingCompanyInput?.addEventListener('input', syncIncomingCompany);
    incomingSenderNameInput?.addEventListener('input', syncIncomingPerson);
    incomingIdentityInput?.addEventListener('input', () => {
        incomingIdentityInput.value = incomingIdentityInput.value.replace(/\D+/g, '').slice(0, 11);
    });
    syncSenderType();
</script>
