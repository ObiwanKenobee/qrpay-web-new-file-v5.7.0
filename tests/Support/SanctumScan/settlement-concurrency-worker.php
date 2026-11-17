<?php

use App\Models\SanctumScanExchange;
use App\Services\SanctumScan\ExchangeSettlementManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';

try {
    if ($argc !== 7) {
        throw new RuntimeException('Expected exchange ID, target status, actor ID, expected status, barrier directory and worker name.');
    }

    [, $exchangeId, $toStatus, $actorId, $expectedFrom, $barrierDirectory, $workerName] = $argv;

    if (! in_array($workerName, ['verified', 'rejected'], true)) {
        throw new RuntimeException('Unsupported concurrency worker name.');
    }

    if (! is_dir($barrierDirectory)) {
        throw new RuntimeException('The concurrency barrier directory does not exist.');
    }

    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $connection = DB::connection();

    if ($connection->getDriverName() !== 'mysql') {
        throw new RuntimeException('Settlement concurrency workers must use MySQL.');
    }

    $connection->getPdo();
    $connection->statement('SET SESSION innodb_lock_wait_timeout = 15');
    $connectionId = (int) $connection->selectOne('SELECT CONNECTION_ID() AS connection_id')->connection_id;

    file_put_contents($barrierDirectory.'/'.$workerName.'.ready', (string) $connectionId, LOCK_EX);

    $deadline = microtime(true) + 20;

    while (! is_file($barrierDirectory.'/start')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Timed out waiting for the parent concurrency barrier.');
        }

        usleep(10000);
    }

    $connection->beforeExecuting(function ($query) use ($barrierDirectory, $workerName): void {
        $normalized = strtolower((string) $query);

        if (
            str_contains($normalized, 'sanctum_scan_exchanges')
            && preg_match('/\bfor\s+update\b/', $normalized) === 1
        ) {
            file_put_contents(
                $barrierDirectory.'/'.$workerName.'.lock-query-started',
                (string) getmypid(),
                LOCK_EX
            );
        }
    });

    // Supplying only the primary key avoids an earlier unlocked read. The
    // manager itself performs the SELECT ... FOR UPDATE inside its transaction.
    $exchange = new SanctumScanExchange();
    $exchange->setAttribute($exchange->getKeyName(), (int) $exchangeId);

    $result = app(ExchangeSettlementManager::class)->transition(
        $exchange,
        $toStatus,
        'admin',
        (int) $actorId,
        'Concurrent administrative settlement decision.',
        $expectedFrom
    );

    fwrite(STDOUT, json_encode([
        'ok' => $result->ok,
        'error' => $result->error,
        'from' => $result->from,
        'to' => $result->to,
        'actor_id' => (int) $actorId,
    ], JSON_THROW_ON_ERROR).PHP_EOL);
} catch (\Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
