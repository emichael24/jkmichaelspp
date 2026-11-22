<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_readiness_accepts_an_executable_absolute_path_and_rejects_a_missing_path(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        config(['media.require_scan' => true]);

        foreach ([PHP_BINARY => true, '/nonexistent/jkm-scanner' => false] as $path => $expected) {
            config(['media.scanner' => $path]);
            Artisan::call('enterprise:check', ['--json' => true, '--allow-test-payments' => true]);
            $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            $scanner = collect($report['checks'])->firstWhere('check', 'malware_scanning');
            $this->assertSame($expected, $scanner['passed']);
        }
    }
}
