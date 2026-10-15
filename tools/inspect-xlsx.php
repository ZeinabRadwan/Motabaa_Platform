<?php
require __DIR__ . '/../ecservice/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = $argv[1] ?? '';
$wb = IOFactory::load($path);
$ws = $wb->getActiveSheet();
$highest = $ws->getHighestRow();
for ($r = 1; $r <= min($highest, 25); $r++) {
    $row = [];
    foreach (range('A', 'G') as $col) {
        $row[] = $ws->getCell($col . $r)->getValue();
    }
    echo implode("\t", array_map(fn ($v) => str_replace(["\r", "\n"], ' ', (string) $v), $row)) . PHP_EOL;
}
