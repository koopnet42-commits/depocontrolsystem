<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class CryptoService
{
    public static function encrypt(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);

        return $encrypted === false ? null : base64_encode($iv . $encrypted);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = base64_decode($value, true);

        if ($raw === false || strlen($raw) <= 16) {
            return null;
        }

        $iv = substr($raw, 0, 16);
        $payload = substr($raw, 16);
        $decrypted = openssl_decrypt($payload, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);

        return $decrypted === false ? null : $decrypted;
    }

    private static function key(): string
    {
        return hash('sha256', (string) Config::env('APP_KEY', 'depo-otomasyon-local-key'), true);
    }
}
