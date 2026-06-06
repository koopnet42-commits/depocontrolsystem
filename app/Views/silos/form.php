<?php

declare(strict_types=1);

$selected = static fn (string $value, mixed $current): string => (string) $value === (string) $current ? 'selected' : '';
$alerts = [
    'duplicate_code' => ['alert--danger', 'Bu silo kodu zaten kullanılıyor. Lütfen farklı bir kod girin veya ürün seçerek yeni kod önerisi alın.'],
    'missing_product_code' => ['alert--danger', 'Bu ürün için ürün kodu tanımlanmamış.'],
    'invalid' => ['alert--danger', 'Silo bilgilerini kontrol edin. Ürün tipi, silo kodu, silo adı zorunludur; doluluk kapasiteden büyük olamaz.'],
];
$alert = $alerts[$message ?? ''] ?? null;
$silo = array_merge([
    'id' => null,
    'code' => '',
    'name' => '',
    'product_id' => '',
    'capacity_kg' => '',
    'current_stock_kg' => '',
    'visual_type' => 'vertical',
    'description' => '',
    'is_active' => 1,
], $silo ?? []);
$criteriaByProduct = $criteriaByProduct ?? [];
$toTonValue = static fn (mixed $kg): string => $kg === '' || $kg === null ? '' : rtrim(rtrim(number_format(((float) $kg) / 1000, 3, '.', ''), '0'), '.');
$qualityFields = [
    ['label' => 'Rutubet', 'min' => 'min_moisture', 'max' => 'max_moisture'],
    ['label' => 'Protein', 'min' => 'min_protein', 'max' => 'max_protein'],
    ['label' => 'Hektolitre', 'min' => 'min_hectoliter', 'max' => 'max_hectoliter'],
    ['label' => 'Gluten', 'min' => 'min_gluten', 'max' => 'max_gluten'],
    ['label' => 'Süne oranı', 'min' => 'min_sunn_pest_rate', 'max' => 'max_sunn_pest_rate'],
    ['label' => 'Yabancı madde', 'min' => 'min_foreign_material', 'max' => 'max_foreign_material'],
    ['label' => 'Kırık tane', 'min' => 'min_broken_grain', 'max' => 'max_broken_grain'],
];
$validation = $validation ?? ['errors' => [], 'old' => [], 'general' => null];
$fieldError = static fn (string $field): string => isset($validation['errors'][$field]) ? '<small class="field-error">' . htmlspecialchars((string) $validation['errors'][$field]) . '</small>' : '';
$fieldClass = static fn (string $field): string => isset($validation['errors'][$field]) ? ' field--error' : '';
?>
<header class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <p class="page-subtitle">Silo temel bilgileri ve kalite aralıklarını girin.</p>
    </div>
    <a class="button" href="/silos">Listeye Dön</a>
</header>

<?php if ($alert !== null): ?>
    <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
<?php endif; ?>

<section class="panel panel--form panel--wide-form">
    <form action="<?= htmlspecialchars($action) ?>" method="post" class="form-grid">
        <?php if (! empty($silo['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $silo['id'] ?>">
        <?php endif; ?>

        <label class="field field--wide<?= $fieldClass('product_id') ?>">
            <span>Ürün tipi</span>
            <select id="silo-product" name="product_id">
                <option value="">Seçiniz</option>
                <?php foreach ($products as $product): ?>
                    <option
                        value="<?= (int) $product['id'] ?>"
                        data-code="<?= htmlspecialchars((string) ($product['code'] ?? '')) ?>"
                        <?= $selected((string) $product['id'], $silo['product_id']) ?>
                    >
                        <?= htmlspecialchars($product['name']) ?><?= ! empty($product['code']) ? ' - ' . htmlspecialchars($product['code']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('product_id') ?>
        </label>

        <label class="field<?= $fieldClass('code') ?>">
            <span>Silo kodu</span>
            <input
                id="silo-code"
                type="text"
                name="code"
                value="<?= htmlspecialchars((string) ($silo['code'] ?? '')) ?>"
                maxlength="80"
                placeholder="ÜRÜN-KODU-001"
                required
            >
            <small class="field-help">Ürün tipine göre otomatik oluşturuldu.</small>
            <?= $fieldError('code') ?>
        </label>

        <label class="field<?= $fieldClass('name') ?>">
            <span>Silo adı</span>
            <input id="silo-name" type="text" name="name" value="<?= htmlspecialchars((string) $silo['name']) ?>" maxlength="120" required>
            <small class="field-help">Ürün tipine göre otomatik oluşturuldu.</small>
            <?= $fieldError('name') ?>
        </label>

        <label class="field">
            <span>Görünüm tipi</span>
            <select data-product-required name="visual_type">
                <option value="vertical" <?= $selected('vertical', $silo['visual_type']) ?>>Dikey silo</option>
                <option value="horizontal" <?= $selected('horizontal', $silo['visual_type']) ?>>Yatay silo</option>
            </select>
        </label>

        <label class="field<?= $fieldClass('capacity_ton') ?>">
            <span>Kapasite ton</span>
            <input data-product-required type="number" name="capacity_ton" value="<?= htmlspecialchars($toTonValue($silo['capacity_kg'])) ?>" min="0" step="0.001">
            <?= $fieldError('capacity_ton') ?>
        </label>

        <label class="field<?= $fieldClass('current_stock_ton') ?>">
            <span>Mevcut doluluk ton</span>
            <input data-product-required type="number" name="current_stock_ton" value="<?= htmlspecialchars($toTonValue($silo['current_stock_kg'])) ?>" min="0" step="0.001">
            <?= $fieldError('current_stock_ton') ?>
        </label>

        <div class="quality-grid field--wide">
            <div class="quality-grid__title">Silo için kullanılacak kalite sınırları</div>
            <div id="product-criteria-preview" class="quality-source-note">Ürün seçildiğinde kabul değerleri burada gösterilir.</div>
            <input type="hidden" name="quality_overridden" id="quality-overridden" value="0">
            <?php foreach ($qualityFields as $field): ?>
                <div class="quality-row">
                    <span><?= htmlspecialchars($field['label']) ?></span>
                    <input
                        type="number"
                        name="<?= htmlspecialchars($field['min']) ?>"
                        value="<?= htmlspecialchars((string) ($silo[$field['min']] ?? '')) ?>"
                        step="0.01"
                        placeholder="Min"
                        data-product-required
                    >
                    <input
                        type="number"
                        name="<?= htmlspecialchars($field['max']) ?>"
                        value="<?= htmlspecialchars((string) ($silo[$field['max']] ?? '')) ?>"
                        step="0.01"
                        placeholder="Maks"
                        data-product-required
                    >
                </div>
            <?php endforeach; ?>
        </div>

        <label class="field field--wide<?= $fieldClass('description') ?>">
            <span>Açıklama</span>
            <textarea data-product-required name="description" rows="4"><?= htmlspecialchars((string) ($silo['description'] ?? '')) ?></textarea>
            <?= $fieldError('description') ?>
        </label>

        <label class="check-field">
            <input type="checkbox" name="is_active" value="1" <?= (int) $silo['is_active'] === 1 ? 'checked' : '' ?>>
            <span>Aktif</span>
        </label>

        <div class="form-actions">
            <button class="button button--primary" type="submit">Kaydet</button>
            <a class="button button--ghost" href="/silos">Vazgeç</a>
        </div>
    </form>
</section>

<script>
    const acceptanceCriteria = <?= json_encode($criteriaByProduct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const productSelect = document.getElementById('silo-product');
    const codeInput = document.getElementById('silo-code');
    const nameInput = document.getElementById('silo-name');
    const productRequiredFields = document.querySelectorAll('[data-product-required], #silo-code, #silo-name');
    const siloId = <?= (int) ($silo['id'] ?? 0) ?>;
    const criteriaPreview = document.getElementById('product-criteria-preview');
    const overrideInput = document.getElementById('quality-overridden');
    const qualityMap = {
        max_moisture: 'max_moisture',
        min_protein: 'min_protein',
        min_hectoliter: 'min_hectoliter',
        min_gluten: 'min_gluten',
        max_sunn_pest_rate: 'max_sunn_pest_rate',
        max_foreign_material: 'max_foreign_matter',
        max_broken_grain: 'max_broken_grain',
    };

    function setDependentFieldsState() {
        const hasProduct = Boolean(productSelect.value);
        productRequiredFields.forEach((field) => {
            field.disabled = !hasProduct;
        });

        if (!hasProduct) {
            codeInput.value = '';
            nameInput.value = '';
        }
    }

    async function refreshSiloCode() {
        const productId = productSelect.value;
        if (!productId) {
            setDependentFieldsState();
            return;
        }

        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const productCode = selectedOption.getAttribute('data-code') || '';

        nameInput.value = selectedOption.textContent.split(' - ')[0].trim();

        if (!productCode) {
            codeInput.value = '';
            window.alert('Bu ürün için ürün kodu tanımlanmamış.');
            setDependentFieldsState();
            return;
        }

        const params = new URLSearchParams({ product_id: productId });
        if (siloId > 0) {
            params.set('exclude_id', String(siloId));
        }

        const response = await fetch(`/silos/next-code?${params.toString()}`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await response.json();
        if (data.code) {
            codeInput.value = data.code;
        }

        applyProductCriteria(productId);
        setDependentFieldsState();
    }

    function applyProductCriteria(productId) {
        const criteria = acceptanceCriteria[productId];
        if (!criteria) {
            criteriaPreview.textContent = 'Bu ürün için onaylı kabul değeri bulunamadı.';
            return;
        }

        Object.entries(qualityMap).forEach(([siloField, criteriaField]) => {
            const input = document.querySelector(`[name="${siloField}"]`);
            if (input && !input.value) {
                input.value = criteria[criteriaField] ?? '';
            }
        });

        criteriaPreview.textContent = `Kaynak: ${criteria.source_name || '-'} / Tarih: ${criteria.source_date || '-'} / Tip: ${criteria.source_type || '-'}`;
    }

    document.querySelectorAll('.quality-row input').forEach((input) => {
        input.addEventListener('change', () => {
            overrideInput.value = '1';
        });
    });

    productSelect.addEventListener('change', refreshSiloCode);

    if (!codeInput.value && productSelect.value) {
        refreshSiloCode();
    } else {
        if (productSelect.value) {
            applyProductCriteria(productSelect.value);
        }
        setDependentFieldsState();
    }
</script>
