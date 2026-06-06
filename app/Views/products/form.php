<?php

declare(strict_types=1);

$criteria = array_merge([
    'min_protein' => '',
    'max_moisture' => '',
    'min_hectoliter' => '',
    'max_sunn_pest_rate' => '',
    'max_foreign_matter' => '',
    'max_broken_grain' => '',
    'min_gluten' => '',
    'source_type' => 'manual',
    'source_name' => '',
    'source_url' => '',
    'source_date' => '',
    'notes' => '',
], $criteria ?? []);
?>
<header class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <p class="page-subtitle">Buğday, arpa, mısır gibi ürün türlerini tanımlayın.</p>
    </div>
    <a class="button" href="/products">Listeye Dön</a>
</header>

<section class="panel panel--form">
    <form action="<?= htmlspecialchars($action) ?>" method="post" class="form-grid">
        <?php if (! empty($product['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
        <?php endif; ?>

        <label class="field field--wide">
            <span>Ürün adı</span>
            <input
                type="text"
                name="name"
                value="<?= htmlspecialchars((string) $product['name']) ?>"
                maxlength="140"
                placeholder="Makarnalık buğday"
                required
            >
        </label>

        <label class="field">
            <span>Ürün kodu</span>
            <input type="text" name="code" value="<?= htmlspecialchars((string) ($product['code'] ?? '')) ?>" maxlength="40">
        </label>

        <label class="field field--wide">
            <span>Açıklama</span>
            <textarea name="description" rows="4"><?= htmlspecialchars((string) ($product['description'] ?? '')) ?></textarea>
        </label>

        <label class="check-field">
            <input type="checkbox" name="is_active" value="1" <?= (int) $product['is_active'] === 1 ? 'checked' : '' ?>>
            <span>Aktif</span>
        </label>

        <div class="field field--wide criteria-panel">
            <div class="section-heading">
                <h2>Kabul Gören Alım Değerleri</h2>
                <button class="button button--small" type="button" id="fetch-official-criteria">Resmi Kaynaktan Değerleri Getir</button>
            </div>
            <div id="official-preview" class="alert alert--info" hidden></div>
        </div>

        <label class="field"><span>Minimum protein</span><input type="number" step="0.01" name="min_protein" value="<?= htmlspecialchars((string) $criteria['min_protein']) ?>"></label>
        <label class="field"><span>Maksimum rutubet</span><input type="number" step="0.01" name="max_moisture" value="<?= htmlspecialchars((string) $criteria['max_moisture']) ?>"></label>
        <label class="field"><span>Minimum hektolitre</span><input type="number" step="0.01" name="min_hectoliter" value="<?= htmlspecialchars((string) $criteria['min_hectoliter']) ?>"></label>
        <label class="field"><span>Maksimum süne oranı</span><input type="number" step="0.01" name="max_sunn_pest_rate" value="<?= htmlspecialchars((string) $criteria['max_sunn_pest_rate']) ?>"></label>
        <label class="field"><span>Maksimum yabancı madde</span><input type="number" step="0.01" name="max_foreign_matter" value="<?= htmlspecialchars((string) $criteria['max_foreign_matter']) ?>"></label>
        <label class="field"><span>Maksimum kırık tane</span><input type="number" step="0.01" name="max_broken_grain" value="<?= htmlspecialchars((string) $criteria['max_broken_grain']) ?>"></label>
        <label class="field"><span>Minimum gluten</span><input type="number" step="0.01" name="min_gluten" value="<?= htmlspecialchars((string) $criteria['min_gluten']) ?>"></label>
        <label class="field"><span>Kaynak tipi</span><select name="source_type"><option value="manual" <?= $criteria['source_type'] === 'manual' ? 'selected' : '' ?>>Manuel</option><option value="official_source" <?= $criteria['source_type'] === 'official_source' ? 'selected' : '' ?>>Resmi kaynak</option></select></label>
        <label class="field"><span>Kaynak bilgisi</span><input name="source_name" value="<?= htmlspecialchars((string) $criteria['source_name']) ?>"></label>
        <label class="field"><span>Kaynak URL</span><input name="source_url" value="<?= htmlspecialchars((string) $criteria['source_url']) ?>"></label>
        <label class="field"><span>Geçerlilik tarihi</span><input type="date" name="source_date" value="<?= htmlspecialchars((string) $criteria['source_date']) ?>"></label>
        <label class="field field--wide"><span>Açıklama</span><textarea name="criteria_notes" rows="3"><?= htmlspecialchars((string) ($criteria['notes'] ?? '')) ?></textarea></label>

        <div class="form-actions">
            <button class="button button--primary" type="submit">Kaydet</button>
            <a class="button button--ghost" href="/products">Vazgeç</a>
        </div>
    </form>
</section>

<script>
    const officialButton = document.getElementById('fetch-official-criteria');
    const previewBox = document.getElementById('official-preview');
    officialButton?.addEventListener('click', async () => {
        const productName = document.querySelector('input[name="name"]').value.trim();
        if (!productName) {
            window.alert('Önce ürün adını girin.');
            return;
        }

        const response = await fetch('/products/official-criteria-preview?product_name=' + encodeURIComponent(productName), {
            headers: { 'Accept': 'application/json' },
        });
        const data = await response.json();
        if (!data.success) {
            return;
        }

        Object.entries(data.criteria).forEach(([key, value]) => {
            const field = document.querySelector(`[name="${key}"]`);
            if (field) {
                field.value = value ?? '';
            }
        });
        previewBox.hidden = false;
        previewBox.textContent = `${data.criteria.source_name} simülasyonundan değerler getirildi. Kaydet butonuna basmadan aktif kullanıma alınmaz.`;
    });
</script>
