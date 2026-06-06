<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class DriverVehicleRegistry
{
    public static function ensureSchema(): void
    {
        $database = Database::connection();
        $driver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);

        self::ensureVehicleColumns($database, $driver);

        if ($driver === 'sqlite') {
            $database->exec(
                'CREATE TABLE IF NOT EXISTS drivers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    full_name TEXT NULL,
                    phone TEXT NULL,
                    identity_number TEXT NULL,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )'
            );
            $database->exec('CREATE UNIQUE INDEX IF NOT EXISTS drivers_identity_number_unique ON drivers(identity_number) WHERE identity_number IS NOT NULL AND identity_number <> ""');
            $database->exec('CREATE INDEX IF NOT EXISTS drivers_phone_index ON drivers(phone)');
            $database->exec(
                'CREATE TABLE IF NOT EXISTS driver_vehicle_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    driver_id INTEGER NOT NULL,
                    vehicle_id INTEGER NOT NULL,
                    entry_id INTEGER NULL,
                    company_id INTEGER NULL,
                    used_at TEXT DEFAULT CURRENT_TIMESTAMP
                )'
            );
            $database->exec('CREATE INDEX IF NOT EXISTS driver_vehicle_history_driver_id_index ON driver_vehicle_history(driver_id)');
            $database->exec('CREATE INDEX IF NOT EXISTS driver_vehicle_history_vehicle_id_index ON driver_vehicle_history(vehicle_id)');
            return;
        }

        $database->exec(
            'CREATE TABLE IF NOT EXISTS drivers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(160) NULL,
                phone VARCHAR(40) NULL,
                identity_number VARCHAR(20) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY drivers_identity_number_unique (identity_number),
                KEY drivers_phone_index (phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS driver_vehicle_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                driver_id BIGINT UNSIGNED NOT NULL,
                vehicle_id BIGINT UNSIGNED NOT NULL,
                entry_id BIGINT UNSIGNED NULL,
                company_id BIGINT UNSIGNED NULL,
                used_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY driver_vehicle_history_driver_id_index (driver_id),
                KEY driver_vehicle_history_vehicle_id_index (vehicle_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function normalizePlate(string $plate): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($plate)));
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        return $digits === '' ? null : $digits;
    }

    public static function lookup(array $input): array
    {
        self::ensureSchema();
        self::seedDriversFromVehicles();
        self::syncVehicleNormalizedPlates();

        $plate = self::normalizePlate((string) ($input['plate'] ?? ''));
        $vehicle = $plate === '' ? null : self::vehicleByPlate($plate);
        $driver = self::driverMatch($input, $vehicle);

        return [
            'ok' => true,
            'vehicle' => $vehicle === null ? null : self::vehiclePayload($vehicle),
            'driver' => $driver === null ? null : self::driverPayload($driver, $vehicle),
        ];
    }

    public static function upsertFromRequest(int $companyId, array $input, ?int $entryId = null): int
    {
        self::ensureSchema();
        self::syncVehicleNormalizedPlates();

        $plate = self::normalizePlate((string) ($input['plate_number'] ?? ''));
        $vehicle = self::vehicleByPlate($plate);
        $vehicleAction = (string) ($input['vehicle_match_action'] ?? 'update');
        $driverAction = (string) ($input['driver_match_action'] ?? 'update');
        $brand = self::nullish($input['vehicle_brand'] ?? null);
        $model = self::nullish($input['vehicle_model'] ?? null);
        $driverName = self::nullish($input['driver_name'] ?? null);
        $driverPhone = self::normalizePhone($input['driver_phone'] ?? null);
        $driverIdentity = self::digits($input['driver_identity_number'] ?? null);

        if ($vehicle === null) {
            Database::connection()->prepare(
                'INSERT INTO vehicles (plate_number, normalized_plate, brand, model, driver_name, driver_phone, company_id, is_active)
                 VALUES (:plate_number, :normalized_plate, :brand, :model, :driver_name, :driver_phone, :company_id, 1)'
            )->execute([
                'plate_number' => $plate,
                'normalized_plate' => $plate,
                'brand' => $brand,
                'model' => $model,
                'driver_name' => $driverName,
                'driver_phone' => $driverPhone,
                'company_id' => $companyId > 0 ? $companyId : null,
            ]);
            $vehicleId = (int) Database::connection()->lastInsertId();
        } else {
            $vehicleId = (int) $vehicle['id'];
            if ($vehicleAction === 'update') {
                $old = ['brand' => $vehicle['brand'] ?? null, 'model' => $vehicle['model'] ?? null];
                Database::connection()->prepare(
                    'UPDATE vehicles
                     SET brand = :brand, model = :model, driver_name = :driver_name, driver_phone = :driver_phone, company_id = :company_id, updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'id' => $vehicleId,
                    'brand' => $brand,
                    'model' => $model,
                    'driver_name' => $driverName,
                    'driver_phone' => $driverPhone,
                    'company_id' => $companyId > 0 ? $companyId : null,
                ]);
                if (($old['brand'] ?? '') !== ($brand ?? '') || ($old['model'] ?? '') !== ($model ?? '')) {
                    AuditLogger::log('vehicle.master_updated_from_entry', 'vehicles', $vehicleId, [
                        'old' => $old,
                        'new' => ['brand' => $brand, 'model' => $model],
                        'plate' => $plate,
                    ]);
                }
            }
        }

        $driverId = self::upsertDriver($driverName, $driverPhone, $driverIdentity, $driverAction);
        if ($driverId !== null) {
            self::recordHistory($driverId, $vehicleId, $entryId, $companyId);
        }

        return $vehicleId;
    }

    public static function recordUsageForEntry(int $entryId): void
    {
        self::ensureSchema();
        $statement = Database::connection()->prepare(
            'SELECT dn.id, dn.company_id, v.id AS vehicle_id, v.driver_name, v.driver_phone
             FROM delivery_notifications dn
             INNER JOIN vehicles v ON v.id = dn.vehicle_id
             WHERE dn.id = :id'
        );
        $statement->execute(['id' => $entryId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return;
        }
        $driverId = self::upsertDriver($row['driver_name'] ?? null, self::normalizePhone($row['driver_phone'] ?? null), null, 'old');
        if ($driverId !== null) {
            self::recordHistory($driverId, (int) $row['vehicle_id'], $entryId, (int) $row['company_id']);
        }
    }

    private static function upsertDriver(?string $name, ?string $phone, ?string $identity, string $action): ?int
    {
        if ($name === null && $phone === null && $identity === null) {
            return null;
        }

        $driver = self::driverMatch(['driver_name' => $name, 'driver_phone' => $phone, 'driver_identity_number' => $identity], null);
        if ($driver === null) {
            Database::connection()->prepare(
                'INSERT INTO drivers (full_name, phone, identity_number, is_active)
                 VALUES (:full_name, :phone, :identity_number, 1)'
            )->execute(['full_name' => $name, 'phone' => $phone, 'identity_number' => $identity]);
            return (int) Database::connection()->lastInsertId();
        }

        if ($action === 'update') {
            $old = ['full_name' => $driver['full_name'] ?? null, 'phone' => $driver['phone'] ?? null, 'identity_number' => $driver['identity_number'] ?? null];
            Database::connection()->prepare(
                'UPDATE drivers SET full_name = :full_name, phone = :phone, identity_number = :identity_number, updated_at = NOW() WHERE id = :id'
            )->execute([
                'id' => (int) $driver['id'],
                'full_name' => $name ?? $driver['full_name'],
                'phone' => $phone ?? $driver['phone'],
                'identity_number' => $identity ?? $driver['identity_number'],
            ]);
            $new = ['full_name' => $name ?? $driver['full_name'], 'phone' => $phone ?? $driver['phone'], 'identity_number' => $identity ?? $driver['identity_number']];
            if ($old !== $new) {
                AuditLogger::log('driver.master_updated_from_entry', 'drivers', (int) $driver['id'], ['old' => $old, 'new' => $new]);
            }
        }

        return (int) $driver['id'];
    }

    private static function driverMatch(array $input, ?array $vehicle): ?array
    {
        $identity = self::digits($input['driver_identity_number'] ?? $input['identity_number'] ?? null);
        $phone = self::normalizePhone($input['driver_phone'] ?? $input['phone'] ?? null);
        $name = self::nullish($input['driver_name'] ?? $input['full_name'] ?? null);

        if ($identity !== null) {
            $driver = self::fetchDriver('identity_number = :value', ['value' => $identity]);
            if ($driver !== null) {
                return $driver;
            }
        }
        if ($phone !== null) {
            return self::fetchDriver('phone = :value', ['value' => $phone]);
        }
        if ($name !== null && $vehicle !== null) {
            $statement = Database::connection()->prepare(
                'SELECT d.*
                 FROM drivers d
                 INNER JOIN driver_vehicle_history dvh ON dvh.driver_id = d.id
                 WHERE dvh.vehicle_id = :vehicle_id AND LOWER(d.full_name) = LOWER(:name)
                 ORDER BY dvh.used_at DESC
                 LIMIT 1'
            );
            $statement->execute(['vehicle_id' => (int) $vehicle['id'], 'name' => $name]);
            $driver = $statement->fetch(PDO::FETCH_ASSOC);
            if ($driver !== false) {
                return $driver;
            }
        }

        return null;
    }

    private static function fetchDriver(string $where, array $params): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM drivers WHERE ' . $where . ' LIMIT 1');
        $statement->execute($params);
        $driver = $statement->fetch(PDO::FETCH_ASSOC);
        return $driver === false ? null : $driver;
    }

    private static function vehicleByPlate(string $plate): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM vehicles WHERE normalized_plate = :plate OR REPLACE(plate_number, " ", "") = :plate LIMIT 1'
        );
        $statement->execute(['plate' => $plate]);
        $vehicle = $statement->fetch(PDO::FETCH_ASSOC);
        return $vehicle === false ? null : $vehicle;
    }

    private static function vehiclePayload(array $vehicle): array
    {
        return [
            'id' => (int) $vehicle['id'],
            'plate_number' => $vehicle['plate_number'] ?? '',
            'normalized_plate' => $vehicle['normalized_plate'] ?? self::normalizePlate((string) ($vehicle['plate_number'] ?? '')),
            'brand' => $vehicle['brand'] ?? '',
            'model' => $vehicle['model'] ?? '',
            'driver_name' => $vehicle['driver_name'] ?? '',
            'driver_phone' => $vehicle['driver_phone'] ?? '',
            'history' => self::vehicleHistory((int) $vehicle['id']),
        ];
    }

    private static function driverPayload(array $driver, ?array $vehicle): array
    {
        return [
            'id' => (int) $driver['id'],
            'full_name' => $driver['full_name'] ?? '',
            'phone' => $driver['phone'] ?? '',
            'identity_number' => $driver['identity_number'] ?? '',
            'plates' => self::driverPlates((int) $driver['id']),
            'history' => self::driverHistory((int) $driver['id']),
        ];
    }

    private static function recordHistory(int $driverId, int $vehicleId, ?int $entryId, int $companyId): void
    {
        if ($entryId !== null) {
            $exists = Database::connection()->prepare('SELECT COUNT(*) FROM driver_vehicle_history WHERE entry_id = :entry_id AND driver_id = :driver_id AND vehicle_id = :vehicle_id');
            $exists->execute(['entry_id' => $entryId, 'driver_id' => $driverId, 'vehicle_id' => $vehicleId]);
            if ((int) $exists->fetchColumn() > 0) {
                return;
            }
        } else {
            $exists = Database::connection()->prepare('SELECT COUNT(*) FROM driver_vehicle_history WHERE entry_id IS NULL AND driver_id = :driver_id AND vehicle_id = :vehicle_id');
            $exists->execute(['driver_id' => $driverId, 'vehicle_id' => $vehicleId]);
            if ((int) $exists->fetchColumn() > 0) {
                return;
            }
        }

        Database::connection()->prepare(
            'INSERT INTO driver_vehicle_history (driver_id, vehicle_id, entry_id, company_id, used_at)
             VALUES (:driver_id, :vehicle_id, :entry_id, :company_id, NOW())'
        )->execute([
            'driver_id' => $driverId,
            'vehicle_id' => $vehicleId,
            'entry_id' => $entryId,
            'company_id' => $companyId > 0 ? $companyId : null,
        ]);
    }

    private static function driverPlates(int $driverId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT DISTINCT v.plate_number
             FROM driver_vehicle_history dvh
             INNER JOIN vehicles v ON v.id = dvh.vehicle_id
             WHERE dvh.driver_id = :driver_id
             ORDER BY dvh.used_at DESC
             LIMIT 8'
        );
        $statement->execute(['driver_id' => $driverId]);
        return array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'plate_number');
    }

    private static function driverHistory(int $driverId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT dvh.used_at, v.plate_number, c.name AS company_name, COUNT(*) OVER() AS total_count
             FROM driver_vehicle_history dvh
             INNER JOIN vehicles v ON v.id = dvh.vehicle_id
             LEFT JOIN companies c ON c.id = dvh.company_id
             WHERE dvh.driver_id = :driver_id
             ORDER BY dvh.used_at DESC
             LIMIT 1'
        );
        $statement->execute(['driver_id' => $driverId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private static function vehicleHistory(int $vehicleId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT dvh.used_at, d.full_name AS driver_name, p.name AS product_name, COUNT(*) OVER() AS total_count
             FROM driver_vehicle_history dvh
             LEFT JOIN drivers d ON d.id = dvh.driver_id
             LEFT JOIN delivery_notifications dn ON dn.id = dvh.entry_id
             LEFT JOIN products p ON p.id = dn.product_id
             WHERE dvh.vehicle_id = :vehicle_id
             ORDER BY dvh.used_at DESC
             LIMIT 1'
        );
        $statement->execute(['vehicle_id' => $vehicleId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private static function ensureVehicleColumns(PDO $database, string $driver): void
    {
        $columns = $driver === 'sqlite'
            ? array_column($database->query('PRAGMA table_info(vehicles)')->fetchAll(PDO::FETCH_ASSOC), 'name')
            : array_column($database->query('SHOW COLUMNS FROM vehicles')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $definitions = $driver === 'sqlite'
            ? ['normalized_plate' => 'TEXT NULL', 'model' => 'TEXT NULL']
            : ['normalized_plate' => 'VARCHAR(20) NULL', 'model' => 'VARCHAR(80) NULL'];
        foreach ($definitions as $column => $definition) {
            if (! in_array($column, $columns, true)) {
                $database->exec('ALTER TABLE vehicles ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        if ($driver === 'sqlite') {
            $database->exec('CREATE UNIQUE INDEX IF NOT EXISTS vehicles_normalized_plate_unique ON vehicles(normalized_plate)');
        } else {
            try {
                $database->exec('ALTER TABLE vehicles ADD UNIQUE KEY vehicles_normalized_plate_unique (normalized_plate)');
            } catch (\Throwable) {
            }
        }
    }

    private static function syncVehicleNormalizedPlates(): void
    {
        $rows = Database::connection()->query('SELECT id, plate_number, normalized_plate FROM vehicles')->fetchAll(PDO::FETCH_ASSOC);
        $statement = Database::connection()->prepare('UPDATE vehicles SET normalized_plate = :normalized_plate WHERE id = :id');
        foreach ($rows as $row) {
            $normalized = self::normalizePlate((string) $row['plate_number']);
            if (($row['normalized_plate'] ?? null) !== $normalized) {
                $statement->execute(['id' => (int) $row['id'], 'normalized_plate' => $normalized]);
            }
        }
    }

    private static function seedDriversFromVehicles(): void
    {
        $rows = Database::connection()
            ->query('SELECT id, company_id, driver_name, driver_phone FROM vehicles WHERE driver_name IS NOT NULL OR driver_phone IS NOT NULL')
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $driverId = self::upsertDriver(self::nullish($row['driver_name'] ?? null), self::normalizePhone($row['driver_phone'] ?? null), null, 'old');
            if ($driverId !== null) {
                self::recordHistory($driverId, (int) $row['id'], null, (int) ($row['company_id'] ?? 0));
            }
        }
    }

    private static function nullish(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function digits(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        return $digits === '' ? null : $digits;
    }
}
