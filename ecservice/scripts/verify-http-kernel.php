<?php

/**
 * List middleware classes referenced in app/Http/Kernel.php that PHP cannot load.
 * Run on the server after a bad deploy (SSH, project root):
 *
 *   php scripts/verify-http-kernel.php
 */

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$kernelFile = dirname(__DIR__).'/app/Http/Kernel.php';
$code = file_get_contents($kernelFile);

if (! preg_match_all('/((?:\\\\Illuminate|\\\\App)[\\\\A-Za-z0-9_]+)::class/', $code, $matches)) {
    echo "No middleware classes found in {$kernelFile}\n";
    exit(1);
}

$missing = [];
foreach (array_unique($matches[1]) as $class) {
    $class = ltrim($class, '\\');
    if (! class_exists($class)) {
        $missing[] = $class;
    }
}

if ($missing === []) {
    echo "All middleware classes in app/Http/Kernel.php exist.\n";
    exit(0);
}

echo "Missing or wrong middleware classes (replace app/Http/Kernel.php from git/ecservice):\n";
foreach ($missing as $class) {
    echo "  - {$class}\n";
}
exit(1);
