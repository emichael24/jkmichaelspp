<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Isolated production-copy rehearsal only. This must never boot the live app.
$root = realpath(__DIR__.'/..');
if ($root !== '/var/lib/jkmichaels/readiness-20261005') {
    throw new RuntimeException('A protected, separate runtime checkout is required.');
}
require $root.'/vendor/autoload.php';
$settings = json_decode(file_get_contents($root.'/runtime-settings.json'), true, 512, JSON_THROW_ON_ERROR);
if ($settings['database'] !== 'jk_migration_rehearsal_20261005' || $settings['username'] !== 'jk_rehearsal') {
    throw new RuntimeException('Refusing an unexpected validation database.');
}
$app = require $root.'/bootstrap/app.php';
$app->useEnvironmentPath($root.'/tests/Fixtures')->loadEnvironmentFrom('testing.env');
$app->make(Kernel::class)->bootstrap();
$app->instance('env', 'testing');
$database = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => $settings['database'],
    'username' => $settings['username'], 'password' => $settings['password'], 'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
foreach (array_keys(config('database.connections')) as $name) {
    config(['database.connections.'.$name => $database]);
}
config(['app.key' => 'base64:'.base64_encode(str_repeat('v', 32)), 'app.url' => 'http://127.0.0.1:8917', 'app.debug' => false,
    'database.default' => 'mysql', 'cache.default' => 'database', 'cache.prefix' => 'jk_readiness_probe_',
    'session.driver' => 'file', 'session.secure' => false, 'mail.default' => 'array', 'queue.default' => 'database',
    'queue.connections.database.queue' => 'readiness-probe', 'queue.connections.database.retry_after' => 180,
    'services.paystack.secret_key' => null]);
DB::purge();
Http::preventStrayRequests();

return $app;
