<?php

declare(strict_types=1);

/**
 * Minimal PSR-4 loader for the framework-independent classes.
 *
 * Exists so the release guard can run on a bare PHP binary — a release machine
 * or CI job must be able to gate a build without a composer install first.
 * Classes that need Contao or Symfony simply never get loaded by the guard.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'VTinnovations\\SeoStudio\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, \strlen($prefix));
    $path = \dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
