<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthCheckCommand extends Command
{
    protected $signature = 'app:health-check {--queue : Comprueba también las tablas de la cola}';

    protected $description = 'Comprueba que Laravel puede iniciar y acceder a sus dependencias críticas';

    public function handle(): int
    {
        try {
            DB::select('select 1');

            if (! Schema::hasTable('migrations')) {
                $this->error('La base de datos no está migrada.');

                return self::FAILURE;
            }

            if ($this->option('queue') && (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs'))) {
                $this->error('Las tablas de la cola no están disponibles.');

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Una dependencia crítica no está disponible.');

            return self::FAILURE;
        }

        $this->info('healthy');

        return self::SUCCESS;
    }
}
