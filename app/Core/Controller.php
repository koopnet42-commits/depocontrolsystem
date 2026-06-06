<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function redirectWithValidation(string $path, array $errors, ?array $old = null, ?string $general = null): void
    {
        $_SESSION['_validation'] = [
            'errors' => $errors,
            'old' => $old ?? $_POST,
            'general' => $general ?? 'Formdaki hatalı alanları kontrol edin.',
        ];

        if (! str_contains($path, 'message=')) {
            $separator = str_contains($path, '?') ? '&' : '?';
            $path .= $separator . 'message=invalid';
        }

        $this->redirect($path);
    }

    protected function consumeValidation(): array
    {
        $validation = $_SESSION['_validation'] ?? [
            'errors' => [],
            'old' => [],
            'general' => null,
        ];

        unset($_SESSION['_validation']);

        return [
            'errors' => is_array($validation['errors'] ?? null) ? $validation['errors'] : [],
            'old' => is_array($validation['old'] ?? null) ? $validation['old'] : [],
            'general' => $validation['general'] ?? null,
        ];
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function boolInput(string $key): int
    {
        return isset($_POST[$key]) ? 1 : 0;
    }

    protected function nullableInput(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }

    protected function decimalInputOrNull(string $key): ?string
    {
        $value = str_replace(',', '.', trim((string) $this->input($key, '')));

        return $value === '' || ! is_numeric($value) ? null : $value;
    }

    protected function decimalInputOrZero(string $key): string
    {
        return $this->decimalInputOrNull($key) ?? '0';
    }

    protected function intInputOrNull(string $key): ?int
    {
        $value = (int) $this->input($key);

        return $value > 0 ? $value : null;
    }

    protected function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewPath = BASE_PATH . '/app/Views/' . $view . '.php';

        if (! is_file($viewPath)) {
            http_response_code(500);
            echo 'View bulunamadı.';
            return;
        }

        require BASE_PATH . '/app/Views/layouts/main.php';
    }
}
