<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;

class EnterpriseReadiness extends Command
{
    protected $signature = 'enterprise:check {--json} {--allow-test-payments : Only for controlled sandbox verification}';

    protected $description = 'Fail closed when production release checks are missing; never prints credentials';

    public function handle(): int
    {
        $checks = [];
        $record = function ($name, $passed, $detail) use (&$checks) {
            $checks[] = ['check' => $name, 'passed' => (bool) $passed, 'detail' => $detail];
        };
        $record('php', PHP_VERSION_ID >= 80200, PHP_VERSION);
        foreach (['pdo_mysql', 'fileinfo', 'mbstring', 'openssl', 'zip', 'dom', 'gd'] as $extension) {
            $record('extension:'.$extension, extension_loaded($extension), 'Required runtime extension');
        }
        $record('production_mode', app()->environment('production') && ! config('app.debug'), 'APP_ENV=production; APP_DEBUG=false');
        $record('application_key', strlen((string) config('app.key')) >= 32, 'A stable application encryption key is required');
        $record('secure_sessions', config('session.secure') && config('session.http_only') && in_array(config('session.same_site'), ['lax', 'strict']), 'Secure, HttpOnly session cookies');
        try {
            $response = Http::timeout(15)->withoutRedirecting()->get(rtrim(config('app.url'), '/').'/up');
            $record('https_health', str_starts_with(config('app.url'), 'https://') && $response->successful(), 'HTTPS certificate validation and /up response');
        } catch (\Throwable) {
            $record('https_health', false, 'HTTPS health request failed; verify certificate, DNS and network');
        }
        try {
            DB::select('SELECT 1');
            $record('database', DB::connection()->getDriverName() === 'mysql', 'Production MySQL connection');
            $migrator = app('migrator');
            $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan());
            $record('migrations', count($pending) === 0, count($pending).' pending migrations');
            $duplicates = Schema::hasTable('certificates') ? DB::table('certificates')->select('user_id', 'certifiable_type', 'certifiable_id')->groupBy('user_id', 'certifiable_type', 'certifiable_id')->havingRaw('COUNT(*) > 1')->get()->count() : -1;
            $record('certificate_integrity', $duplicates === 0, $duplicates.' duplicate credential groups');
        } catch (\Throwable) {
            $record('database', false, 'Database or migration checks failed');
        }
        $root = realpath(config('filesystems.disks.local.root'));
        $public = realpath(public_path());
        $rootPrefix = strtolower(rtrim($root ?: '', '/\\').DIRECTORY_SEPARATOR);
        $publicPrefix = strtolower(rtrim($public ?: '', '/\\').DIRECTORY_SEPARATOR);
        $record('private_storage', $root && $public && ! str_starts_with($rootPrefix, $publicPrefix), 'Private storage must be outside the document root');
        $legacy = 0;
        foreach (['scorm', 'videos', 'courses/videos', 'courses/resources'] as $directory) {
            $legacy += count(Storage::disk('public')->allFiles($directory));
        }
        $record('legacy_public_media', $legacy === 0, $legacy.' legacy public learning files require migration');
        $scannerCommand = (string) config('media.scanner');
        $scanner = is_file($scannerCommand) && is_executable($scannerCommand)
            ? $scannerCommand
            : (new ExecutableFinder)->find($scannerCommand);
        $record('malware_scanning', config('media.require_scan') && $scanner, 'Required scanner must be available; keep signatures current');
        $scanHealth = config('operations.scanner_health_file');
        $record('scanner_definitions', $scanHealth && is_file($scanHealth) && filemtime($scanHealth) > time() - 48 * 3600,
            'Malware definitions must have been successfully checked within 48 hours');
        $sandboxFile = config('operations.document_sandbox_evidence');
        $sandbox = $sandboxFile && is_file($sandboxFile) ? json_decode(file_get_contents($sandboxFile), true) : null;
        $sandboxReady = ($sandbox['result'] ?? null) === 'passed'
            && strtotime($sandbox['verified_at'] ?? '') > time() - 30 * 86400;
        foreach (['pdfinfo', 'pdftoppm', 'libreoffice'] as $program) {
            $path = config('documents.'.$program);
            $sandboxReady = $sandboxReady && is_string($path) && is_file($path) && is_executable($path)
                && $path === ($sandbox['programs'][$program]['path'] ?? null)
                && hash_file('sha256', $path) === ($sandbox['programs'][$program]['sha256'] ?? null);
        }
        $policyPath = $sandbox['policy']['path'] ?? '';
        $sandboxReady = $sandboxReady && is_file($policyPath)
            && hash_file('sha256', $policyPath) === ($sandbox['policy']['sha256'] ?? null);
        $record('document_isolation', $sandboxReady, 'Configure tested PDF/PowerPoint sandbox wrappers; require matching policy hashes and a recent isolation probe');
        $record('durable_queue', ! in_array(config('queue.default'), ['sync', 'null']), 'Use database or Redis workers');
        $record('queue_visibility_timeout', (int) config('queue.connections.'.config('queue.default').'.retry_after') >= 180,
            'Retry visibility must exceed the 120-second worker timeout; use at least 180 seconds');
        $record('durable_cache', ! in_array(config('cache.default'), ['array', 'null']), 'Shared persistent cache for locks and rate limiting');
        foreach (['scheduler', 'queue'] as $component) {
            try {
                $stamp = Cache::get('operations:'.$component.'-heartbeat');
            } catch (\Throwable) {
                $stamp = null;
            }
            $record($component.'_heartbeat', is_numeric($stamp) && abs(time() - $stamp) < 180, 'Heartbeat must be less than three minutes old');
        }
        $secret = app(\App\Services\PaystackSettings::class)->secret();
        $testMode = str_starts_with($secret, 'sk_test_');
        $record('paystack_mode', str_starts_with($secret, 'sk_live_') || ($this->option('allow-test-payments') && $testMode), $testMode ? 'Sandbox verification only; real payments are not active' : 'Live key required for live billing');
        $record('mail_transport', ! in_array(config('mail.default'), ['log', 'array']), 'Production mail delivery must be configured and tested');
        $evidence = config('operations.restore_evidence');
        $restore = $evidence && is_file($evidence) ? json_decode(file_get_contents($evidence), true) : null;
        $record('restore_drill', $restore && ($restore['result'] ?? null) === 'passed' && strtotime($restore['verified_at'] ?? '') > time() - 30 * 86400 && ! empty($restore['backup_sha256']), 'Supply evidence from an actual isolated restore within 30 days');
        $passed = collect($checks)->every('passed');
        if ($this->option('json')) {
            $this->line(json_encode(['ready' => $passed, 'checked_at' => now()->toISOString(), 'checks' => $checks], JSON_PRETTY_PRINT));
        } else {
            $this->table(['Check', 'Result', 'Detail'], array_map(fn ($c) => [$c['check'], $c['passed'] ? 'PASS' : 'FAIL', $c['detail']], $checks));
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
