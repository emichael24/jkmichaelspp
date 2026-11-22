<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        // Never load developer or production credentials during automated checks.
        $app->useEnvironmentPath(__DIR__.'/Fixtures')->loadEnvironmentFrom('testing.env');
        $app->make(Kernel::class)->bootstrap();
        $app->instance('env', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $app['config']->set('database.default', 'sqlite');
        foreach (array_keys($app['config']->get('database.connections', [])) as $connection) {
            $app['config']->set("database.connections.$connection", [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }
        if (getenv('CI') === 'true' && getenv('JK_TEST_DATABASE_DRIVER') === 'mysql') {
            $testDatabase = getenv('JK_TEST_DATABASE_NAME') ?: 'jk_enterprise_test';
            if (! preg_match('/^jk_[a-z0-9_]+_test$/', $testDatabase)) {
                throw new \RuntimeException('Use an isolated jk_*_test database for SQL validation.');
            }
            $connection = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => $testDatabase, 'username' => 'jk_test', 'password' => getenv('JK_TEST_DB_PASSWORD') ?: 'isolated-ci-only', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
            foreach (array_keys($app['config']->get('database.connections')) as $name) {
                $app['config']->set("database.connections.$name", $connection);
            }
            $app['config']->set('database.default', 'mysql');
        }
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('queue.default', 'sync');
        Http::preventStrayRequests();

        return $app;
    }
}
