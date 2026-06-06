<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'name' => Config::env('APP_NAME', 'Depo Otomasyon Sistemi'),
    'env' => Config::env('APP_ENV', 'local'),
    'debug' => filter_var(Config::env('APP_DEBUG', true), FILTER_VALIDATE_BOOL),
    'url' => Config::env('APP_URL', 'http://127.0.0.1:8000'),
    'timezone' => Config::env('APP_TIMEZONE', 'Europe/Istanbul'),
    'default_role' => Config::env('APP_DEFAULT_ROLE', 'admin'),
];
