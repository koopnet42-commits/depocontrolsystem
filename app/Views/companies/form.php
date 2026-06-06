<?php

declare(strict_types=1);

$company = array_merge([
    'id' => null,
    'name' => '',
    'tax_number' => '',
    'phone' => '',
    'contact_person' => '',
    'address' => '',
    'is_active' => 1,
], $company ?? []);
$message = (string) ($_GET['message'] ?? '');
?>
<header class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <p class="page-subtitle">Firma temel bilgilerini girin.</p>
    </div>
    <a class="button" href="/companies">Listeye Dön</a>
</header>

<?php if ($message === 'invalid'): ?>
    <div class="alert alert--danger">Firma adı boş olamaz. Lütfen firma adını girip tekrar kaydedin.</div>
<?php endif; ?>

<section class="panel panel--form">
    <form action="<?= htmlspecialchars($action) ?>" method="post" class="form-grid">
        <?php if (! empty($company['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $company['id'] ?>">
        <?php endif; ?>

        <label class="field field--wide">
            <span>Firma adı</span>
            <input type="text" name="name" value="<?= htmlspecialchars((string) ($company['name'] ?? '')) ?>" maxlength="180" required>
        </label>

        <label class="field">
            <span>Vergi no</span>
            <input type="text" name="tax_number" value="<?= htmlspecialchars((string) ($company['tax_number'] ?? '')) ?>" maxlength="40">
        </label>

        <label class="field">
            <span>Telefon</span>
            <input type="text" name="phone" value="<?= htmlspecialchars((string) ($company['phone'] ?? '')) ?>" maxlength="40">
        </label>

        <label class="field field--wide">
            <span>Yetkili kişi</span>
            <input type="text" name="contact_person" value="<?= htmlspecialchars((string) ($company['contact_person'] ?? '')) ?>" maxlength="140">
        </label>

        <label class="field field--wide">
            <span>Adres</span>
            <textarea name="address" rows="4"><?= htmlspecialchars((string) ($company['address'] ?? '')) ?></textarea>
        </label>

        <label class="check-field">
            <input type="checkbox" name="is_active" value="1" <?= (int) ($company['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
            <span>Aktif</span>
        </label>

        <div class="form-actions">
            <button class="button button--primary" type="submit">Kaydet</button>
            <a class="button button--ghost" href="/companies">Vazgeç</a>
        </div>
    </form>
</section>
