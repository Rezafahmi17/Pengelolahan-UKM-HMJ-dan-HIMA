<?php

declare(strict_types=1);

function render(string $view, array $data = [], string $layout = 'dashboard'): void
{
    $viewFile = __DIR__ . '/views/pages/' . $view . '.php';
    $layoutFile = __DIR__ . '/views/layouts/' . $layout . '.php';
    if (!is_file($viewFile) || !is_file($layoutFile)) {
        throw new RuntimeException('View tidak ditemukan: ' . $view);
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require $layoutFile;
}
