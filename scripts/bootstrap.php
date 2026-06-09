<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the PHP CI/CD scripts. Loads Composer's autoloader so
 * the pipeline classes under scripts/src/ (and the package under src/) are
 * available to every entrypoint.
 */

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(\STDERR, "Composer dependencies are not installed. Run: composer install\n");
    exit(1);
}

require_once $autoload;
