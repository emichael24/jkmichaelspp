<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Run only inside the protected validation checkout. Never connects to the live
// application database for writes and never loads production environment secrets.
require __DIR__.'/../vendor/autoload.php';

$root = realpath(__DIR__.'/..');
if ($root !== '/root/jkm-readiness-validation-20261005/application') {
    throw new RuntimeException('Run this rehearsal in the protected validation checkout.');
}
$settings = json_decode(file_get_contents(dirname($root).'/migration-rehearsal.json'), true, 512, JSON_THROW_ON_ERROR);
if ($settings['database'] !== 'jk_migration_rehearsal_20261005' || $settings['username'] !== 'jk_rehearsal') {
    throw new RuntimeException('Unexpected rehearsal database.');
}
$sourceFile = dirname($root).'/migration-source.sqlite';
if (! is_file($sourceFile)) {
    throw new RuntimeException('A consistent source snapshot is required.');
}
$source = new PDO('sqlite:'.$sourceFile);
$source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$source->exec('PRAGMA query_only=ON');
if ($source->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $source->query('PRAGMA foreign_key_check')->fetch()) {
    throw new RuntimeException('Source snapshot failed integrity checks.');
}
$app = require $root.'/bootstrap/app.php';
$app->useEnvironmentPath($root.'/tests/Fixtures')->loadEnvironmentFrom('testing.env');
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) {
    $message = get_class($error) === RuntimeException::class ? $error->getMessage() : 'Rehearsal failed; inspect protected diagnostics.';
    $diagnostic = ['error' => get_class($error), 'code' => (string) $error->getCode(), 'message' => $message];
    if ($error instanceof QueryException) {
        preg_match('/(?:into|table) [`"]([a-zA-Z0-9_]+)[`"]/i', $error->getSql(), $table);
        preg_match('/column [\'`]([a-zA-Z0-9_]+)[\'`]/i', $error->errorInfo[2] ?? '', $column);
        $diagnostic += ['database_error' => $error->errorInfo[1] ?? null, 'table' => $table[1] ?? null, 'column' => $column[1] ?? null];
    }
    fwrite(STDERR, json_encode($diagnostic).PHP_EOL);
    exit(1);
});
$app->instance('env', 'testing');
config(['app.key' => 'base64:'.base64_encode(str_repeat('m', 32)), 'app.url' => 'https://rehearsal.invalid',
    'database.default' => 'mysql', 'database.connections.mysql' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => $settings['database'],
        'username' => $settings['username'], 'password' => $settings['password'], 'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
    ], 'cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync']);
Http::preventStrayRequests();
DB::purge();
$database = DB::connection();
if ($database->getDatabaseName() !== 'jk_migration_rehearsal_20261005' || $database->getSchemaBuilder()->getTables()) {
    throw new RuntimeException('Rehearsal requires an empty dedicated database.');
}
$ran = $source->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
$paths = [];
foreach ($ran as $name) {
    if (! preg_match('/^[a-zA-Z0-9_]+$/', $name) || ! is_file($root.'/database/migrations/'.$name.'.php')) {
        throw new RuntimeException('Source migration is absent from the checkout.');
    }
    $paths[] = 'database/migrations/'.$name.'.php';
}
if (Artisan::call('migrate', ['--path' => $paths, '--force' => true]) !== 0) {
    throw new RuntimeException('Baseline migration failed.');
}
$legacyTypes = $source->query("SELECT id, type FROM courses WHERE type NOT IN ('self_paced','live','certified','bootcamp') OR type IS NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($legacyTypes as $legacy) {
    if (! in_array($legacy['type'], ['free', 'premium', 'enterprise'], true)) {
        throw new RuntimeException('Unknown source course classification requires review.');
    }
}
// Only the isolated target is widened so the raw transfer can be hash-verified.
// The forward migration preserves commercial labels and restores the SQL enum.
if ($legacyTypes) {
    $database->statement("ALTER TABLE courses MODIFY type VARCHAR(32) NOT NULL DEFAULT 'self_paced'");
}
$tables = $source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name <> 'migrations' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$counts = [];
$dateNormalizations = [];
$database->statement('SET FOREIGN_KEY_CHECKS=0');
try {
    $database->transaction(function () use ($tables, $source, $database, &$counts, &$dateNormalizations) {
        foreach ($tables as $table) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $table) || ! $database->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException('Source table has no SQL target.');
            }
            $database->table($table)->delete();
            $rows = $source->query('SELECT * FROM "'.$table.'"');
            $buffer = [];
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $buffer[] = $row;
                if (count($buffer) === 100) {
                    $database->table($table)->insert($buffer);
                    $buffer = [];
                }
            }
            if ($buffer) {
                $database->table($table)->insert($buffer);
            }
            $count = (int) $source->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();
            if ($database->table($table)->count() !== $count) {
                throw new RuntimeException('Transferred row counts differ.');
            }
            // Compare canonical row hashes. Numeric driver representations may differ;
            // normalize scalar values to strings while retaining SQL NULL values.
            $sourceColumns = array_column($source->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC), 'name');
            $numeric = collect($database->getSchemaBuilder()->getColumns($table))->filter(fn ($column) => preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|double|float|real|boolean)$/', $column['type_name']))->pluck('name')->all();
            $dates = collect($database->getSchemaBuilder()->getColumns($table))->where('type_name', 'date')->pluck('name')->all();
            $canonical = function ($row) use ($numeric, $dates, $sourceColumns) {
                $row = (array) $row;
                ksort($row);
                $row = array_intersect_key($row, array_flip($sourceColumns));
                foreach ($row as $column => &$value) {
                    if ($value === null) {
                        continue;
                    }
                    $value = (string) $value;
                    if (in_array($column, $numeric, true) && preg_match('/^-?\d+(\.\d+)?$/', $value) && str_contains($value, '.')) {
                        $value = rtrim(rtrim($value, '0'), '.');
                    }
                    if (in_array($column, $dates, true) && preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|\+00:00)?)?$/', $value, $date)) {
                        $value = $date[1];
                    }
                }
                unset($value);

                return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
            };
            $sourceHashes = [];
            $records = $source->query('SELECT * FROM "'.$table.'"');
            while ($row = $records->fetch(PDO::FETCH_ASSOC)) {
                foreach ($dates as $column) {
                    if (isset($row[$column]) && strlen((string) $row[$column]) > 10) {
                        $key = $table.'.'.$column;
                        $dateNormalizations[$key] = ($dateNormalizations[$key] ?? 0) + 1;
                    }
                }
                $sourceHashes[] = $canonical($row);
            }
            $targetHashes = $database->table($table)->get()->map($canonical)->all();
            sort($sourceHashes);
            sort($targetHashes);
            if ($sourceHashes !== $targetHashes) {
                $example = $source->query('SELECT * FROM "'.$table.'" LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                $targetExample = isset($example['id']) ? (array) $database->table($table)->where('id', $example['id'])->first() : [];
                $different = [];
                foreach ($example ?: [] as $column => $value) {
                    if ($canonical([$column => $value]) !== $canonical([$column => $targetExample[$column] ?? null])) {
                        $different[] = $column;
                    }
                }
                throw new RuntimeException('Transferred row values differ in '.$table.'; example columns: '.implode(', ', $different));
            }
            $counts[$table] = $count;
        }
    });
} finally {
    $database->statement('SET FOREIGN_KEY_CHECKS=1');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Pending migrations failed.');
}
foreach ($legacyTypes as $legacy) {
    $course = $database->table('courses')->find($legacy['id']);
    if ($course->type !== 'self_paced' || $course->legacy_commercial_type !== $legacy['type']) {
        throw new RuntimeException('Legacy classification preservation failed.');
    }
}
// Enabling FOREIGN_KEY_CHECKS does not validate already inserted rows; check each constraint explicitly.
$foreignKeys = $database->select('SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$settings['database']]);
foreach ($foreignKeys as $key) {
    foreach ((array) $key as $identifier) {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Unexpected schema identifier.');
        }
    }
    $sql = "SELECT COUNT(*) AS missing FROM `{$key->TABLE_NAME}` c LEFT JOIN `{$key->REFERENCED_TABLE_NAME}` p ON c.`{$key->COLUMN_NAME}`=p.`{$key->REFERENCED_COLUMN_NAME}` WHERE c.`{$key->COLUMN_NAME}` IS NOT NULL AND p.`{$key->REFERENCED_COLUMN_NAME}` IS NULL";
    if ((int) $database->selectOne($sql)->missing !== 0) {
        throw new RuntimeException('Foreign-key integrity failed after migration.');
    }
}
$result = ['result' => 'passed', 'verified_at' => gmdate('c'), 'source_tables_transferred' => count($counts), 'source_rows_transferred' => array_sum($counts),
    'row_hashes_verified_before_pending_migrations' => true, 'foreign_keys_verified' => count($foreignKeys),
    'migrations' => $database->table('migrations')->count(), 'live_database_modified' => false,
    'preserved_legacy_course_classifications' => count($legacyTypes),
    'date_only_columns_normalized_to_schema' => $dateNormalizations,
    'limits' => ['Protected same-server rehearsal', 'No production HTTP traffic redirected', 'No external email, payment or AI requests']];
file_put_contents(dirname($root).'/migration-rehearsal-result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
