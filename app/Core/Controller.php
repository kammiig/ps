<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'site'): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require APP_PATH . '/Views/' . $view . '.php';
        $content = ob_get_clean();

        ob_start();
        require APP_PATH . '/Views/layouts/' . $layout . '.php';
        return ob_get_clean();
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function back(string $fallback = '/'): void
    {
        $target = $_SERVER['HTTP_REFERER'] ?? $fallback;
        $this->redirect($target);
    }
}
