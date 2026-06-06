<?php

declare(strict_types=1);

$app = require BASE_PATH . '/config/app.php';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title . ' | ' . $app['name']) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="error-shell">
        <section class="error-card">
            <span class="error-code"><?= (int) $code ?></span>
            <h1><?= htmlspecialchars($title) ?></h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a class="button button--primary" href="/dashboard">Dashboarda Dön</a>
        </section>
    </main>
</body>
</html>
