<?php

declare(strict_types=1);

$selected = static fn (string $value, mixed $current): string => (string) $value === (string) $current ? 'selected' : '';
?>
<header class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <p class="page-subtitle">Analiz eşiklerini ve hedef siloyu tanımlayın.</p>
    </div>
    <a class="button" href="/silo-rules">Listeye Dön</a>
</header>

<section class="panel panel--form panel--wide-form">
    <form action="<?= htmlspecialchars($action) ?>" method="post" class="form-grid">
        <?php if (! empty($rule['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $rule['id'] ?>">
        <?php endif; ?>

        <label class="field field--wide">
            <span>Kural adı</span>
            <input type="text" name="name" value="<?= htmlspecialchars((string) $rule['name']) ?>" maxlength="140" required>
        </label>

        <label class="field">
            <span>Ürün tipi</span>
            <select name="product_id" required>
                <option value="">Seçiniz</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>" <?= $selected((string) $product['id'], $rule['product_id']) ?>>
                        <?= htmlspecialchars($product['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Hedef silo</span>
            <select name="silo_id" required>
                <option value="">Seçiniz</option>
                <?php foreach ($silos as $silo): ?>
                    <option value="<?= (int) $silo['id'] ?>" <?= $selected((string) $silo['id'], $rule['silo_id']) ?>>
                        <?= htmlspecialchars($silo['code'] . ' - ' . $silo['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Minimum rutubet</span>
            <input type="number" name="min_moisture" value="<?= htmlspecialchars((string) ($rule['min_moisture'] ?? '')) ?>" step="0.01">
        </label>

        <label class="field">
            <span>Maksimum rutubet</span>
            <input type="number" name="max_moisture" value="<?= htmlspecialchars((string) ($rule['max_moisture'] ?? '')) ?>" step="0.01">
        </label>

        <label class="field">
            <span>Minimum protein</span>
            <input type="number" name="min_protein" value="<?= htmlspecialchars((string) ($rule['min_protein'] ?? '')) ?>" step="0.01">
        </label>

        <label class="field">
            <span>Minimum hektolitre</span>
            <input type="number" name="min_hectoliter" value="<?= htmlspecialchars((string) ($rule['min_hectoliter'] ?? '')) ?>" step="0.01">
        </label>

        <label class="field">
            <span>Maksimum yabancı madde</span>
            <input type="number" name="max_foreign_material" value="<?= htmlspecialchars((string) ($rule['max_foreign_material'] ?? '')) ?>" step="0.01">
        </label>

        <label class="field">
            <span>Maksimum süne oranı</span>
            <input type="number" name="max_sunn_pest_rate" value="<?= htmlspecialchars((string) ($rule['max_sunn_pest_rate'] ?? '')) ?>" step="0.01">
        </label>

        <label class="field">
            <span>Öncelik</span>
            <input type="number" name="priority" value="<?= htmlspecialchars((string) $rule['priority']) ?>" min="1" step="1">
        </label>

        <label class="check-field">
            <input type="checkbox" name="is_active" value="1" <?= (int) $rule['is_active'] === 1 ? 'checked' : '' ?>>
            <span>Aktif</span>
        </label>

        <div class="form-actions">
            <button class="button button--primary" type="submit">Kaydet</button>
            <a class="button button--ghost" href="/silo-rules">Vazgeç</a>
        </div>
    </form>
</section>
