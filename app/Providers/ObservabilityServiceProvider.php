<?php

namespace App\Providers;

use App\Observability\QueueMonitor;
use App\Observability\SlowQueryMonitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class ObservabilityServiceProvider extends ServiceProvider
{
    public function boot(QueueMonitor $queueMonitor, SlowQueryMonitor $slowQueryMonitor): void
    {
        DB::listen($slowQueryMonitor->handle(...));
        Queue::before($queueMonitor->processing(...));
        Queue::after($queueMonitor->processed(...));
        Queue::exceptionOccurred($queueMonitor->exceptionOccurred(...));
        Queue::failing($queueMonitor->failed(...));
    }
}
