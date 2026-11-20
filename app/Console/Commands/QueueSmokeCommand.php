<?php

namespace App\Console\Commands;

use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QueueSmokeCommand extends Command
{
    protected $signature = 'app:queue-smoke {--timeout=30 : Segundos máximos de espera}';

    protected $description = 'Envía un Job y espera a que el worker confirme su procesamiento';

    public function handle(): int
    {
        $key = 'docker:queue-smoke:'.Str::uuid();
        RecordQueueHeartbeat::dispatch($key);
        $deadline = microtime(true) + max(1, (int) $this->option('timeout'));

        do {
            if (Cache::pull($key)) {
                $this->info('Worker procesó el Job correctamente.');

                return self::SUCCESS;
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        $this->error('El worker no procesó el Job dentro del tiempo esperado.');

        return self::FAILURE;
    }
}
