<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditLogger;

final class AuthController extends Controller
{
    public function loginForm(): void
    {
        $this->view('auth/login', [
            'title' => 'Giriş',
            'authLayout' => true,
            'message' => (string) $this->input('message', ''),
        ]);
    }

    public function login(): void
    {
        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');

        if (! Auth::attempt($email, $password)) {
            AuditLogger::log('login_failed', 'users', null, ['email' => $email]);
            $this->redirect('/login?message=failed');
        }

        AuditLogger::log('login_success', 'users', (int) Auth::user()['id']);
        if (Auth::mustChangePassword()) {
            $this->redirect('/password/change');
        }

        $this->redirect(Auth::isMaster() ? '/security-recovery' : '/dashboard');
    }

    public function changePasswordForm(): void
    {
        $this->view('auth/change_password', [
            'title' => 'Şifre Değiştir',
            'authLayout' => true,
            'message' => (string) $this->input('message', ''),
        ]);
    }

    public function changePassword(): void
    {
        $password = (string) $this->input('password', '');
        $confirmation = (string) $this->input('password_confirmation', '');

        if (strlen($password) < 10 || $password !== $confirmation) {
            $this->redirect('/password/change?message=invalid');
        }

        $user = Auth::user();
        Database::connection()
            ->prepare('UPDATE users SET password = :password, must_change_password = 0, failed_login_count = 0, locked_until = NULL, is_locked = 0 WHERE id = :id')
            ->execute([
                'id' => (int) $user['id'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);

        $_SESSION['user']['must_change_password'] = 0;
        AuditLogger::log('password_changed', 'users', (int) $user['id']);
        $this->redirect(Auth::isMaster() ? '/security-recovery' : '/dashboard');
    }

    public function logout(): void
    {
        AuditLogger::log('logout', 'users', Auth::user() === null ? null : (int) Auth::user()['id']);
        Auth::logout();
        $this->redirect('/login');
    }
}
