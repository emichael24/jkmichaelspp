<?php

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

require __DIR__.'/validation-bootstrap.php';
$mode = $argv[1] ?? '';
if ($mode === 'initialize') {
    $user = User::firstOrCreate(['email' => 'runtime-probe@readiness.invalid'], [
        'first_name' => 'Synthetic runtime', 'last_name' => 'Verification', 'password' => Hash::make(bin2hex(random_bytes(32))),
        'is_active' => true,
    ]);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole(Role::findOrCreate('learner', 'web'));
    $settings['probe_user_id'] = $user->id;
    file_put_contents(__DIR__.'/../runtime-settings.json', json_encode($settings, JSON_THROW_ON_ERROR));
    echo json_encode(['initialized' => true], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}
if ($mode === 'schedule') {
    exit(Artisan::call('schedule:test', ['--name' => 'operational-heartbeat', '--no-interaction' => true]));
}
if ($mode === 'queue') {
    exit(Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'readiness-probe', '--once' => true, '--timeout' => 30, '--tries' => 1]));
}
if ($mode === 'verify') {
    $checks = [];
    foreach (['scheduler', 'queue'] as $component) {
        $stamp = Cache::get('operations:'.$component.'-heartbeat');
        $checks[$component] = is_numeric($stamp) && abs(time() - $stamp) < 180;
    }
    $checks['probe_queue_empty'] = ! DB::table('jobs')->where('queue', 'readiness-probe')->exists();
    echo json_encode($checks, JSON_THROW_ON_ERROR).PHP_EOL;
    exit(in_array(false, $checks, true) ? 1 : 0);
}
if ($mode === 'reconcile') {
    $status = Artisan::call('learning:reconcile');
    echo Artisan::output();
    exit($status);
}
if ($mode === 'media') {
    config(['media.require_scan' => true, 'media.scanner' => '/usr/local/bin/jkm-clamscan']);
    $status = Artisan::call('media:migrate-legacy-resources', ['--apply' => true]);
    $remaining = count(Storage::disk('public')->allFiles('courses/resources'));
    $assets = MediaAsset::where('kind', 'document')->get();
    $checks = ['migration_exit' => $status, 'remaining_public_files' => $remaining, 'document_assets' => $assets->count(),
        'scanned_and_hash_verified' => $assets->every(fn ($asset) => $asset->scanned_at && hash_equals($asset->sha256, hash_file('sha256', Storage::disk('local')->path($asset->path))))];
    echo json_encode($checks, JSON_THROW_ON_ERROR).PHP_EOL;
    exit($status || $remaining || ! $checks['scanned_and_hash_verified'] ? 1 : 0);
}
throw new RuntimeException('Choose schedule, queue or verify.');
