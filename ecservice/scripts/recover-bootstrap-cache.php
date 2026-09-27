<?php

/**
 * Emergency bootstrap cache wipe — does NOT boot Laravel.
 * Use when artisan fails with "Target class [files] does not exist" or similar
 * after uploading bootstrap/cache or config cache from another machine.
 *
 * On the server (api.motabaah.com document root = ecservice):
 *   php scripts/recover-bootstrap-cache.php
 *   composer install --no-dev --optimize-autoloader
 *   php artisan package:discover --ansi
 *   php artisan migrate --force
 */

$root = dirname(__DIR__);
$cacheDir = $root.'/bootstrap/cache';

if (!is_dir($cacheDir)) {
    fwrite(STDERR, "Missing directory: {$cacheDir}\n");
    exit(1);
}

$removed = [];
foreach (glob($cacheDir.'/*.php') ?: [] as $file) {
    if (!is_file($file)) {
        continue;
    }
    if (unlink($file)) {
        $removed[] = basename($file);
    }
}

echo "Removed bootstrap cache files:\n";
if ($removed === []) {
    echo "  (none)\n";
} else {
    foreach ($removed as $name) {
        echo "  - {$name}\n";
    }
}

echo "\nNext on this server:\n";
echo "  composer install --no-dev --optimize-autoloader\n";
echo "  php artisan package:discover --ansi\n";
echo "  php artisan config:clear\n";
echo "  php artisan migrate --force\n";
