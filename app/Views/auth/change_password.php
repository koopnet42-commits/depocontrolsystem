<?php

declare(strict_types=1);

$alerts = [
    'invalid' => ['alert--danger', 'Şifre en az 10 karakter olmalı ve tekrar alanı ile eşleşmelidir.'],
];
$alert = $alerts[$message] ?? null;
?>
<main class="login-shell">
    <section class="login-card">
        <div class="login-card__brand">
            <span>DOS</span>
            <div>
                <h1>Şifre Değiştir</h1>
                <p>Devam etmek için yeni şifre belirleyin.</p>
            </div>
        </div>

        <?php if ($alert !== null): ?>
            <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
        <?php endif; ?>

        <form action="/password/change" method="post" class="login-form">
            <label class="field">
                <span>Yeni şifre</span>
                <input type="password" name="password" autocomplete="new-password" minlength="10" required autofocus>
            </label>
            <label class="field">
                <span>Yeni şifre tekrar</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" required>
            </label>
            <button class="button button--primary" type="submit">Şifreyi Değiştir</button>
        </form>
    </section>
</main>
