<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class SenderPersonRegistry
{
    public static function all(): array
    {
        self::ensureTable();

        return Database::connection()
            ->query(
                'SELECT sender_name, identity_number, sender_phone, sender_address
                 FROM (
                    SELECT full_name AS sender_name, identity_number, phone AS sender_phone, address AS sender_address, updated_at AS sort_date
                    FROM sender_people
                    WHERE is_active = 1
                    UNION ALL
                    SELECT sender_name, identity_number, sender_phone, sender_address, COALESCE(updated_at, created_at) AS sort_date
                    FROM delivery_notifications
                    WHERE sender_type = "person" AND sender_name IS NOT NULL AND sender_name <> ""
                 )
                 GROUP BY sender_name, identity_number, sender_phone, sender_address
                 ORDER BY sender_name ASC
                 LIMIT 300'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findOrCreate(array $payload): array
    {
        self::ensureTable();

        $fullName = trim((string) ($payload['full_name'] ?? $payload['sender_name'] ?? ''));
        $identityNumber = self::nullable($payload['identity_number'] ?? null);
        $phone = self::nullable($payload['phone'] ?? $payload['sender_phone'] ?? null);
        $address = self::nullable($payload['address'] ?? $payload['sender_address'] ?? null);

        if ($fullName === '') {
            throw new \InvalidArgumentException('Şahıs adı zorunludur.');
        }

        $existing = self::findExisting($fullName, $identityNumber, $phone);
        if ($existing !== null) {
            return $existing;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO sender_people (full_name, identity_number, phone, address, is_active, created_at, updated_at)
             VALUES (:full_name, :identity_number, :phone, :address, 1, NOW(), NOW())'
        );
        $statement->execute([
            'full_name' => $fullName,
            'identity_number' => $identityNumber,
            'phone' => $phone,
            'address' => $address,
        ]);

        return self::findById((int) Database::connection()->lastInsertId());
    }

    public static function ensureTable(): void
    {
        $driver = Database::connection()->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            Database::connection()->exec(
                'CREATE TABLE IF NOT EXISTS sender_people (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    full_name TEXT NOT NULL,
                    identity_number TEXT NULL,
                    phone TEXT NULL,
                    address TEXT NULL,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )'
            );
            Database::connection()->exec('CREATE INDEX IF NOT EXISTS idx_sender_people_identity ON sender_people(identity_number)');
            Database::connection()->exec('CREATE INDEX IF NOT EXISTS idx_sender_people_phone ON sender_people(phone)');
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS sender_people (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(180) NOT NULL,
                identity_number VARCHAR(20) NULL,
                phone VARCHAR(40) NULL,
                address TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function findExisting(string $fullName, ?string $identityNumber, ?string $phone): ?array
    {
        if ($identityNumber !== null) {
            $statement = Database::connection()->prepare('SELECT * FROM sender_people WHERE identity_number = :identity_number AND is_active = 1 LIMIT 1');
            $statement->execute(['identity_number' => $identityNumber]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            if ($record !== false) {
                return self::format($record);
            }
        }

        if ($phone !== null) {
            $statement = Database::connection()->prepare('SELECT * FROM sender_people WHERE phone = :phone AND is_active = 1 LIMIT 1');
            $statement->execute(['phone' => $phone]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            if ($record !== false) {
                return self::format($record);
            }
        }

        $statement = Database::connection()->prepare('SELECT * FROM sender_people WHERE lower(full_name) = lower(:full_name) AND is_active = 1 LIMIT 1');
        $statement->execute(['full_name' => $fullName]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : self::format($record);
    }

    private static function findById(int $id): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM sender_people WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return self::format($record ?: []);
    }

    private static function format(array $record): array
    {
        return [
            'id' => (int) ($record['id'] ?? 0),
            'sender_name' => (string) ($record['full_name'] ?? ''),
            'identity_number' => (string) ($record['identity_number'] ?? ''),
            'sender_phone' => (string) ($record['phone'] ?? ''),
            'sender_address' => (string) ($record['address'] ?? ''),
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
