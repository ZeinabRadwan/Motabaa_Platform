<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\File;

$root = 'tmp_n1_measure';
$paths = [];
for ($i = 1; $i <= 10; $i++) {
    $path = $root.'/'.$i;
    $paths[] = $path;
    File::ensureDirectoryExists(storage_path('app/'.$path));
    file_put_contents(storage_path('app/'.$path).'/case_image.jpg', 'img');
}

fileLookupResetStats();
$cold = [];
foreach ($paths as $path) {
    $cold[] = fetchFiles($path, 'case_image');
}
$coldStats = fileLookupStats();

fileLookupResetStats();
$warm = [];
foreach ($paths as $path) {
    $warm[] = fetchFiles($path, 'case_image');
}
$warmStats = fileLookupStats();

fileLookupResetStats();
$stored = [];
foreach ($paths as $path) {
    $stored[] = resolveStoredNamedFile($path, 'case_image', 'case_image.jpg');
}
$storedStats = fileLookupStats();

fileLookupResetStats();
$missing = [];
foreach ($paths as $path) {
    $missing[] = resolveStoredNamedFile($path, 'case_image', '');
}
$missingStats = fileLookupStats();

File::deleteDirectory(storage_path('app/'.$root));
foreach ($paths as $path) {
    bumpFileCacheGeneration($path);
}

$first = $cold[0] ?? null;
$storedFirst = $stored[0] ?? null;
echo json_encode([
    'rows' => 10,
    'shape_ok' => isset($first['file_url']) && ($first['file_name'] ?? null) === 'case_image',
    'stored_shape_ok' => isset($storedFirst['file_url']) && ($storedFirst['file_name'] ?? null) === 'case_image',
    'cold_unique_folders' => $coldStats,
    'repeat_same_request' => $warmStats,
    'stored_path_lookups' => $storedStats,
    'confirmed_missing_lookups' => $missingStats,
    'missing_all_null' => count(array_filter($missing)) === 0,
], JSON_PRETTY_PRINT), PHP_EOL;
