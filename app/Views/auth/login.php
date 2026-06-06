<?php

declare(strict_types=1);

$alerts = [
    'failed' => ['alert--danger', 'E-posta veya şifre hatalı ya da kullanıcı pasif.'],
    'required' => ['alert--danger', 'Devam etmek için giriş yapmalısınız.'],
];
$alert = $alerts[$message] ?? null;
?>
<main class="login-shell">
    <section class="login-card">
        <div class="login-card__brand">
            <span>DOS</span>
            <div>
                <h1>Depo Otomasyon Sistemi</h1>
                <p>Güvenli işletme paneli</p>
            </div>
        </div>

        <?php if ($alert !== null): ?>
            <div class="alert <?= htmlspecialchars($alert[0]) ?>"><?= htmlspecialchars($alert[1]) ?></div>
        <?php endif; ?>

        <form action="/login" method="post" class="login-form">
            <label class="field">
                <span>E-posta</span>
                <input type="email" name="email" autocomplete="username" required autofocus>
            </label>
            <label class="field">
                <span>Şifre</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="button button--primary" type="submit">Giriş Yap</button>
        </form>
    </section>
</main>
