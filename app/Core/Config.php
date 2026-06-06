<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $env = [];

    public static function load(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            self::$env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        return self::$env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
