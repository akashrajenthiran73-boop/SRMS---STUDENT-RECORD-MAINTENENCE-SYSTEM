<?php
/**
 * PHPUnit Bootstrap file for SRMS Test Suite.
 */

// 1. Composer Autoloader if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// 2. Fallback PSR-4 Autoloader for SRMS and SRMS\Tests
spl_autoload_register(function ($class) {
    $prefixes = [
        'SRMS\\Tests\\' => __DIR__ . '/',
        'SRMS\\'        => __DIR__ . '/../src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 3. Preload Core Database Exceptions
if (file_exists(__DIR__ . '/../src/Database/Exceptions.php')) {
    require_once __DIR__ . '/../src/Database/Exceptions.php';
}

// 4. Mock Session Initialization if in CLI mode
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
