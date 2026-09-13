<?php

/**
 * One-off profiler for list-endpoint query counts.
 * Usage: php scripts/measure_list_n1.php
 */

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function normalizeSql(string $sql): string
{
    $sql = preg_replace('/\s+/', ' ', $sql);
    $sql = preg_replace('/\b\d+\b/', '?', $sql);
    $sql = preg_replace("/'[^']*'/", '?', $sql);
    $sql = preg_replace('/"[^"]*"/', '?', $sql);

    return trim($sql);
}

function measure(string $label, callable $fn): array
{
    if (function_exists('fileLookupResetStats')) {
        fileLookupResetStats();
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $started = microtime(true);
    $result = $fn();
    $elapsedMs = round((microtime(true) - $started) * 1000, 1);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $patterns = [];
    foreach ($queries as $query) {
        $key = normalizeSql($query['query']);
        $patterns[$key] = ($patterns[$key] ?? 0) + 1;
    }
    arsort($patterns);

    $rowCount = null;
    if (is_object($result) && method_exists($result, 'getData')) {
        $data = $result->getData(true);
        $rowCount = is_array($data['data'] ?? null) ? count($data['data']) : null;
    }

    $fileStats = function_exists('fileLookupStats') ? fileLookupStats() : null;

    $report = [
        'label' => $label,
        'rows' => $rowCount,
        'queries' => count($queries),
        'ms' => $elapsedMs,
        'repeat_patterns' => array_slice(array_filter($patterns, fn ($n) => $n > 1), 0, 8, true),
        'file_lookups' => $fileStats,
    ];

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

    return $report;
}

$user = User::query()->orderBy('id')->first();
if (!$user) {
    fwrite(STDERR, "No users in the database; cannot measure authenticated list endpoints.\n");
    exit(1);
}

Auth::login($user);
$centerId = $user->centers()->value('centers.id');

$options = ['itemsPerPage' => 10, 'page' => 1];

$makeRequest = function (array $params = []) use ($user) {
    $request = Request::create('/measure', 'GET', $params);
    $request->setUserResolver(fn () => $user);
    app()->instance('request', $request);

    return $request;
};

echo "Measuring as user #{$user->id} center=".($centerId ?: 'none').PHP_EOL;

measure('GET /cases', function () use ($makeRequest, $centerId) {
    return app(\App\Http\Controllers\General\SCaseController::class)->index(
        $makeRequest(['center_id' => $centerId, 'options' => ['itemsPerPage' => 10], 'page' => 1])
    );
});

measure('GET /users', function () use ($makeRequest, $centerId) {
    return app(\App\Http\Controllers\Admin\UserController::class)->index(
        $makeRequest(['center_id' => $centerId, 'options' => ['itemsPerPage' => 10], 'excludeParent' => 1])
    );
});

measure('GET /users?onlyParents=1', function () use ($makeRequest, $centerId) {
    return app(\App\Http\Controllers\Admin\UserController::class)->index(
        $makeRequest(['center_id' => $centerId, 'options' => ['itemsPerPage' => 10], 'onlyParents' => 1])
    );
});

measure('GET /payments', function () use ($makeRequest, $centerId) {
    return app(\App\Http\Controllers\General\SCaseController::class)->payments(
        $makeRequest(['center_id' => $centerId, 'options' => ['itemsPerPage' => 10]])
    );
});

measure('GET /logs', function () use ($makeRequest, $centerId) {
    return app(\App\Http\Controllers\General\LogController::class)->index(
        $makeRequest(['center_id' => $centerId, 'options' => ['itemsPerPage' => 10]])
    );
});

measure('GET /messages', function () use ($makeRequest) {
    return app(\App\Http\Controllers\General\MessageController::class)->index(
        $makeRequest(['options' => ['itemsPerPage' => 10]])
    );
});
