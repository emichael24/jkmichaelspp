<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PerformanceService
{
    /**
     * Get database query optimization suggestions.
     */
    public function analyzeQueryPerformance(): array
    {
        $tables = DB::select('SHOW TABLES');
        $issues = [];

        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0];

            // Check for missing indexes on foreign keys
            $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");
            $indexes = DB::select("SHOW INDEX FROM `{$tableName}`");
            $indexedColumns = collect($indexes)->pluck('Column_name')->toArray();

            foreach ($columns as $column) {
                if (str_ends_with($column->Field, '_id') && !in_array($column->Field, $indexedColumns)) {
                    $issues[] = [
                        'table' => $tableName,
                        'type' => 'missing_index',
                        'column' => $column->Field,
                        'suggestion' => "Add index on {$tableName}.{$column->Field}",
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Cache frequently accessed data.
     */
    public function warmCache(): array
    {
        $cached = [];

        // Cache course categories
        Cache::remember('categories', 3600, function () {
            return \App\Models\Category::withCount('courses')->get();
        });
        $cached[] = 'categories';

        // Cache published course count
        Cache::remember('published_courses_count', 1800, function () {
            return \App\Models\Course::where('is_published', true)->count();
        });
        $cached[] = 'published_courses_count';

        // Cache active user count
        Cache::remember('active_users_count', 1800, function () {
            return \App\Models\User::where('is_active', true)->count();
        });
        $cached[] = 'active_users_count';

        return $cached;
    }

    /**
     * Clear all application caches.
     */
    public function clearAllCaches(): void
    {
        Cache::flush();
        Log::info('All caches cleared');
    }

    /**
     * Get application health metrics.
     */
    public function getHealthMetrics(): array
    {
        $dbStart = microtime(true);
        DB::select('SELECT 1');
        $dbTime = round((microtime(true) - $dbStart) * 1000, 2);

        return [
            'database_latency_ms' => $dbTime,
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'php_version' => phpversion(),
            'laravel_version' => app()->version(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'disk_free_gb' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2),
            'uptime' => $this->getUptime(),
        ];
    }

    private function getUptime(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'N/A on Windows';
        }
        return trim(shell_exec('uptime -p') ?? 'unknown');
    }
}
