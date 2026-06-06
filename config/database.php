<?php

declare(strict_types=1);

use App\Core\Config;

$connection = Config::env('DB_CONNECTION', 'mysql');
$host = Config::env('DB_HOST', '127.0.0.1');
$port = Config::env('DB_PORT', '3306');
$database = Config::env('DB_DATABASE', 'depo_otomasyon');
$sqliteDatabase = Config::env('DB_DATABASE', BASE_PATH . '/storage/database.sqlite');

return [
    'default' => $connection,
    'connections' => [
        'sqlite' => [
            'dsn' => 'sqlite:' . $sqliteDatabase,
            'username' => null,
            'password' => null,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],
        'mysql' => [
            'dsn' => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            'username' => Config::env('DB_USERNAME', 'root'),
            'password' => Config::env('DB_PASSWORD', ''),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],
];
