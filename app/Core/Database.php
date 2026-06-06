<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require BASE_PATH . '/config/database.php';
        $default = $config['default'];
        $database = $config['connections'][$default];

        try {
            self::$connection = new PDO(
                $database['dsn'],
                $database['username'],
                $database['password'],
                $database['options']
            );

            if (self::$connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                self::$connection->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
            }
        } catch (PDOException $exception) {
            throw new PDOException('Veritabanı bağlantısı kurulamadı.', (int) $exception->getCode(), $exception);
        }

        return self::$connection;
    }
}
