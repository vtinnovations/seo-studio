<?php

declare(strict_types=1);

/**
 * Test bootstrap.
 *
 * Works both standalone (the bundle has its own vendor directory) and when the
 * bundle is installed into a Contao application as a path repository, where the
 * application's autoloader is the one that knows Contao and Symfony.
 */
$autoloaders = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

$loaded = false;

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        $loaded = true;

        break;
    }
}

if (!$loaded) {
    fwrite(STDERR, "No autoloader found. Install dependencies first.\n");

    exit(1);
}

// The application autoloader knows the bundle's src/ but not its tests/.
spl_autoload_register(static function (string $class): void {
    $prefix = 'VTinnovations\\SeoStudio\\Tests\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
