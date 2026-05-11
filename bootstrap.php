<?php

declare(strict_types=1);

$config = require __DIR__ . '/config/app.php';

date_default_timezone_set($config['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

spl_autoload_register(static function ($class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/src/Support/helpers.php';

\App\Support\Database::boot($config);

