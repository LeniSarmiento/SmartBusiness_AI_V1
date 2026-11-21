<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DiagnosticsCommand extends Command
{
    protected $signature = 'app:diagnostics
        {--json : Devuelve el diagnóstico como JSON}
        {--fail-on-failed-jobs : Falla cuando existe al menos un Job fallido}';

    protected $description = 'Diagnostica Laravel, PostgreSQL, cache y cola sin mostrar secretos';

    public function handle(): int
    {
        $report = [
            'generated_at' => now()->toIso8601String(),
            'application' => [
                'status' => 'healthy',
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
        ];
        $healthy = true;

        try {
            $startedAt = hrtime(true);
            DB::select('select 1');
            $report['database'] = [
                'status' => 'healthy',
                'connection' => DB::getDefaultConnection(),
                'latency_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
                'migrated' => Schema::hasTable('migrations'),
                'migration_count' => Schema::hasTable('migrations')
                    ? DB::table('migrations')->count()
                    : 0,
            ];

            if (! $report['database']['migrated']) {
                $healthy = false;
                $report['database']['status'] = 'unhealthy';
            }
        } catch (Throwable $exception) {
            report($exception);
            $healthy = false;
            $report['database'] = [
                'status' => 'unhealthy',
                'exception_class' => $exception::class,
            ];
        }

        try {
            $cacheKey = 'diagnostics:'.Str::uuid();
            Cache::put($cacheKey, true, now()->addMinute());
            $cacheAvailable = Cache::get($cacheKey) === true;
            Cache::forget($cacheKey);
            $report['cache'] = [
                'status' => $cacheAvailable ? 'healthy' : 'unhealthy',
                'store' => (string) config('cache.default'),
            ];
            $healthy = $healthy && $cacheAvailable;
        } catch (Throwable $exception) {
            report($exception);
            $healthy = false;
            $report['cache'] = [
                'status' => 'unhealthy',
                'exception_class' => $exception::class,
            ];
        }

        try {
            $queueConnection = (string) config('queue.default');
            $report['queue'] = [
                'status' => 'healthy',
                'connection' => $queueConnection,
            ];

            if ($queueConnection === 'database') {
                if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
                    $healthy = false;
                    $report['queue']['status'] = 'unhealthy';
                    $report['queue']['schema_available'] = false;
                } else {
                    $report['queue']['schema_available'] = true;
                    $report['queue']['pending_jobs'] = DB::table('jobs')->count();
                    $report['queue']['failed_jobs'] = DB::table('failed_jobs')->count();

                    if ($this->option('fail-on-failed-jobs') && $report['queue']['failed_jobs'] > 0) {
                        $healthy = false;
                        $report['queue']['status'] = 'unhealthy';
                    }
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $healthy = false;
            $report['queue'] = [
                'status' => 'unhealthy',
                'connection' => (string) config('queue.default'),
                'exception_class' => $exception::class,
            ];
        }

        $report['status'] = $healthy ? 'healthy' : 'unhealthy';

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } else {
            $this->table(['Componente', 'Estado', 'Detalle'], $this->rows($report));
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    private function rows(array $report): array
    {
        return [
            ['application', $report['application']['status'], app()->version()],
            ['database', $report['database']['status'], isset($report['database']['latency_ms']) ? $report['database']['latency_ms'].' ms' : 'no disponible'],
            ['cache', $report['cache']['status'], $report['cache']['store'] ?? 'no disponible'],
            ['queue', $report['queue']['status'], $report['queue']['connection']],
            ['overall', $report['status'], $report['generated_at']],
        ];
    }
}
