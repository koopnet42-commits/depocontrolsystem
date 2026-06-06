<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\AuditLogger;
use Throwable;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            $this->renderError('Sayfa bulunamadı.', 404);
            return;
        }

        if (! $this->isPublicPath($path) && ! Auth::check()) {
            header('Location: /login?message=required');
            return;
        }

        if (Auth::check() && Auth::mustChangePassword() && ! in_array($path, ['/password/change', '/logout'], true)) {
            header('Location: /password/change');
            return;
        }

        if (! $this->isPublicPath($path) && $path !== '/logout' && ! Auth::canAccessPath($path)) {
            http_response_code(403);
            $this->renderError('Bu işlem için yetkiniz yok.', 403);
            return;
        }

        [$controllerClass, $action] = $handler;
        $controller = new $controllerClass();

        try {
            if ($method === 'POST') {
                AuditLogger::log('post:' . $path, null, null, $_POST);
            }

            $controller->{$action}();
        } catch (Throwable $exception) {
            http_response_code(500);
            AuditLogger::log('system_error', null, null, [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
            $this->renderError('İşlem sırasında beklenmeyen bir hata oluştu. Lütfen veriyi kontrol edip tekrar deneyin.', 500);
        }
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function isPublicPath(string $path): bool
    {
        return in_array($path, ['/login'], true);
    }

    private function renderError(string $message, int $code): void
    {
        $title = $code === 403 ? 'Yetkisiz İşlem' : 'Sistem Mesajı';
        require BASE_PATH . '/app/Views/errors/error.php';
    }
}
