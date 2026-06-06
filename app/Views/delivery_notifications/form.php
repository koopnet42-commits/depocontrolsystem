<?php

declare(strict_types=1);

$selected = static fn (string $value, mixed $current): string => (string) $value === (string) $current ? 'selected' : '';
$toTonValue = static fn (mixed $kg): string => $kg === '' || $kg === null ? '' : rtrim(rtrim(number_format(((float) $kg) / 1000, 3, '.', ''), '0'), '.');
$selectedCompanyName = '';
foreach ($companies as $companyOption) {
    if ((string) $companyOption['id'] === (string) ($notification['company_id'] ?? '')) {
        $selectedCompanyName = (string) $companyOption['name'];
        break;
    }
}
$selectedCompanyName = (string) ($notification['company_name'] ?? $selectedCompanyName);
$companyJson = json_encode($companies, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$personJson = json_encode($personSenders ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$alerts = [
    'active_plate_exists' => ['alert--danger', 'Bu plaka için aktif bir süreç zaten var. Mevcut işlem tamamlanmadan yeni ön bildirim açılamaz.'],
    'invalid' => ['alert--danger', 'Kayıt oluşturulmadı. Firma ürününde firma adı ve irsaliye; şahıs ürününde ad soyad ve 11 haneli TC zorunludur. Ürün ve plaka da boş olamaz.'],
];
$alert = $alerts[$message ?? ''] ?? null;
$validation = $validation ?? ['errors' => [], 'old' => [], 'general' => null];
$fieldError = static fn (string $field): string => isset($validation['errors'][$field]) ? '<small class="field-error">' . htmlspecialchars((string) $validation['errors'][$field]) . '</small>' : '';
$fieldClass = static fn (string $field): string => isset($validation['errors'][$field]) ? ' field--error' : '';
?>
<header class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <p class="page-subtitle">Firma, ürün ve araç bilgilerini tek ekrandan girin.</p>
    </div>
    <a class="button" href="/delivery-notifications">Listeye Dön</a>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel panel--form panel--wide-form">
    <form action="<?= htmlspecialchars($action) ?>" method="post" class="form-grid" data-driver-vehicle-form>
        <?php if (! empty($notification['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
        <?php endif; ?>

        <div class="field field--wide">
            <span>Gönderici Tipi</span>
            <div class="segmented segmented--sender">
                <label><input type="radio" name="sender_type" value="company" <?= ($notification['sender_type'] ?? 'company') === 'person' ? '' : 'checked' ?>> Firma Ürünü</label>
                <label><input type="radio" name="sender_type" value="person" <?= ($notification['sender_type'] ?? 'company') === 'person' ? 'checked' : '' ?>> Şahıs Ürünü</label>
            </div>
        </div>

        <label class="field sender-company<?= $fieldClass('company_name') ?>">
            <span>Firma ara / seç</span>
            <input
                type="search"
                name="company_name"
                value="<?= htmlspecialchars($selectedCompanyName) ?>"
                list="company-suggestions"
                autocomplete="off"
                placeholder="Firma adını yazın"
            >
            <input type="hidden" name="company_id" value="<?= htmlspecialchars((string) ($notification['company_id'] ?? '')) ?>">
            <datalist id="company-suggestions">
                <?php foreach ($companies as $company): ?>
                    <option value="<?= htmlspecialchars((string) $company['name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <small class="field-help" id="company-match-help">Firma adı yazıldığında mevcut kayıtlar önerilir.</small>
            <?= $fieldError('company_name') ?>
        </label>

        <label class="field sender-company<?= $fieldClass('dispatch_number') ?>"><span>İrsaliye numarası</span><input type="text" name="dispatch_number" value="<?= htmlspecialchars((string) ($notification['dispatch_number'] ?? '')) ?>"><?= $fieldError('dispatch_number') ?></label>
        <label class="field sender-company"><span>Vergi no</span><input type="text" name="sender_tax_number" value="<?= htmlspecialchars((string) ($notification['sender_tax_number'] ?? '')) ?>"></label>
        <label class="field sender-person<?= $fieldClass('sender_name') ?>" hidden>
            <span>Ad soyad ara / yaz</span>
            <input type="search" name="sender_name" value="<?= htmlspecialchars((string) ($notification['sender_name'] ?? '')) ?>" list="person-suggestions" autocomplete="off" placeholder="Ad soyad yazın">
            <datalist id="person-suggestions">
                <?php foreach (($personSenders ?? []) as $person): ?>
                    <option value="<?= htmlspecialchars((string) $person['sender_name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <small class="field-help" id="person-match-help">Daha önce gelen şahıslar yazarken önerilir.</small>
            <?= $fieldError('sender_name') ?>
        </label>
        <label class="field sender-person<?= $fieldClass('identity_number') ?>" hidden><span>TC kimlik no</span><input type="text" name="identity_number" value="<?= htmlspecialchars((string) ($notification['identity_number'] ?? '')) ?>" inputmode="numeric" pattern="[0-9]{11}" maxlength="11"><?= $fieldError('identity_number') ?></label>
        <label class="field sender-contact"><span>Gönderici telefon</span><input type="text" name="sender_phone" value="<?= htmlspecialchars((string) ($notification['sender_phone'] ?? '')) ?>"></label>
        <label class="field field--wide sender-contact"><span>Gönderici adres</span><textarea name="sender_address" rows="2"><?= htmlspecialchars((string) ($notification['sender_address'] ?? '')) ?></textarea></label>

        <label class="field<?= $fieldClass('product_id') ?>">
            <span>Ürün çeşidi</span>
            <select name="product_id" required>
                <option value="">Seçiniz</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>" <?= $selected((string) $product['id'], $notification['product_id']) ?>>
                        <?= htmlspecialchars($product['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('product_id') ?>
        </label>

        <label class="field<?= $fieldClass('expected_quantity_ton') ?>">
            <span>Bildirilen miktar ton</span>
            <input
                type="number"
                name="expected_quantity_ton"
                value="<?= htmlspecialchars($toTonValue($notification['expected_quantity_kg'] ?? '')) ?>"
                min="0"
                step="0.001"
            >
            <?= $fieldError('expected_quantity_ton') ?>
        </label>

        <label class="field<?= $fieldClass('plate_number') ?>">
            <span>Durum</span>
            <select name="status">
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $selected($value, $notification['status']) ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Araç plakası</span>
            <input type="hidden" name="vehicle_match_action" value="update">
            <input type="hidden" name="driver_match_action" value="update">
            <input
                type="text"
                name="plate_number"
                value="<?= htmlspecialchars((string) ($vehicle['plate_number'] ?? '')) ?>"
                maxlength="20"
                placeholder="34 ABC 123"
                required
            >
            <?= $fieldError('plate_number') ?>
        </label>

        <label class="field">
            <span>Araç markası</span>
            <input type="text" name="vehicle_brand" value="<?= htmlspecialchars((string) ($vehicle['brand'] ?? '')) ?>" maxlength="80">
        </label>

        <label class="field">
            <span>Araç modeli</span>
            <input type="text" name="vehicle_model" value="<?= htmlspecialchars((string) ($vehicle['model'] ?? '')) ?>" maxlength="80">
        </label>

        <label class="field">
            <span>Şoför adı soyadı</span>
            <input type="text" name="driver_name" value="<?= htmlspecialchars((string) ($vehicle['driver_name'] ?? '')) ?>" maxlength="140">
        </label>

        <label class="field">
            <span>Şoför telefon</span>
            <input type="text" name="driver_phone" value="<?= htmlspecialchars((string) ($vehicle['driver_phone'] ?? '')) ?>" maxlength="40">
        </label>

        <label class="field">
            <span>Şoför TC kimlik no</span>
            <input type="text" name="driver_identity_number" value="" inputmode="numeric" maxlength="11">
        </label>

        <div class="field--wide driver-vehicle-card" data-driver-vehicle-card hidden></div>

        <label class="field">
            <span>Yükleme tarihi</span>
            <input type="date" name="loading_date" value="<?= htmlspecialchars((string) ($notification['loading_date'] ?? '')) ?>">
        </label>

        <label class="field">
            <span>Tahmini geliş tarihi</span>
            <input type="date" name="expected_arrival_date" value="<?= htmlspecialchars((string) ($notification['expected_arrival_date'] ?? '')) ?>">
        </label>

        <label class="field field--wide">
            <span>Açıklama</span>
            <textarea name="notes" rows="4"><?= htmlspecialchars((string) ($notification['notes'] ?? '')) ?></textarea>
        </label>

        <div class="form-actions">
            <button class="button button--primary" type="submit">Kaydet</button>
            <a class="button button--ghost" href="/delivery-notifications">Vazgeç</a>
        </div>
    </form>
</section>

<script>
    const companyOptions = <?= $companyJson ?: '[]' ?>;
    const personOptions = <?= $personJson ?: '[]' ?>;

    function normalizeText(value) {
        return (value || '').toLocaleLowerCase('tr-TR').trim();
    }

    function syncSenderType() {
        const type = document.querySelector('input[name="sender_type"]:checked')?.value || 'company';
        document.querySelectorAll('.sender-company').forEach((el) => {
            el.hidden = type !== 'company';
            el.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = type !== 'company';
                field.required = type === 'company' && ['company_id', 'dispatch_number'].includes(field.name);
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
            el.querySelectorAll('input, textarea').forEach((field) => {
                field.disabled = false;
            });
        });
        if (type === 'company') {
            syncCompanyFields();
        } else {
            syncPersonFields();
        }
    }

    document.querySelectorAll('input[name="sender_type"]').forEach((input) => input.addEventListener('change', syncSenderType));

    const companyInput = document.querySelector('input[name="company_name"]');
    const companyIdInput = document.querySelector('input[name="company_id"]');
    const senderNameInput = document.querySelector('input[name="sender_name"]');
    const identityInput = document.querySelector('input[name="identity_number"]');
    const taxInput = document.querySelector('input[name="sender_tax_number"]');
    const addressInput = document.querySelector('textarea[name="sender_address"]');
    const phoneInput = document.querySelector('input[name="sender_phone"]');
    const companyHelp = document.getElementById('company-match-help');
    const personHelp = document.getElementById('person-match-help');

    function syncCompanyFields() {
        const typed = normalizeText(companyInput?.value);
        const match = companyOptions.find((company) => normalizeText(company.name) === typed);

        if (match) {
            companyIdInput.value = match.id || '';
            taxInput.value = match.tax_number || '';
            addressInput.value = match.address || '';
            phoneInput.value = match.phone || '';
            companyHelp.textContent = 'Mevcut firma seçildi. Bilgiler firma kartından getirildi.';
            return;
        }

        companyIdInput.value = '';
        if (companyInput?.value.trim()) {
            companyHelp.textContent = 'Firma bulunamadı. Kaydedince yeni firma kaydı otomatik oluşturulacak.';
        } else {
            companyHelp.textContent = 'Firma adı yazıldığında mevcut kayıtlar önerilir.';
        }
    }

    function syncPersonFields() {
        const typed = normalizeText(senderNameInput?.value);
        const match = personOptions.find((person) => normalizeText(person.sender_name) === typed);

        if (match) {
            identityInput.value = match.identity_number || '';
            phoneInput.value = match.sender_phone || '';
            addressInput.value = match.sender_address || '';
            personHelp.textContent = 'Mevcut şahıs bilgileri getirildi.';
            return;
        }

        if (senderNameInput?.value.trim()) {
            personHelp.textContent = 'Şahıs bulunamadı. Kaydedince bu bildirimde yeni şahıs bilgisi oluşacak.';
        } else {
            personHelp.textContent = 'Daha önce gelen şahıslar yazarken önerilir.';
        }
    }

    companyInput?.addEventListener('input', syncCompanyFields);
    senderNameInput?.addEventListener('input', syncPersonFields);
    identityInput?.addEventListener('input', () => {
        identityInput.value = identityInput.value.replace(/\D+/g, '').slice(0, 11);
    });

    syncSenderType();
</script>
