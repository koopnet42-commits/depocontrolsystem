<?php

declare(strict_types=1);

$alerts = [
    'created' => ['alert--success', 'Ürün çıkışı kaydı oluşturuldu.'],
    'created_to_weighbridge' => ['alert--success', 'Ürün çıkışı kaydı oluşturuldu. Araç kantar ekranına aktarıldı.'],
    'started_to_weighbridge' => ['alert--success', 'Çıkış ön bildirimi sürece aktarıldı. Araç kantar ekranına aktarıldı.'],
    'first_saved' => ['alert--success', '1. tartım kaydedildi. Araç boş ağırlığı alındı.'],
    'first_required' => ['alert--danger', 'Önce 1. tartım kaydedilmelidir.'],
    'silo_mismatch' => ['alert--danger', 'Seçilen silo ürün tipiyle uyumlu değil.'],
    'cancelled' => ['alert--success', 'Ürün çıkışı kaydı iptal edildi.'],
    'invalid' => ['alert--danger', 'Formdaki alanları kontrol edin.'],
];
$alert = $alerts[$message] ?? null;
$formatKg = static fn (mixed $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (mixed $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$statusLabel = static fn (string $status): string => [
    'OUTBOUND_PRE_NOTIFIED' => 'Ön bildirildi',
    'OUTBOUND_ARRIVED' => 'Araç geldi',
    'OUTBOUND_FIRST_WEIGHED' => '1. tartım alındı',
    'OUTBOUND_LOADING_ASSIGNED_TO_SILO' => 'Siloya yönlendirildi',
    'OUTBOUND_SECOND_WEIGHING_WAITING' => '2. tartım bekliyor',
    'OUTBOUND_SECOND_WEIGHED' => '2. tartım alındı',
    'OUTBOUND_COMPLETED' => 'Tamamlandı',
    'OUTBOUND_REJECTED' => 'İptal / ret',
][$status] ?? $status;
$validation = $validation ?? ['errors' => [], 'old' => [], 'general' => null];
$oldInput = is_array($validation['old'] ?? null) ? $validation['old'] : [];
$oldValue = static fn (string $field, mixed $default = ''): string => htmlspecialchars((string) ($oldInput[$field] ?? $default));
$fieldError = static fn (string $field): string => isset($validation['errors'][$field]) ? '<small class="field-error">' . htmlspecialchars((string) $validation['errors'][$field]) . '</small>' : '';
$fieldClass = static fn (string $field): string => isset($validation['errors'][$field]) ? ' field--error' : '';
$companyJson = json_encode($companies, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$personJson = json_encode($personSenders ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$validationJson = json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<header class="page-header">
    <div>
        <h1 class="page-title">Silo Boşaltımı / Ürün Çıkışı</h1>
        <p class="page-subtitle">Boş gelen aracı tartın, silodan yüklemeye yönlendirin ve 2. tartımda çıkışı kapatın.</p>
    </div>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?> operation-alert">
        <span><?= htmlspecialchars($alert[1]) ?></span>
        <?php if (in_array((string) ($message ?? ''), ['created_to_weighbridge', 'started_to_weighbridge'], true) && (int) ($_GET['id'] ?? 0) > 0): ?>
            <a class="button button--small button--outbound" href="/weighbridge-entry?outbound_id=<?= (int) $_GET['id'] ?>&focus=outbound-<?= (int) $_GET['id'] ?>#outbound-first-weighing">Kantar Ekranına Git</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="panel operation-form-card operation-form-card--outbound">
    <div class="section-heading"><h2>Yeni Ürün Çıkışı Kaydı</h2></div>
    <form action="/outbound-loadings/store" method="post" class="form-grid operation-form outbound-operation-form" data-driver-vehicle-form>
        <input type="hidden" name="operation_type" value="PRODUCT_OUT">
        <input type="hidden" name="outbound_status" value="OUTBOUND_ARRIVED">
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
                    <span>Ürün</span>
                    <select name="product_id" required>
                        <option value="">Seçiniz</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int) $product['id'] ?>" <?= (string) ($oldInput['product_id'] ?? '') === (string) $product['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $product['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('product_id') ?>
                </label>
                <label class="field<?= $fieldClass('source_silo_id') ?>">
                    <span>Yüklenecek silo</span>
                    <select name="source_silo_id" required>
                        <option value="">Seçiniz</option>
                        <?php foreach ($silos as $silo): ?>
                            <option value="<?= (int) $silo['id'] ?>" <?= (string) ($oldInput['source_silo_id'] ?? '') === (string) $silo['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) ($silo['code'] . ' - ' . $silo['name'] . ' / ' . ($silo['product_name'] ?? '-') . ' / ' . $formatTon($silo['current_stock_kg']))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('source_silo_id') ?>
                </label>
                <label class="field<?= $fieldClass('planned_quantity_kg') ?>"><span>Planlanan miktar kg</span><input type="number" name="planned_quantity_kg" min="1" step="1" value="<?= $oldValue('planned_quantity_kg') ?>" required><?= $fieldError('planned_quantity_kg') ?></label>
                <label class="field field--wide"><span>Not</span><textarea name="note" rows="2"><?= $oldValue('note') ?></textarea></label>
                <div class="field--wide driver-vehicle-card" data-driver-vehicle-card hidden></div>
            </div>
        </section>
        <div class="form-actions field--wide"><button class="button button--primary button--outbound" type="submit">Yeni Çıkış Kaydı Oluştur</button></div>
    </form>
</section>

<?php if ($selectedRecord !== null): ?>
    <section class="panel detail-panel">
        <div class="detail-header">
            <div>
                <div class="detail-kicker">Ürün Çıkışı</div>
                <h2><?= htmlspecialchars((string) $selectedRecord['plate_number']) ?></h2>
            </div>
            <span class="badge badge--info"><?= htmlspecialchars($statusLabel((string) $selectedRecord['status'])) ?></span>
        </div>
        <div class="detail-grid">
            <div><span>Gönderici</span><strong><?= htmlspecialchars((string) $selectedRecord['sender_display']) ?></strong></div>
            <div><span>Ürün</span><strong><?= htmlspecialchars((string) $selectedRecord['product_name']) ?></strong></div>
            <div><span>Silo</span><strong><?= htmlspecialchars((string) ($selectedRecord['silo_code'] . ' - ' . $selectedRecord['silo_name'])) ?></strong></div>
            <div><span>Silo stok</span><strong><?= htmlspecialchars($formatKg($selectedRecord['current_stock_kg'])) ?></strong></div>
            <div><span>Planlanan</span><strong><?= htmlspecialchars($formatKg($selectedRecord['planned_quantity_kg'])) ?></strong></div>
            <div><span>1. tartım</span><strong><?= htmlspecialchars($selectedRecord['first_weight_kg'] === null ? '-' : $formatKg($selectedRecord['first_weight_kg'])) ?></strong></div>
            <div><span>2. tartım</span><strong><?= htmlspecialchars($selectedRecord['second_weight_kg'] === null ? '-' : $formatKg($selectedRecord['second_weight_kg'])) ?></strong></div>
            <div><span>Net çıkış</span><strong><?= htmlspecialchars($selectedRecord['net_quantity_kg'] === null ? '-' : $formatKg($selectedRecord['net_quantity_kg'])) ?></strong></div>
            <div><span>Çıkış barkodu</span><strong><?= htmlspecialchars((string) ($selectedRecord['outbound_barcode'] ?? '-')) ?></strong></div>
        </div>
        <div class="operation-row">
            <?php $selectedStatus = (string) ($selectedRecord['status'] ?? ''); ?>
            <?php if ($selectedStatus === 'OUTBOUND_PRE_NOTIFIED'): ?>
                <form action="/outbound-loadings/start-arrived" method="post">
                    <input type="hidden" name="id" value="<?= (int) $selectedRecord['id'] ?>">
                    <button class="button button--primary button--outbound" type="submit">Çıkış Akışını Başlat</button>
                </form>
            <?php endif; ?>
            <?php if ($selectedStatus === 'OUTBOUND_ARRIVED'): ?>
                <a class="button button--primary button--outbound" href="/weighbridge-entry?outbound_id=<?= (int) $selectedRecord['id'] ?>&focus=outbound-<?= (int) $selectedRecord['id'] ?>#outbound-first-weighing">Kantar Ekranına Git</a>
            <?php endif; ?>
            <?php if ($selectedStatus === 'OUTBOUND_FIRST_WEIGHED'): ?>
                <form action="/outbound-loadings/assign-silo" method="post">
                    <input type="hidden" name="id" value="<?= (int) $selectedRecord['id'] ?>">
                    <button class="button button--primary button--outbound" type="submit">Barkodla Doluma Gönder</button>
                </form>
            <?php endif; ?>
            <?php if ($selectedStatus === 'OUTBOUND_LOADING_ASSIGNED_TO_SILO'): ?>
                <form action="/outbound-loadings/send-to-second-weighing" method="post">
                    <input type="hidden" name="id" value="<?= (int) $selectedRecord['id'] ?>">
                    <button class="button button--primary button--outbound" type="submit">2. Tartıma Gönder</button>
                </form>
            <?php endif; ?>
            <?php if ($selectedStatus === 'OUTBOUND_SECOND_WEIGHING_WAITING'): ?>
                <a class="button button--primary button--outbound" href="/second-weighing?outbound_id=<?= (int) $selectedRecord['id'] ?>">2. Tartım Ekranını Aç</a>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="section-heading"><h2>Ürün Çıkışı Kayıtları</h2></div>
    <div class="table-wrap">
        <table class="data-table data-table--compact">
            <thead><tr><th>İşlem No</th><th>Plaka</th><th>Gönderici</th><th>Ürün</th><th>Silo</th><th>Planlanan</th><th>Durum</th><th class="table-actions">İşlem</th></tr></thead>
            <tbody>
                <?php if ($records === []): ?><tr><td colspan="8" class="empty-state">Ürün çıkışı kaydı yok.</td></tr><?php endif; ?>
                <?php foreach ($records as $row): ?>
                    <tr class="clickable-row">
                        <td><strong><?= htmlspecialchars((string) $row['operation_number']) ?></strong></td>
                        <td><?= htmlspecialchars((string) $row['plate_number']) ?></td>
                        <td><?= htmlspecialchars((string) $row['sender_display']) ?></td>
                        <td><?= htmlspecialchars((string) $row['product_name']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['silo_code'] . ' - ' . $row['silo_name'])) ?></td>
                        <td><?= htmlspecialchars($formatKg($row['planned_quantity_kg'])) ?></td>
                        <td><span class="badge badge--info"><?= htmlspecialchars($statusLabel((string) $row['status'])) ?></span></td>
                        <td class="table-actions"><a class="button button--small button--primary" href="/outbound-loadings?id=<?= (int) $row['id'] ?>">Aç</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<datalist id="op-company-list"><?php foreach ($companies as $company): ?><option value="<?= htmlspecialchars((string) $company['name']) ?>"></option><?php endforeach; ?></datalist>
<datalist id="op-person-list"><?php foreach (($personSenders ?? []) as $person): ?><option value="<?= htmlspecialchars((string) $person['sender_name']) ?>"></option><?php endforeach; ?></datalist>

<script>
const opCompanies = <?= $companyJson ?: '[]' ?>;
const opPeople = <?= $personJson ?: '[]' ?>;
const opValidation = <?= $validationJson ?: '{"errors":[],"old":[],"general":null}' ?>;
const normalizeOp = (value) => (value || '').toLocaleLowerCase('tr-TR').trim();

function syncSenderForm(form) {
    const type = form.querySelector('input[name="sender_type"]:checked')?.value || 'company';
    form.querySelectorAll('.sender-company').forEach((el) => {
        el.hidden = type !== 'company';
        el.querySelectorAll('input, textarea, select').forEach((field) => {
            field.disabled = type !== 'company';
            field.required = type === 'company' && field.name === 'company_name';
        });
    });
    form.querySelectorAll('.sender-person').forEach((el) => {
        el.hidden = type !== 'person';
        el.querySelectorAll('input, textarea, select').forEach((field) => {
            field.disabled = type !== 'person';
            field.required = type === 'person' && field.name === 'sender_name';
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
    const appendCompany = (record) => {
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
    const appendPerson = (record) => {
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
    const openCompanyRecord = () => {
        const typed = companyInput?.value.trim() || '';
        if (typed === '' || opCompanies.some((company) => normalizeOp(company.name) === normalizeOp(typed)) || form.dataset.senderRecordModal === '1') return;
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
                appendCompany(record);
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
    const openPersonRecord = () => {
        const typed = personInput?.value.trim() || '';
        if (typed === '' || opPeople.some((person) => normalizeOp(person.sender_name) === normalizeOp(typed)) || form.dataset.senderRecordModal === '1') return;
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
                appendPerson(record);
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
    companyInput?.addEventListener('blur', openCompanyRecord);
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
    personInput?.addEventListener('blur', openPersonRecord);
    identity?.addEventListener('input', () => identity.value = identity.value.replace(/\D+/g, '').slice(0, 11));
    syncSenderForm(form);
}

function applyValidation(form, validation) {
    form.querySelectorAll('.field--error').forEach((el) => el.classList.remove('field--error'));
    form.querySelectorAll('.field-error').forEach((el) => el.remove());
    Object.entries(validation.errors || {}).forEach(([field, message]) => {
        const input = form.elements[field];
        if (!input) return;
        const wrapper = input.closest('.field') || input.parentElement;
        wrapper.classList.add('field--error');
        const error = document.createElement('small');
        error.className = 'field-error';
        error.textContent = message;
        wrapper.appendChild(error);
    });
}

document.querySelectorAll('.outbound-operation-form').forEach(bindSenderLookup);
if (Object.keys(opValidation.errors || {}).length > 0) {
    const form = document.querySelector('.outbound-operation-form');
    if (form) applyValidation(form, opValidation);
}
</script>
