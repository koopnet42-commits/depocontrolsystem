<?php

declare(strict_types=1);

$selected = static fn (string $value, mixed $current): string => (string) $value === (string) $current ? 'selected' : '';
$rejectionReasons = $rejectionReasons ?? [];
$conditionalReasons = $conditionalReasons ?? [];
$alerts = [
    'invalid_values' => ['alert--danger', 'Rutubet, hektolitre, yabancı madde ve analiz sonucu zorunludur. Değerleri 0 ile 100 arasında girin.'],
];
$alert = $alerts[$message ?? ''] ?? null;
$acceptanceLabels = [
    'accepted' => ['badge--success', 'Alıma Uygun'],
    'requires_approval' => ['badge--info', 'Yetkili Onayı Gerekli'],
    'rejected' => ['badge--danger', 'Alıma Uygun Değil'],
];
$acceptance = $acceptanceLabels[$analysis['acceptance_status'] ?? 'requires_approval'] ?? $acceptanceLabels['requires_approval'];
$formatKg = static fn (float|int|string|null $kg): string => number_format((float) $kg, 0, ',', '.') . ' kg';
$formatTon = static fn (float|int|string|null $kg): string => number_format(((float) $kg) / 1000, 2, ',', '.') . ' ton';
$senderName = ($record['sender_type'] ?? 'company') === 'person' ? ($record['sender_name'] ?? '-') : ($record['company_name'] ?? '-');
$validation = $validation ?? ['errors' => [], 'old' => [], 'general' => null];
$fieldError = static fn (string $field): string => isset($validation['errors'][$field]) ? '<small class="field-error">' . htmlspecialchars((string) $validation['errors'][$field]) . '</small>' : '';
$fieldClass = static fn (string $field): string => isset($validation['errors'][$field]) ? ' field--error' : '';
?>
<header class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <p class="page-subtitle">Numune analiz değerlerini kaydedin.</p>
    </div>
    <a class="button" href="/sample-analysis">Listeye Dön</a>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel detail-panel">
    <div class="detail-header">
        <div>
            <div class="detail-kicker">Analiz Bekleyen Araç</div>
            <h2><?= htmlspecialchars($record['plate_number']) ?></h2>
        </div>
        <span class="badge badge--info">Analizde</span>
    </div>

    <div class="detail-grid">
        <div>
            <span>Kantar Fişi</span>
            <strong><?= htmlspecialchars($record['ticket_number']) ?></strong>
        </div>
        <div>
            <span>Gönderici</span>
            <strong><?= htmlspecialchars((string) $senderName) ?></strong>
        </div>
        <div>
            <span>Ürün</span>
            <strong><?= htmlspecialchars($record['product_name']) ?></strong>
        </div>
        <div>
            <span>1. Tartım</span>
            <strong><?= htmlspecialchars($formatKg($record['first_weight_kg'])) ?></strong>
            <span class="table-muted"><?= htmlspecialchars($formatTon($record['first_weight_kg'])) ?></span>
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
            <span>Bildirilen miktar</span>
            <strong><?= htmlspecialchars($formatTon($record['expected_quantity_kg'] ?? 0)) ?></strong>
        </div>
        <div>
            <span>Giriş zamanı</span>
            <strong><?= htmlspecialchars((string) ($record['entry_created_at'] ?? $record['first_weighed_at'] ?? '-')) ?></strong>
        </div>
    </div>
</section>

<?php if ($criteria !== null): ?>
    <section class="panel">
        <div class="section-heading">
            <h2>Ürün Kabul Değerleri</h2>
            <span class="badge <?= htmlspecialchars($acceptance[0]) ?>"><?= htmlspecialchars($acceptance[1]) ?></span>
        </div>
        <div class="detail-grid">
            <div><span>Maks. rutubet</span><strong><?= htmlspecialchars((string) ($criteria['max_moisture'] ?? '-')) ?></strong></div>
            <div><span>Min. protein</span><strong><?= htmlspecialchars((string) ($criteria['min_protein'] ?? '-')) ?></strong></div>
            <div><span>Min. hektolitre</span><strong><?= htmlspecialchars((string) ($criteria['min_hectoliter'] ?? '-')) ?></strong></div>
            <div><span>Min. gluten</span><strong><?= htmlspecialchars((string) ($criteria['min_gluten'] ?? '-')) ?></strong></div>
            <div><span>Kaynak</span><strong><?= htmlspecialchars((string) ($criteria['source_name'] ?? '-')) ?></strong></div>
            <div><span>Geçerlilik</span><strong><?= htmlspecialchars((string) ($criteria['source_date'] ?? '-')) ?></strong></div>
        </div>
    </section>
<?php endif; ?>

<section class="panel panel--form panel--wide-form">
    <form action="/sample-analysis/save" method="post" class="form-grid">
        <input type="hidden" name="weighbridge_record_id" value="<?= (int) $record['weighbridge_record_id'] ?>">

        <label class="field<?= $fieldClass('moisture') ?>">
            <span>Rutubet</span>
            <input type="number" name="moisture" value="<?= htmlspecialchars((string) ($analysis['moisture'] ?? '')) ?>" min="0" max="100" step="0.01" required>
            <?= $fieldError('moisture') ?>
        </label>

        <label class="field<?= $fieldClass('protein') ?>">
            <span>Protein</span>
            <input type="number" name="protein" value="<?= htmlspecialchars((string) ($analysis['protein'] ?? '')) ?>" min="0" step="0.01">
            <?= $fieldError('protein') ?>
        </label>

        <label class="field<?= $fieldClass('hectoliter') ?>">
            <span>Hektolitre</span>
            <input type="number" name="hectoliter" value="<?= htmlspecialchars((string) ($analysis['hectoliter'] ?? '')) ?>" min="0" max="100" step="0.01" required>
            <?= $fieldError('hectoliter') ?>
        </label>

        <label class="field<?= $fieldClass('gluten') ?>">
            <span>Gluten</span>
            <input type="number" name="gluten" value="<?= htmlspecialchars((string) ($analysis['gluten'] ?? '')) ?>" min="0" step="0.01">
            <?= $fieldError('gluten') ?>
        </label>

        <label class="field<?= $fieldClass('sunn_pest_rate') ?>">
            <span>Süne oranı</span>
            <input type="number" name="sunn_pest_rate" value="<?= htmlspecialchars((string) ($analysis['sunn_pest_rate'] ?? '')) ?>" min="0" step="0.01">
            <?= $fieldError('sunn_pest_rate') ?>
        </label>

        <label class="field<?= $fieldClass('foreign_material') ?>">
            <span>Yabancı madde</span>
            <input type="number" name="foreign_material" value="<?= htmlspecialchars((string) ($analysis['foreign_material'] ?? '')) ?>" min="0" max="100" step="0.01" required>
            <?= $fieldError('foreign_material') ?>
        </label>

        <label class="field<?= $fieldClass('broken_grain') ?>">
            <span>Kırık tane</span>
            <input type="number" name="broken_grain" value="<?= htmlspecialchars((string) ($analysis['broken_grain'] ?? '')) ?>" min="0" step="0.01">
            <?= $fieldError('broken_grain') ?>
        </label>

        <label class="field<?= $fieldClass('result') ?>">
            <span>Analiz sonucu</span>
            <select name="result" required id="analysis-result">
                <?php foreach ($resultOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $selected($value, $analysis['result']) ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('result') ?>
        </label>

        <label class="field<?= $fieldClass('conditional_reason') ?>" data-conditional-field>
            <span>Şartlı kabul sebebi</span>
            <select name="conditional_reason" id="conditional-reason">
                <option value="">Seçiniz</option>
                <?php foreach ($conditionalReasons as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $selected($value, $analysis['conditional_reason'] ?? '') ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('conditional_reason') ?>
        </label>

        <label class="field field--wide<?= $fieldClass('conditional_note') ?>" data-conditional-field>
            <span>Şartlı kabul açıklaması</span>
            <textarea name="conditional_note" id="conditional-note" rows="3"><?= htmlspecialchars((string) ($analysis['conditional_note'] ?? '')) ?></textarea>
            <?= $fieldError('conditional_note') ?>
        </label>

        <label class="field<?= $fieldClass('rejection_reason') ?>" data-rejection-field>
            <span>Ret sebebi</span>
            <select name="rejection_reason" id="rejection-reason">
                <option value="">Seçiniz</option>
                <?php foreach ($rejectionReasons as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $selected($value, $analysis['rejection_reason'] ?? '') ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('rejection_reason') ?>
        </label>

        <label class="field field--wide<?= $fieldClass('rejection_note') ?>" data-rejection-field>
            <span>Ret açıklaması</span>
            <textarea name="rejection_note" id="rejection-note" rows="3"><?= htmlspecialchars((string) ($analysis['rejection_note'] ?? '')) ?></textarea>
            <?= $fieldError('rejection_note') ?>
        </label>

        <label class="field field--wide">
            <span>Açıklama</span>
            <textarea name="notes" rows="4"><?= htmlspecialchars((string) ($analysis['notes'] ?? '')) ?></textarea>
        </label>

        <div class="form-actions">
            <button class="button button--primary" type="submit" id="analysis-submit">Analizi Kaydet</button>
            <a class="button button--ghost" href="/sample-analysis">Vazgeç</a>
        </div>
    </form>
</section>

<script>
    const resultSelect = document.getElementById('analysis-result');
    const conditionalReason = document.getElementById('conditional-reason');
    const conditionalNote = document.getElementById('conditional-note');
    const reasonSelect = document.getElementById('rejection-reason');
    const rejectionNote = document.getElementById('rejection-note');
    const submitButton = document.getElementById('analysis-submit');

    function syncRejectionFields() {
        const rejected = resultSelect.value === 'rejected';
        const conditional = resultSelect.value === 'conditional';
        document.querySelectorAll('[data-conditional-field]').forEach((field) => {
            field.hidden = !conditional;
            field.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !conditional;
            });
        });
        document.querySelectorAll('[data-rejection-field]').forEach((field) => {
            field.hidden = !rejected;
            field.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !rejected;
            });
        });
        conditionalReason.required = conditional;
        conditionalNote.required = conditional && conditionalReason.value === 'other';
        reasonSelect.required = rejected;
        rejectionNote.required = rejected && reasonSelect.value === 'other';
        submitButton.textContent = rejected ? 'Ret Fişi Oluştur / Yazdır' : 'Analizi Kaydet';
    }

    resultSelect.addEventListener('change', syncRejectionFields);
    conditionalReason.addEventListener('change', syncRejectionFields);
    reasonSelect.addEventListener('change', syncRejectionFields);
    syncRejectionFields();
</script>
