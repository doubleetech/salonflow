<?php

/**
 * bootstrap.php
 * Loaded once at the top of public/index.php. Wires up config,
 * a tiny PSR-4-ish autoloader (no Composer needed for core/models/controllers),
 * and starts the session.
 */

require_once __DIR__ . '/../config/config.php';

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../core/',
        __DIR__ . '/../models/',
        __DIR__ . '/../controllers/',
    ];

    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/Session.php';
Session::start();
