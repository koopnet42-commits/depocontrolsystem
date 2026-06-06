<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;
use PDO;

final class SecurityRecoveryController extends Controller
{
    public function index(): void
    {
        $tempPassword = $_SESSION['generated_temp_password'] ?? null;
        unset($_SESSION['generated_temp_password']);

        $this->view('security_recovery/index', [
            'title' => 'Güvenlik / Kullanıcı Kurtarma',
            'users' => $this->users(),
            'query' => trim((string) $this->input('q', '')),
            'roles' => Auth::ASSIGNABLE_ROLES,
            'message' => (string) $this->input('message', ''),
            'tempPassword' => $tempPassword,
        ]);
    }

    public function resetPassword(): void
    {
        $target = $this->targetUser();
        $note = $this->serviceNote();

        if ($target === null || $target['role'] === 'master' || $note === null) {
            $this->redirect('/security-recovery?message=invalid');
        }

        $password = $this->temporaryPassword();
        Database::connection()
            ->prepare('UPDATE users SET password = :password, must_change_password = 1, failed_login_count = 0, locked_until = NULL, is_locked = 0 WHERE id = :id')
            ->execute([
                'id' => (int) $target['id'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);

        $_SESSION['generated_temp_password'] = [
            'user' => $target['email'],
            'password' => $password,
        ];
        AuditLogger::log('master.password_reset', 'users', (int) $target['id'], [
            'target_user_id' => (int) $target['id'],
            'target_user_email' => $target['email'],
            'service_note' => $note,
        ]);

        $this->redirect('/security-recovery?message=password_reset');
    }

    public function toggleStatus(): void
    {
        $target = $this->targetUser();
        $note = $this->serviceNote();

        if ($target === null || $target['role'] === 'master' || $note === null) {
            $this->redirect('/security-recovery?message=invalid');
        }

        if ((int) $target['is_active'] === 1 && $target['role'] === 'admin' && $this->activeAdminCount((int) $target['id']) < 1) {
            $this->redirect('/security-recovery?message=admin_required');
        }

        $newValue = (int) ! (bool) $target['is_active'];
        Database::connection()
            ->prepare('UPDATE users SET is_active = :is_active WHERE id = :id')
            ->execute(['id' => (int) $target['id'], 'is_active' => $newValue]);

        AuditLogger::log('master.user_status_changed', 'users', (int) $target['id'], [
            'target_user_id' => (int) $target['id'],
            'service_note' => $note,
            'old' => ['is_active' => (int) $target['is_active']],
            'new' => ['is_active' => $newValue],
        ]);

        $this->redirect('/security-recovery?message=saved');
    }

    public function unlock(): void
    {
        $target = $this->targetUser();
        $note = $this->serviceNote();

        if ($target === null || $target['role'] === 'master' || $note === null) {
            $this->redirect('/security-recovery?message=invalid');
        }

        Database::connection()
            ->prepare('UPDATE users SET is_locked = 0, failed_login_count = 0, locked_until = NULL WHERE id = :id')
            ->execute(['id' => (int) $target['id']]);

        AuditLogger::log('master.user_unlocked', 'users', (int) $target['id'], [
            'target_user_id' => (int) $target['id'],
            'service_note' => $note,
        ]);

        $this->redirect('/security-recovery?message=saved');
    }

    public function assignRole(): void
    {
        $target = $this->targetUser();
        $note = $this->serviceNote();
        $role = (string) $this->input('role');

        if ($target === null || $target['role'] === 'master' || $note === null || ! isset(Auth::ASSIGNABLE_ROLES[$role])) {
            $this->redirect('/security-recovery?message=invalid');
        }

        if ($target['role'] === 'admin' && $role !== 'admin' && $this->activeAdminCount((int) $target['id']) < 1) {
            $this->redirect('/security-recovery?message=admin_required');
        }

        Database::connection()
            ->prepare('UPDATE users SET role = :role WHERE id = :id')
            ->execute(['id' => (int) $target['id'], 'role' => $role]);

        AuditLogger::log('master.role_assigned', 'users', (int) $target['id'], [
            'target_user_id' => (int) $target['id'],
            'service_note' => $note,
            'old' => ['role' => $target['role']],
            'new' => ['role' => $role],
        ]);

        $this->redirect('/security-recovery?message=saved');
    }

    private function users(): array
    {
        $query = trim((string) $this->input('q', ''));
        $sql = 'SELECT id, name, email, role, is_active, is_locked, must_change_password, failed_login_count, locked_until, last_login_at
                FROM users';
        $params = [];

        if ($query !== '') {
            $sql .= ' WHERE name LIKE :q OR email LIKE :q';
            $params['q'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY role = "master" DESC, is_active DESC, name ASC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function targetUser(): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => (int) $this->input('user_id')]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    private function serviceNote(): ?string
    {
        $note = trim((string) $this->input('service_note', ''));

        return $note === '' ? null : $note;
    }

    private function activeAdminCount(?int $exceptId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE role = "admin" AND is_active = 1';
        $params = [];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function temporaryPassword(): string
    {
        return 'Tmp-' . bin2hex(random_bytes(4)) . '!' . random_int(10, 99);
    }
}
