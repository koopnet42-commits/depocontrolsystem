<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const ROLE_LABELS = [
        'master' => 'Üretici / Master',
        'admin' => 'Admin',
        'kantar_gorevlisi' => 'Kantar görevlisi',
        'laboratuvar_gorevlisi' => 'Laboratuvar görevlisi',
        'silo_gorevlisi' => 'Silo görevlisi',
        'yonetici' => 'Yönetici',
        'weighbridge' => 'Kantar görevlisi',
        'lab' => 'Laboratuvar görevlisi',
        'silo' => 'Silo görevlisi',
        'manager' => 'Yönetici',
    ];

    private const ROLE_PERMISSIONS = [
        'master' => ['master.recovery', 'weighbridge.entry'],
        'admin' => ['*'],
        'kantar_gorevlisi' => ['weighbridge.entry', 'weighbridge.second'],
        'laboratuvar_gorevlisi' => ['sample.analysis'],
        'silo_gorevlisi' => ['barcode.scan', 'unloading'],
        'yonetici' => ['dashboard', 'reports'],
        'weighbridge' => ['weighbridge.entry', 'weighbridge.second'],
        'lab' => ['sample.analysis'],
        'silo' => ['barcode.scan', 'unloading'],
        'manager' => ['dashboard', 'reports'],
    ];

    private const ROUTE_PERMISSIONS = [
        '/dashboard' => 'dashboard',
        '/companies' => 'admin.master-data',
        '/products' => 'admin.master-data',
        '/product-operations' => 'admin.delivery',
        '/delivery-notifications' => 'admin.delivery',
        '/incoming-products/direct' => 'delivery.direct-entry',
        '/incoming-products' => 'weighbridge.entry',
        '/weighbridge-entry' => 'weighbridge.entry',
        '/second-weighing' => 'weighbridge.second',
        '/sample-analysis' => 'sample.analysis',
        '/silos' => 'admin.silos',
        '/silo-rules' => 'admin.silos',
        '/barcode-tickets' => 'barcode.scan',
        '/unloading-operations' => 'unloading',
        '/outbound-loadings' => 'unloading',
        '/reports' => 'reports',
        '/users' => 'admin.users',
        '/settings' => 'admin.settings',
        '/vehicle-process' => 'dashboard',
        '/outbound-process' => 'dashboard',
        '/driver-vehicle' => 'weighbridge.entry',
        '/process-repair' => 'admin.settings',
        '/security-recovery' => 'master.recovery',
    ];

    public const ASSIGNABLE_ROLES = [
        'admin' => 'Admin',
        'kantar_gorevlisi' => 'Kantar görevlisi',
        'laboratuvar_gorevlisi' => 'Laboratuvar görevlisi',
        'silo_gorevlisi' => 'Silo görevlisi',
        'yonetici' => 'Yönetici',
    ];

    public static function role(): string
    {
        $role = $_SESSION['user']['role'] ?? null;

        return isset(self::ROLE_LABELS[$role]) ? $role : 'invalid';
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return self::check() ? $_SESSION['user'] : null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(\PDO::FETCH_ASSOC);

        if (
            $user === false
            || ! isset(self::ROLE_LABELS[$user['role']])
            || (int) ($user['is_active'] ?? 0) !== 1
            || self::isLocked($user)
            || ! password_verify($password, (string) $user['password'])
        ) {
            if ($user !== false) {
                self::recordFailedLogin((int) $user['id']);
            }
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'must_change_password' => (int) ($user['must_change_password'] ?? 0),
        ];

        Database::connection()
            ->prepare('UPDATE users SET last_login_at = NOW(), failed_login_count = 0, locked_until = NULL WHERE id = :id')
            ->execute(['id' => (int) $user['id']]);

        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['user_role']);
        session_regenerate_id(true);
    }

    public static function roleLabel(?string $role = null): string
    {
        return self::ROLE_LABELS[$role ?? self::role()] ?? 'Yetkisiz';
    }

    public static function can(string $permission): bool
    {
        if ($permission === 'master.recovery') {
            return self::role() === 'master';
        }

        $permissions = self::ROLE_PERMISSIONS[self::role()] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public static function canAccessPath(string $path): bool
    {
        $path = self::normalizePath($path);

        if ($path === '/') {
            return self::can('dashboard');
        }

        foreach (self::ROUTE_PERMISSIONS as $prefix => $permission) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return self::can($permission);
            }
        }

        return self::can('*');
    }

    public static function mustChangePassword(): bool
    {
        return self::check() && (int) ($_SESSION['user']['must_change_password'] ?? 0) === 1;
    }

    public static function isMaster(): bool
    {
        return self::role() === 'master';
    }

    public static function filterMenu(array $items): array
    {
        return array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['route'] ?? '#') === '#'
                ? false
                : self::canAccessPath($item['route'])
        ));
    }

    private static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private static function isLocked(array $user): bool
    {
        $lockedUntil = $user['locked_until'] ?? null;

        return (int) ($user['is_locked'] ?? 0) === 1
            || ($lockedUntil !== null && strtotime((string) $lockedUntil) !== false && strtotime((string) $lockedUntil) > time());
    }

    private static function recordFailedLogin(int $userId): void
    {
        $statement = Database::connection()->prepare('SELECT failed_login_count FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
        $count = (int) $statement->fetchColumn() + 1;
        $lockedUntil = $count >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;

        Database::connection()
            ->prepare('UPDATE users SET failed_login_count = :count, locked_until = :locked_until WHERE id = :id')
            ->execute(['id' => $userId, 'count' => $count, 'locked_until' => $lockedUntil]);
    }
}
