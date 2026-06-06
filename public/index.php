<?php

declare(strict_types=1);

use App\Core\Router;

require dirname(__DIR__) . '/bootstrap/app.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$assetPath = realpath(__DIR__ . $path);

if ($assetPath !== false && str_starts_with($assetPath, __DIR__) && is_file($assetPath)) {
    $extension = pathinfo($assetPath, PATHINFO_EXTENSION);
    $types = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
    ];
    header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
    readfile($assetPath);
    return;
}

$router = new Router();

require BASE_PATH . '/routes/web.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
